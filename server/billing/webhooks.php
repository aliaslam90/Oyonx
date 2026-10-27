<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';

function billing_apply_stripe_event(array $event, callable $provider, ?string $body = null): array {
    $config=billing_config();
    $body=$body ?? json_encode($event, JSON_THROW_ON_ERROR);
    $db=billing_db();
    try {
    $type=$event['type']??''; $object=$event['data']['object']??[];
    $handled=['checkout.session.completed','checkout.session.async_payment_succeeded','customer.subscription.created','customer.subscription.updated','customer.subscription.deleted','invoice.paid','invoice.payment_failed','charge.refunded','charge.dispute.created'];
    if (!in_array($type,$handled,true)) return ['ok'=>true];
    $db=billing_db();$db->beginTransaction();
    // Serialize all subscription events to avoid stale ordering/racing replays. Fetch current provider state under lock.
    billing_query("SELECT lock_name FROM plugin_billing_locks WHERE lock_name='stripe-events' FOR UPDATE");
    if (billing_query("SELECT external_event_id FROM plugin_webhook_events WHERE provider='stripe' AND environment=? AND external_event_id=?",[$config['environment'],$event['id']])->fetch()) {$db->commit();return ['ok'=>true];}
    $subscriptionId=null;
    if (strpos($type,'customer.subscription.')===0) $subscriptionId=$object['id']??null;
    else $subscriptionId=$object['subscription']??null;
    if (in_array($type,['charge.refunded','charge.dispute.created'],true)) {
        $charge=$type==='charge.dispute.created'?$provider('GET','/charges/'.rawurlencode($object['charge'])):$object;
        $invoiceId=$charge['invoice']??null;
        if ($invoiceId) {$invoice=$provider('GET','/invoices/'.rawurlencode(is_array($invoiceId)?$invoiceId['id']:$invoiceId));$subscriptionId=$invoice['subscription']??null;}
    }
    if (is_array($subscriptionId)) $subscriptionId=$subscriptionId['id']??null;
    if ($subscriptionId) {
        if (!is_string($subscriptionId)||!preg_match('/^sub_[a-zA-Z0-9]+$/',$subscriptionId)) throw new RuntimeException('Invalid subscription ID.');
        $sub=$provider('GET','/subscriptions/'.$subscriptionId);
        $accountId=$sub['metadata']['oyonx_account']??''; $planCode=$sub['metadata']['oyonx_plan']??'';
        // An Oyonx merchant account can have unrelated payments/subscriptions.
        if ($accountId === '' || $planCode === '') {
            $db->commit(); return ['ok'=>true,'ignored'=>true];
        }
        $customer=is_array($sub['customer'])?$sub['customer']['id']:$sub['customer'];
        $owner=billing_query('SELECT account_id FROM plugin_customers WHERE external_customer_id=? AND environment=?',[$customer,$config['environment']])->fetchColumn();
        if (!$owner||$owner!==$accountId) throw new RuntimeException('Customer ownership mismatch.');
        billing_query('SELECT id FROM plugin_accounts WHERE id=? FOR UPDATE',[$accountId]);
        $plan=billing_query('SELECT * FROM plugin_plans WHERE code=?',[$planCode])->fetch();
        $items=$sub['items']['data']??[];
        if (!$plan||count($items)!==1||($items[0]['quantity']??0)!==1||!billing_price_matches($items[0]['price'],$plan)) throw new RuntimeException('Subscription plan mismatch.');
        $mapped=billing_query("SELECT plan_code FROM plugin_provider_prices WHERE provider='stripe' AND environment=? AND external_price_id=?",[$config['environment'],$items[0]['price']['id']])->fetchColumn();
        if ($mapped!==$planCode) throw new RuntimeException('Unknown provider price.');
        $latest=$sub['latest_invoice']??null;
        $latest=is_array($latest)?$latest:($latest?$provider('GET','/invoices/'.rawurlencode($latest)):null);
        $existing=billing_query("SELECT * FROM plugin_subscriptions WHERE provider='stripe' AND environment=? AND external_subscription_id=?",[$config['environment'],$subscriptionId])->fetch();
        $access=$existing['access_until']??null;
        if ($existing && $existing['plan_code'] !== $planCode) throw new RuntimeException('Subscription plan cannot change implicitly.');
        // Only a paid invoice grants/extends access. Incomplete/unpaid checkout never does.
        if ($latest) {
            $period=billing_paid_through($latest,$subscriptionId,$items[0]['price']['id']);
            if ($period!==null && (!$access || $period>strtotime($access.' UTC'))) $access=gmdate('Y-m-d H:i:s',$period);
        }
        $status=$sub['status'];
        if (in_array($type,['charge.refunded','charge.dispute.created'],true)) {
            // Do not revoke a newer paid billing period for a historical refund.
            if (($latest['id']??null)===($invoice['id']??$charge['invoice']??null)) $status='revoked';
        }
        // A refund revocation is sticky until the next paid billing period.
        if (($existing['status']??'')==='revoked'&&(!$access||$access<=($existing['access_until']??''))) $status='revoked';
        billing_query("INSERT INTO plugin_subscriptions(id,account_id,plan_code,provider,environment,external_subscription_id,status,access_until,cancel_at_period_end) VALUES(?,?,?,'stripe',?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),access_until=VALUES(access_until),cancel_at_period_end=VALUES(cancel_at_period_end)",[billing_id(),$accountId,$planCode,$config['environment'],$subscriptionId,$status,$access,!empty($sub['cancel_at_period_end'])?1:0]);
    }
    billing_query("INSERT INTO plugin_webhook_events(provider,environment,external_event_id,payload_sha256) VALUES('stripe',?,?,?)",[$config['environment'],$event['id'],hash('sha256',$body)]);
    $db->commit();return ['ok'=>true];
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
}
