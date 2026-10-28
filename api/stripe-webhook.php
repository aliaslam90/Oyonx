<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/server/billing/bootstrap.php';
header('Content-Type: application/json'); header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); billing_fail(405,'Method not allowed.'); }
try {
    $config=billing_config();
    $body=file_get_contents('php://input',false,null,0,1048577);
    if (!is_string($body)||strlen($body)>1048576) billing_fail(413,'Payload too large.');
    if (!billing_signature_valid($body,$_SERVER['HTTP_STRIPE_SIGNATURE']??'',$config['stripe_webhook_secret']??'',time())) billing_fail(400,'Invalid signature.');
    $event=json_decode($body,true);
    if (!is_array($event)||!is_string($event['id']??null)||strlen($event['id'])>255) billing_fail(400,'Invalid event.');
    if (($event['livemode']??null)!==($config['environment']==='live')) billing_fail(400,'Wrong environment.');
    require_once dirname(__DIR__).'/server/billing/webhooks.php';
    echo json_encode(billing_apply_stripe_event($event, 'billing_stripe', $body));
} catch(Throwable $e) {
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    error_log('Oyonx payment event failed: '.get_class($e));
    billing_fail(503,'Event processing unavailable; retry required.');
}
