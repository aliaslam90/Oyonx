<?php
declare(strict_types=1);
require_once __DIR__ . '/domain.php';

function billing_config(): array {
    static $config;
    if ($config !== null) return $config;
    // Environment variables hold a PATH only; secrets remain outside public_html/Git.
    $path = getenv('OYONX_BILLING_CONFIG') ?: dirname(__DIR__, 3) . '/oyonx-private/billing.php';
    $real = realpath($path);
    $root = realpath(dirname(__DIR__, 2));
    if (!$real || strpos($real, $root . DIRECTORY_SEPARATOR) === 0) throw new RuntimeException('Billing is not configured.');
    $config = require $real;
    if (!is_array($config) || empty($config['enabled'])) throw new RuntimeException('Billing is not configured.');
    if (!in_array($config['environment'] ?? '', ['test', 'live'], true)) throw new RuntimeException('Invalid billing environment.');
    return $config;
}
function billing_db(): PDO {
    static $db;
    if ($db) return $db;
    $config = billing_config();
    $db = new PDO($config['dsn'], $config['db_user'], $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $db->exec("SET time_zone = '+00:00'");
    return $db;
}
function billing_query(string $sql, array $params = []): PDOStatement {
    $stmt = billing_db()->prepare($sql); $stmt->execute($params); return $stmt;
}
function billing_id(): string {
    $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 15) | 64); $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}
function billing_fail(int $status, string $message): void {
    http_response_code($status); echo json_encode(['ok' => false, 'message' => $message]); exit;
}
function billing_json(): array {
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') billing_fail(415, 'Send JSON.');
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) billing_fail(413, 'Request is too large.');
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if ($raw === false || strlen($raw) > 16384) billing_fail(413, 'Request is too large.');
    $obj = json_decode($raw);
    if (!is_object($obj)) billing_fail(400, 'Invalid request.');
    return get_object_vars($obj);
}
function billing_text(array $data, string $key, int $max): string {
    $value = $data[$key] ?? '';
    if (!is_string($value) || strlen($value) > $max) billing_fail(400, 'Invalid ' . $key . '.');
    return $value;
}
function billing_account(bool $verified = true): array {
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer ([a-f0-9]{64})$/', $authorization, $match)) {
        if (!in_array($_GET['action'] ?? '', ['use','use-complete','use-cancel','access'], true)) billing_fail(403, 'Plugin tokens cannot manage your account.');
        $account = billing_query("SELECT a.* FROM plugin_accounts a JOIN plugin_account_tokens t ON a.id=t.account_id WHERE t.token_hash=? AND t.purpose='plugin' AND t.consumed_at IS NULL AND t.expires_at>UTC_TIMESTAMP()", [hash('sha256', $match[1])])->fetch();
    } else {
        if ($authorization !== '') billing_fail(401, 'Invalid access token.');
        $account = !empty($_SESSION['account_id']) ? billing_query('SELECT * FROM plugin_accounts WHERE id=?', [$_SESSION['account_id']])->fetch() : false;
    }
    if (!$account) billing_fail(401, 'Sign in to your Oyonx account.');
    if ($authorization === '' && ($account['session_version'] ?? null) !== ($_SESSION['session_version'] ?? null)) { unset($_SESSION['account_id']); billing_fail(401, 'Sign in again.'); }
    if ($verified && !$account['email_verified_at']) billing_fail(403, 'Verify your email first.');
    return $account;
}
function billing_token(string $account, string $purpose, int $seconds): string {
    $token = bin2hex(random_bytes(32));
    billing_query('INSERT INTO plugin_account_tokens(token_hash,account_id,purpose,expires_at) VALUES(?,?,?,?)', [hash('sha256', $token), $account, $purpose, gmdate('Y-m-d H:i:s', time() + $seconds)]);
    return $token;
}
function billing_mail(string $email, string $purpose, string $token): void {
    $config = billing_config();
    $url = 'https://oyonx.com/account/#' . http_build_query(['action' => $purpose, 'token' => $token]);
    $subject = $purpose === 'verify' ? 'Verify your Oyonx account' : 'Reset your Oyonx password';
    if (!mail($email, $subject, $subject . "\n\n" . $url . "\n\nIf you did not request this, ignore this email.", "From: Oyonx <" . ($config['mail_from'] ?? 'noreply@oyonx.com') . ">\r\nContent-Type: text/plain; charset=UTF-8")) throw new RuntimeException('Unable to send account email.');
}
function billing_rate(string $action, int $max): void {
    $bucket = (int)floor(time() / 600);
    $key = hash('sha256', $action . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $bucket);
    billing_query('INSERT INTO plugin_rate_limits(bucket_key,expires_at,attempts) VALUES(?,?,1) ON DUPLICATE KEY UPDATE attempts=attempts+1', [$key, gmdate('Y-m-d H:i:s', ($bucket + 1) * 600)]);
    $count = (int)billing_query('SELECT attempts FROM plugin_rate_limits WHERE bucket_key=?', [$key])->fetchColumn();
    if ($count > $max) { header('Retry-After: 600'); billing_fail(429, 'Too many attempts. Try again later.'); }
    if (random_int(1, 100) === 1) billing_query('DELETE FROM plugin_rate_limits WHERE expires_at<UTC_TIMESTAMP() LIMIT 1000');
}
function billing_stripe(string $method, string $path, array $data = [], ?string $key = null): array {
    if (!preg_match('~^/[a-z0-9_/?=\[\]&.-]+$~i', $path)) throw new RuntimeException('Invalid provider resource.');
    $config = billing_config();
    $secret = $config['stripe_secret_key'] ?? '';
    $expected = $config['environment'] === 'live' ? 'sk_live_' : 'sk_test_';
    if (strpos($secret, $expected) !== 0) throw new RuntimeException('Payment environment is not configured.');
    $curl = curl_init('https://api.stripe.com/v1' . $path);
    $headers = ['Authorization: Bearer ' . $secret, 'Stripe-Version: 2024-06-20'];
    if ($key) $headers[] = 'Idempotency-Key: ' . $key;
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25, CURLOPT_HTTPHEADER => $headers, CURLOPT_CUSTOMREQUEST => $method]);
    if ($method === 'POST') curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    $raw = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
    $result = is_string($raw) ? json_decode($raw, true) : null;
    if ($status < 200 || $status >= 300 || !is_array($result)) throw new RuntimeException('Payment provider is temporarily unavailable.');
    return $result;
}
