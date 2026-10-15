<?php
declare(strict_types=1);
require __DIR__ . '/../server/inquiry.php';

$checks = 0;
function check(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    $checks++;
}
function validate(array $fields): array
{
    return oyonx_validate_inquiry(json_encode($fields));
}
$valid = ['name' => 'Ali Aslam', 'email' => 'ali@example.com', 'phone' => '+971 555 123456', 'vision' => "A new website\nWith two pages"];
check(validate($valid)['status'] === 200, 'normal submission');
check(validate(['name' => 'علی', 'email' => 'ali@example.com'])['status'] === 200, 'Unicode name');
foreach (['{', '[]', 'null', '"text"', '{"name":"\xff"}'] as $raw) {
    check(oyonx_validate_inquiry($raw)['status'] === 400, 'invalid JSON/object');
}
foreach (['name', 'email', 'phone', 'vision', 'honey'] as $field) {
    foreach ([[], new stdClass(), 123, true, null] as $value) {
        check(validate(array_merge($valid, [$field => $value]))['status'] === 400, 'reject non-string ' . $field);
    }
}
foreach (['name' => 200, 'email' => 254, 'phone' => 50, 'vision' => 10000, 'honey' => 200] as $field => $max) {
    check(validate(array_merge($valid, [$field => str_repeat('a', $max + 1)]))['status'] === 400, 'limit ' . $field);
}
check(oyonx_validate_inquiry(str_repeat(' ', 16385))['status'] === 413, 'oversized body');
check(validate(['name' => '', 'email' => 'ali@example.com'])['status'] === 400, 'missing name');
check(validate(['name' => 'Ali', 'email' => 'invalid'])['status'] === 400, 'invalid email');
check(validate(array_merge($valid, ['name' => "Ali\r\nBcc: someone@example.com"]))['status'] === 400, 'header injection');
check(validate(array_merge($valid, ['vision' => "hello\0world"]))['status'] === 400, 'NUL message');
check(validate(['honey' => 'bot'])['spam'] === true, 'honeypot');
check(validate(array_merge($valid, ['name' => '  Ali  ']))['fields']['name'] === 'Ali', 'normalization');

$file = tempnam(__DIR__, 'oyonx-test-');
if ($file === false) {
    throw new RuntimeException('Cannot create temporary test store.');
}
try {
    for ($i = 0; $i < OYONX_RATE_LIMIT; $i++) {
        check(oyonx_rate_limit($file, '192.0.2.1', 1000)['status'] === 200, 'allowed request');
    }
    $blocked = oyonx_rate_limit($file, '192.0.2.1', 1001);
    check($blocked['status'] === 429 && $blocked['retry_after'] === 599, 'rate limit with retry');
    check(oyonx_rate_limit($file, '192.0.2.2', 1001)['status'] === 200, 'independent peer');
    check(oyonx_rate_limit($file, '192.0.2.1', 1600)['status'] === 200, 'window expiry');
    file_put_contents($file, 'corrupt');
    check(oyonx_rate_limit($file, '192.0.2.1', 1601)['status'] === 503, 'fail closed on corrupt store');
    check(oyonx_rate_limit('/missing-directory/requests.json', '192.0.2.1', 1601)['status'] === 503, 'fail closed on unavailable store');
} finally {
    unlink($file);
}
echo "Passed {$checks} inquiry checks. No email sent.\n";
