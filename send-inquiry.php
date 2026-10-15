<?php
/**
 * Oyonx inquiry form — delivers to info@oyonx.com via Hostinger mail().
 * No SMTP password or config file required.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST, OPTIONS');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

const TO_EMAIL   = 'info@oyonx.com';
const FROM_EMAIL = 'noreply@oyonx.com';

require_once __DIR__ . "/server/inquiry.php";

function respond(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(["success" => false, "message" => $message]);
    exit;
}

$contentType = strtolower(trim(explode(";", $_SERVER["CONTENT_TYPE"] ?? "")[0]));
if ($contentType !== "application/json") {
    respond(415, "Content-Type must be application/json.");
}
if ((int) ($_SERVER["CONTENT_LENGTH"] ?? 0) > OYONX_MAX_BODY_BYTES) {
    respond(413, "Request body is too large.");
}

$rateDirectory = sys_get_temp_dir() . "/oyonx-inquiries-" . hash("sha256", __DIR__);
if (!is_dir($rateDirectory) && !@mkdir($rateDirectory, 0700, true) && !is_dir($rateDirectory)) {
    respond(503, "Enquiries are temporarily unavailable. Please email info@oyonx.com.");
}
$rate = oyonx_rate_limit($rateDirectory . "/requests.json", $_SERVER["REMOTE_ADDR"] ?? "unknown", time());
if ($rate["status"] === 429) {
    header("Retry-After: " . $rate["retry_after"]);
    respond(429, "Too many enquiries. Please try again in a few minutes.");
}
if ($rate["status"] !== 200) {
    respond(503, "Enquiries are temporarily unavailable. Please email info@oyonx.com.");
}

$raw = file_get_contents("php://input", false, null, 0, OYONX_MAX_BODY_BYTES + 1);
$result = oyonx_validate_inquiry($raw === false ? "" : $raw);
if ($result["status"] !== 200) {
    respond($result["status"], $result["message"]);
}
if (!empty($result["spam"])) {
    echo json_encode(["success" => true]);
    exit;
}
["name" => $name, "email" => $email, "phone" => $phone, "vision" => $vision] = $result["fields"];

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
