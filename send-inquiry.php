<?php
/**
 * Oyonx inquiry form — delivers to info@oyonx.com via Hostinger mail().
 * No SMTP password or config file required.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

const TO_EMAIL   = 'info@oyonx.com';
const FROM_EMAIL = 'noreply@oyonx.com';

$raw  = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request body']);
    exit;
}

if (!empty($data['honey'])) {
    echo json_encode(['success' => true]);
    exit;
}

$name   = trim((string) ($data['name'] ?? ''));
$email  = trim((string) ($data['email'] ?? ''));
$phone  = trim((string) ($data['phone'] ?? ''));
$vision = trim((string) ($data['vision'] ?? ''));

if ($name === '' || $email === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name and email are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

$safeName  = preg_replace('/[\r\n]+/', ' ', $name);
$safeEmail = preg_replace('/[\r\n]+/', '', $email);
$safePhone = preg_replace('/[\r\n]+/', ' ', $phone);

$subject = 'New enquiry from ' . $safeName . ' — Oyonx';

$body  = "New project enquiry from oyonx.com\n";
$body .= str_repeat('-', 40) . "\n\n";
$body .= "Name:  {$safeName}\n";
$body .= "Email: {$safeEmail}\n";
$body .= "Phone: " . ($safePhone !== '' ? $safePhone : 'Not provided') . "\n\n";
$body .= "Project details:\n";
$body .= ($vision !== '' ? $vision : '(No message provided)') . "\n\n";
$body .= str_repeat('-', 40) . "\n";
$body .= 'Sent: ' . gmdate('Y-m-d H:i:s') . " UTC\n";

$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
// Show the visitor's name in the inbox — not "me" (same to/from address)
$fromDisplayName = $safeName . ' via Oyonx';
$encodedFrom     = '=?UTF-8?B?' . base64_encode($fromDisplayName) . '?=';
$encodedReplyTo  = '=?UTF-8?B?' . base64_encode($safeName) . '?=';

$headers  = "From: {$encodedFrom} <" . FROM_EMAIL . ">\r\n";
$headers .= "Reply-To: {$encodedReplyTo} <{$safeEmail}>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$sent = @mail(TO_EMAIL, $encodedSubject, $body, $headers);

if ($sent) {
    echo json_encode(['success' => true, 'message' => 'Your enquiry has been sent.']);
    exit;
}

http_response_code(500);
echo json_encode([
    'success' => false,
    'message' => 'Could not send email. Please email info@oyonx.com directly or try again later.',
]);
