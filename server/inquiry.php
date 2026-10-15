<?php
declare(strict_types=1);

const OYONX_MAX_BODY_BYTES = 16384;
const OYONX_RATE_LIMIT = 5;
const OYONX_RATE_WINDOW = 600;

/** Return normalized fields or a validation error. Limits are UTF-8 bytes. */
function oyonx_validate_inquiry(string $raw): array
{
    if (strlen($raw) > OYONX_MAX_BODY_BYTES) {
        return ['status' => 413, 'message' => 'Request body is too large.'];
    }
    $decoded = json_decode($raw);
    if (!is_object($decoded)) {
        return ['status' => 400, 'message' => 'Invalid request body.'];
    }
    $data = get_object_vars($decoded);
    $fields = [];
    foreach (['name' => 200, 'email' => 254, 'phone' => 50, 'vision' => 10000, 'honey' => 200] as $key => $limit) {
        $value = $data[$key] ?? '';
        // Explicit null is invalid, just like arrays, numbers and objects.
        if (array_key_exists($key, $data) && !is_string($data[$key])) {
            return ['status' => 400, 'message' => 'Form fields must be text.'];
        }
        if (strlen($value) > $limit) {
            return ['status' => 400, 'message' => ucfirst($key) . ' is too long.'];
        }
        if ($key !== 'vision' && preg_match('/[\x00-\x1f\x7f]/', $value)) {
            return ['status' => 400, 'message' => 'Invalid characters in form fields.'];
        }
        if ($key === 'vision' && preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
            return ['status' => 400, 'message' => 'Invalid characters in project details.'];
        }
        $fields[$key] = trim($value);
    }
    if ($fields['honey'] !== '') {
        return ['status' => 200, 'spam' => true];
    }
    if ($fields['name'] === '' || $fields['email'] === '') {
        return ['status' => 400, 'message' => 'Name and email are required.'];
    }
    if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        return ['status' => 400, 'message' => 'Invalid email address.'];
    }
    return ['status' => 200, 'fields' => $fields];
}

/** Atomic, bounded per-IP throttling. Store outside the document root. */
function oyonx_rate_limit(string $file, string $ip, int $now): array
{
    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        return ['status' => 503];
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return ['status' => 503];
    }
    try {
        $raw = stream_get_contents($handle, 2 * 1024 * 1024 + 1);
        $entries = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($entries) || strlen($raw) > 2 * 1024 * 1024) {
            return ['status' => 503];
        }
        foreach ($entries as $key => $entry) {
            if (!is_array($entry) || !isset($entry['reset'], $entry['count'])) {
                return ['status' => 503];
            }
            if ($entry['reset'] <= $now) {
                unset($entries[$key]);
            }
        }
        // Use the server's peer address, never an untrusted forwarding header.
        $key = hash('sha256', $ip);
        $entry = $entries[$key] ?? ['reset' => $now + OYONX_RATE_WINDOW, 'count' => 0];
        if ($entry['count'] >= OYONX_RATE_LIMIT) {
            return ['status' => 429, 'retry_after' => max(1, $entry['reset'] - $now)];
        }
        if (!isset($entries[$key]) && count($entries) >= 10000) {
            return ['status' => 503];
        }
        $entry['count']++;
        $entries[$key] = $entry;
        $json = json_encode($entries);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
            return ['status' => 503];
        }
        return ['status' => 200];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
