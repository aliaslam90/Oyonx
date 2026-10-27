<?php
declare(strict_types=1);

function billing_signature_valid(string $body, string $header, string $secret, int $now): bool {
    $parts = [];
    foreach (explode(',', $header) as $piece) {
        $pair = explode('=', trim($piece), 2);
        if (count($pair) === 2) $parts[$pair[0]][] = $pair[1];
    }
    $stamp = $parts['t'][0] ?? '';
    if (!ctype_digit($stamp) || abs($now - (int)$stamp) > 300 || $secret === '') return false;
    $expected = hash_hmac('sha256', $stamp . '.' . $body, $secret);
    foreach ($parts['v1'] ?? [] as $signature) if (hash_equals($expected, $signature)) return true;
    return false;
}

function billing_subscription_access(array $subscription, int $now): bool {
    return in_array($subscription['status'] ?? '', ['active', 'past_due'], true)
        && !empty($subscription['access_until'])
        && strtotime($subscription['access_until'] . ' UTC') > $now;
}

function billing_product_access(string $product, array $subscriptions, array $memberships, int $now): bool {
    foreach ($subscriptions as $subscription) {
        if (in_array($product, $memberships[$subscription['plan_code']] ?? [], true)
            && billing_subscription_access($subscription, $now)) return true;
    }
    return false;
}

function billing_price_matches(array $price, array $plan): bool {
    return ($price['currency'] ?? '') === strtolower($plan['currency'])
        && ($price['unit_amount'] ?? null) === (int)$plan['amount_minor']
        && ($price['recurring']['interval'] ?? '') === $plan['interval_unit']
        && ($price['recurring']['interval_count'] ?? 1) === 1;
}

function billing_plan_code(string $product, string $interval, array $products): string {
    if (!in_array($interval, ['month', 'year'], true)
        || ($product !== 'bundle' && !in_array($product, $products, true))) {
        throw new InvalidArgumentException('Unknown plan.');
    }
    return $product === 'bundle' ? 'current-seven-' . $interval : $product . '-' . $interval;
}

/** Paid-through dates come from paid recurring invoice lines, never an unpaid
 * subscription's current_period_end or a browser-supplied success URL. */
function billing_paid_through(array $invoice, string $subscriptionId, string $priceId): ?int {
    $owner = $invoice['subscription'] ?? null;
    if (is_array($owner)) $owner = $owner['id'] ?? null;
    if (($invoice['status'] ?? '') !== 'paid' || ($invoice['paid'] ?? false) !== true || $owner !== $subscriptionId) return null;
    if (!empty($invoice['lines']['has_more'])) throw new RuntimeException('Invoice lines require pagination.');
    $paidUntil = null;
    foreach ($invoice['lines']['data'] ?? [] as $line) {
        if (($line['type'] ?? '') !== 'subscription' || ($line['price']['id'] ?? '') !== $priceId || !empty($line['proration'])) continue;
        $end = $line['period']['end'] ?? null;
        if (is_int($end) && $end > 0) $paidUntil = max($paidUntil ?? 0, $end);
    }
    return $paidUntil;
}
