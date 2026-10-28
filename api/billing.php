<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/server/billing/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
// Figma plugin UI is sandboxed with a null Origin. Only token-only endpoints permit it.
$pluginAction = in_array($_GET['action'] ?? '', ['use','use-complete','use-cancel','access'], true);
if ($pluginAction && ($_SERVER['HTTP_ORIGIN'] ?? '') === 'null') {
    header('Access-Control-Allow-Origin: null'); header('Vary: Origin');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    if (!$pluginAction) billing_fail(403,'Cross-origin account access is not supported.');
    http_response_code(204); exit;
}
try {
    $config = billing_config();
    session_name('oyonx_account');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1'); session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? '';
    if (!is_string($action)) billing_fail(400, 'Invalid action.');
    if ($method === 'GET' && $action === 'session') {
        $account = !empty($_SESSION['account_id']) ? billing_account(false) : null;
        $result = ['ok' => true, 'csrf' => $_SESSION['csrf'], 'account' => $account ? ['email' => $account['email'], 'verified' => (bool)$account['email_verified_at']] : null];
    } elseif ($method === 'GET' && $action === 'access') {
        $account = billing_account();
        $subscriptions = billing_query('SELECT s.plan_code,s.status,s.access_until,s.cancel_at_period_end FROM plugin_subscriptions s WHERE s.account_id=? AND s.environment=?', [$account['id'], $config['environment']])->fetchAll();
        $memberships = [];
        foreach (billing_query('SELECT * FROM plugin_plan_products')->fetchAll() as $row) $memberships[$row['plan_code']][] = $row['product_slug'];
        $products = billing_query('SELECT slug,name FROM plugin_products WHERE active=1')->fetchAll();
        foreach ($products as &$product) {
            $used = (int)billing_query("SELECT uses_consumed FROM plugin_trial_usage WHERE account_id=? AND product_slug=? AND window_key='lifetime'", [$account['id'], $product['slug']])->fetchColumn();
            $product['paid'] = billing_product_access($product['slug'], $subscriptions, $memberships, time());
            $reserved=(int)billing_query("SELECT COUNT(*) FROM plugin_usage_events WHERE account_id=? AND product_slug=? AND source='trial' AND state='reserved' AND expires_at>UTC_TIMESTAMP()",[$account['id'],$product['slug']])->fetchColumn();
            $product['freeUsesRemaining'] = max(0, 5 - $used - $reserved);
        }
        unset($product);
        $plans = billing_query('SELECT code,currency,amount_minor,interval_unit FROM plugin_plans WHERE active=1')->fetchAll();
        $result = ['ok' => true, 'products' => $products, 'plans' => $plans, 'subscriptions' => $subscriptions];
    } elseif ($method === 'POST') {
        $data = billing_json();
        $bearer = preg_match('/^Bearer [a-f0-9]{64}$/', $_SERVER['HTTP_AUTHORIZATION'] ?? '') === 1;
        if (!$bearer || !in_array($action, ['use','use-complete','use-cancel'], true)) {
            if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) billing_fail(403, 'Reload the page and try again.');
            if (!empty($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== 'https://oyonx.com') billing_fail(403, 'Invalid request origin.');
        }
        switch ($action) {
        case 'register':
            billing_rate('register', 5);
            $email = strtolower(trim(billing_text($data, 'email', 254)));
            $password = billing_text($data, 'password', 72);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) billing_fail(400, 'Use a valid email and at least 12 password characters.');
            $existing = billing_query('SELECT id FROM plugin_accounts WHERE email=?', [$email])->fetch();
            if (!$existing) {
                $id = billing_id();
                billing_query('INSERT INTO plugin_accounts(id,email,password_hash) VALUES(?,?,?)', [$id, $email, password_hash($password, PASSWORD_DEFAULT)]);
                billing_mail($email, 'verify', billing_token($id, 'verify', 86400));
            }
            $result = ['ok' => true, 'message' => 'If this email can be registered, a verification link has been sent. Otherwise sign in or reset your password.'];
            break;
        case 'login':
            billing_rate('login', 20);
            $email = strtolower(trim(billing_text($data, 'email', 254)));
            $password = billing_text($data, 'password', 72);
            $account = billing_query('SELECT * FROM plugin_accounts WHERE email=?', [$email])->fetch();
            $hash = $account['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
            if (!password_verify($password, $hash) || !$account) billing_fail(401, 'Email or password is incorrect.');
            if (!$account['session_version']) {
                billing_query('UPDATE plugin_accounts SET session_version=COALESCE(session_version,?) WHERE id=?', [bin2hex(random_bytes(32)), $account['id']]);
                $account = billing_query('SELECT * FROM plugin_accounts WHERE id=?', [$account['id']])->fetch();
            }
            session_regenerate_id(true); $_SESSION['session_version']=$account['session_version']; $_SESSION['account_id'] = $account['id']; $_SESSION['authenticated_at'] = time(); $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $result = ['ok' => true]; break;
        case 'logout':
            $_SESSION = []; session_destroy();
            setcookie(session_name(), '', ['expires' => time()-3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
            $result = ['ok' => true]; break;
        case 'forgot':
            billing_rate('forgot', 5);
            $email = strtolower(trim(billing_text($data, 'email', 254)));
            $account = billing_query('SELECT * FROM plugin_accounts WHERE email=?', [$email])->fetch();
            if ($account) billing_mail($account['email'], 'reset', billing_token($account['id'], 'reset', 1800));
            $result = ['ok' => true, 'message' => 'If that account exists, a reset link has been sent.']; break;
        case 'resend':
            billing_rate('resend', 3); $account = billing_account(false);
            if (!$account['email_verified_at']) billing_mail($account['email'], 'verify', billing_token($account['id'], 'verify', 86400));
            $result = ['ok' => true, 'message' => 'Check your email for a verification link.']; break;
        case 'verify': case 'reset':
            billing_rate('token', 20);
            $token = billing_text($data, 'token', 64);
            if (!preg_match('/^[a-f0-9]{64}$/', $token)) billing_fail(400, 'Invalid or expired link.');
            $password = $action === 'reset' ? billing_text($data, 'password', 72) : '';
            if ($action === 'reset' && strlen($password) < 12) billing_fail(400, 'Use at least 12 password characters.');
            $db = billing_db(); $db->beginTransaction();
            $row = billing_query('SELECT * FROM plugin_account_tokens WHERE token_hash=? AND purpose=? AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE', [hash('sha256', $token), $action])->fetch();
            if (!$row) { $db->rollBack(); billing_fail(400, 'Invalid or expired link.'); }
            billing_query('UPDATE plugin_account_tokens SET consumed_at=UTC_TIMESTAMP() WHERE token_hash=?', [$row['token_hash']]);
            if ($action === 'verify') billing_query('UPDATE plugin_accounts SET email_verified_at=UTC_TIMESTAMP() WHERE id=?', [$row['account_id']]);
            else {
                billing_query('UPDATE plugin_accounts SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $row['account_id']]);
                billing_query('DELETE FROM plugin_account_tokens WHERE account_id=?', [$row['account_id']]);
                billing_query('UPDATE plugin_accounts SET sessions_valid_after=UTC_TIMESTAMP(),session_version=? WHERE id=?', [bin2hex(random_bytes(32)), $row['account_id']]);
                $_SESSION = []; session_regenerate_id(true);
            }
            $db->commit(); $result = ['ok' => true, 'message' => $action === 'verify' ? 'Email verified. You can sign in.' : 'Password updated. Sign in again.']; break;
        case 'plugin-token':
            billing_rate('plugin-token', 10); $account = billing_account();
            // The user transfers this revocable token into the plugin. It cannot access billing/settings.
            $token = billing_token($account['id'], 'plugin', 2592000);
            $result = ['ok' => true, 'token' => $token, 'expiresInDays' => 30]; break;
        case 'revoke-plugin-tokens':
            $account = billing_account();
            billing_query("DELETE FROM plugin_account_tokens WHERE account_id=? AND purpose='plugin'", [$account['id']]);
            $result = ['ok' => true]; break;
        case 'use-complete': case 'use-cancel':
            if (!$bearer) billing_fail(401, 'Use your plugin access token.');
            billing_rate('use-finalize', 120); $account = billing_account();
            $product = billing_text($data, 'product', 64); $requestKey = billing_text($data, 'requestKey', 64);
            if (!preg_match('/^[a-f0-9]{64}$/', $requestKey)) billing_fail(400, 'Invalid request key.');
            $db = billing_db(); $db->beginTransaction();
            billing_query('SELECT id FROM plugin_accounts WHERE id=? FOR UPDATE', [$account['id']]);
            $event = billing_query('SELECT * FROM plugin_usage_events WHERE account_id=? AND request_key=? AND product_slug=? FOR UPDATE', [$account['id'], $requestKey, $product])->fetch();
            if (!$event) { $db->rollBack(); billing_fail(404, 'Unknown operation.'); }
            if ($event['state'] === 'completed' && $action === 'use-complete') { $db->commit(); $result=['ok'=>true]; break; }
            if ($event['state'] !== 'reserved' || strtotime($event['expires_at'].' UTC') <= time()) { $db->rollBack(); billing_fail(409, 'Operation reservation expired or finalized.'); }
            if ($action === 'use-complete' && $event['source'] === 'trial') billing_query("UPDATE plugin_trial_usage SET uses_consumed=uses_consumed+1 WHERE account_id=? AND product_slug=? AND window_key='lifetime'", [$account['id'], $product]);
            billing_query('UPDATE plugin_usage_events SET state=? WHERE id=?', [$action==='use-complete'?'completed':'cancelled', $event['id']]);
            $db->commit(); $result=['ok'=>true]; break;
        case 'use':
            if (!$bearer) billing_fail(401, 'Use your plugin access token.');
            billing_rate('use', 120); $account = billing_account();
            $product = billing_text($data, 'product', 64); $requestKey = billing_text($data, 'requestKey', 64);
            if (!preg_match('/^[a-f0-9]{64}$/', $requestKey)) billing_fail(400, 'Invalid request key.');
            if (!billing_query('SELECT slug FROM plugin_products WHERE slug=? AND active=1', [$product])->fetch()) billing_fail(404, 'Unknown plugin.');
            $db = billing_db(); $db->beginTransaction();
            // Serialize authorization across tokens for this account, including concurrent trial calls.
            billing_query('SELECT id FROM plugin_accounts WHERE id=? FOR UPDATE', [$account['id']]);
            $event = billing_query('SELECT * FROM plugin_usage_events WHERE account_id=? AND request_key=?', [$account['id'], $requestKey])->fetch();
            if ($event) {
                if ($event['product_slug'] !== $product) { $db->rollBack(); billing_fail(409, 'Request key belongs to another plugin.'); }
                if ($event['state'] !== 'reserved' || strtotime($event['expires_at'].' UTC') <= time()) { $db->rollBack(); billing_fail(409, 'This operation is finalized. Use a new request key for a new operation.'); }
                $db->commit(); $result = ['ok'=>true, 'allowed'=>true, 'source'=>$event['source'], 'replayed'=>true]; break;
            }
            $subscriptions = billing_query('SELECT s.* FROM plugin_subscriptions s JOIN plugin_plan_products p ON s.plan_code=p.plan_code WHERE s.account_id=? AND s.environment=? AND p.product_slug=?', [$account['id'], $config['environment'], $product])->fetchAll();
            $paid = false; foreach ($subscriptions as $sub) if (billing_subscription_access($sub, time())) $paid = true;
            $source = $paid ? 'paid' : 'trial';
            if (!$paid) {
                billing_query("INSERT IGNORE INTO plugin_trial_usage(account_id,product_slug,window_key,uses_consumed) VALUES(?,?,'lifetime',0)", [$account['id'], $product]);
                $used = (int)billing_query("SELECT uses_consumed FROM plugin_trial_usage WHERE account_id=? AND product_slug=? AND window_key='lifetime' FOR UPDATE", [$account['id'], $product])->fetchColumn();
                $reserved=(int)billing_query("SELECT COUNT(*) FROM plugin_usage_events WHERE account_id=? AND product_slug=? AND source='trial' AND state='reserved' AND expires_at>UTC_TIMESTAMP()",[$account['id'],$product])->fetchColumn();
                if ($used + $reserved >= 5) { $db->rollBack(); billing_fail(402, 'Your five free uses are used. Subscribe to continue.'); }
            }
            billing_query("INSERT INTO plugin_usage_events(id,account_id,product_slug,request_key,source,state,expires_at) VALUES(?,?,?,?,?,'reserved',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE))", [billing_id(), $account['id'], $product, $requestKey, $source]);
            $db->commit(); $result = ['ok'=>true,'allowed'=>true,'source'=>$source]; break;
        case 'checkout':
            billing_rate('checkout', 10); $account = billing_account();
            $products = billing_query('SELECT slug FROM plugin_products WHERE active=1')->fetchAll(PDO::FETCH_COLUMN);
            try { $planCode = billing_plan_code(billing_text($data,'product',64), billing_text($data,'interval',5), $products); }
            catch (InvalidArgumentException $e) { billing_fail(400, 'Unknown plan.'); }
            $plan = billing_query("SELECT p.*,x.external_price_id FROM plugin_plans p JOIN plugin_provider_prices x ON p.code=x.plan_code WHERE p.code=? AND p.active=1 AND x.provider='stripe' AND x.environment=?", [$planCode, $config['environment']])->fetch();
            if (!$plan) billing_fail(409, 'This plan is not available yet.');
            $price = billing_stripe('GET', '/prices/' . rawurlencode($plan['external_price_id']));
            if (!billing_price_matches($price, $plan) || empty($price['active'])) throw new RuntimeException('Plan configuration mismatch.');
            // Serialize checkout creation to prevent double-clicks creating multiple subscriptions.
            $db = billing_db(); $db->beginTransaction();
            billing_query('SELECT id FROM plugin_accounts WHERE id=? FOR UPDATE', [$account['id']]);
            $overlap = billing_query("SELECT s.* FROM plugin_subscriptions s JOIN plugin_plan_products owned ON s.plan_code=owned.plan_code JOIN plugin_plan_products requested ON owned.product_slug=requested.product_slug WHERE s.account_id=? AND s.environment=? AND requested.plan_code=?", [$account['id'],$config['environment'],$planCode])->fetchAll();
            foreach ($overlap as $sub) if (billing_subscription_access($sub,time()) || in_array($sub['status'],['active','past_due','unpaid','incomplete','trialing','paused'],true)) { $db->rollBack(); billing_fail(409, 'You already have overlapping access. Manage the existing subscription first.'); }
            $customer = billing_query('SELECT external_customer_id FROM plugin_customers WHERE account_id=? AND environment=?', [$account['id'],$config['environment']])->fetchColumn();
            if (!$customer) {
                $created = billing_stripe('POST','/customers',['email'=>$account['email'],'metadata'=>['oyonx_account'=>$account['id']]],'customer-'.$config['environment'].'-'.$account['id']);
                $customer = $created['id'];
                billing_query('INSERT INTO plugin_customers(account_id,environment,external_customer_id) VALUES(?,?,?)',[$account['id'],$config['environment'],$customer]);
            }
            $pending = billing_query('SELECT * FROM plugin_checkouts WHERE account_id=? AND environment=? AND expires_at>UTC_TIMESTAMP() ORDER BY expires_at DESC LIMIT 1',[$account['id'],$config['environment']])->fetch();
            if ($pending) {
                $session = billing_stripe('GET','/checkout/sessions/'.$pending['external_session_id']);
                if (($session['status']??'')==='open') {
                    $db->rollBack();
                    if ($pending['plan_code']!==$planCode) billing_fail(409,'Complete or cancel your existing checkout before choosing another plan.');
                    $result=['ok'=>true,'url'=>$session['url']]; break;
                }
                if (($session['status']??'')==='complete' && !billing_query("SELECT id FROM plugin_subscriptions WHERE account_id=? AND environment=? AND plan_code=? LIMIT 1",[$account['id'],$config['environment'],$pending['plan_code']])->fetchColumn()) { $db->rollBack(); billing_fail(409,'Your payment is being verified. Please refresh shortly.'); }
            }
            $attempt=billing_query('SELECT * FROM plugin_checkout_attempts WHERE account_id=? AND environment=? AND completed_at IS NULL ORDER BY created_at DESC LIMIT 1',[$account['id'],$config['environment']])->fetch();
            if ($attempt && $attempt['plan_code'] !== $planCode) { $db->rollBack(); billing_fail(409,'An earlier checkout is still being created. Retry that plan first.'); }
            if ($attempt && strtotime($attempt['created_at'].' UTC') < time()-3600) { $db->rollBack(); billing_fail(409,'A previous checkout needs support review before another can be created.'); }
            $checkout=$attempt['id']??billing_id();
            if (!$attempt) billing_query('INSERT INTO plugin_checkout_attempts(id,account_id,plan_code,environment) VALUES(?,?,?,?)',[$checkout,$account['id'],$planCode,$config['environment']]);
            // Commit the idempotency identity BEFORE requesting an external session.
            // A network timeout or PHP crash retries the same Stripe request.
            $db->commit();
            $session = billing_stripe('POST','/checkout/sessions',[
                'mode'=>'subscription','customer'=>$customer,'client_reference_id'=>$account['id'],
                'line_items'=>[['price'=>$plan['external_price_id'],'quantity'=>1]],
                'subscription_data'=>['metadata'=>['oyonx_account'=>$account['id'],'oyonx_plan'=>$planCode]],
                'success_url'=>'https://oyonx.com/account/?checkout=complete',
                'cancel_url'=>'https://oyonx.com/account/?checkout=cancelled'
            ],'checkout-'.$checkout);
            $db->beginTransaction();
            billing_query('SELECT id FROM plugin_accounts WHERE id=? FOR UPDATE',[$account['id']]);
            billing_query('INSERT INTO plugin_checkouts(id,account_id,plan_code,external_session_id,environment,expires_at) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE external_session_id=VALUES(external_session_id)',[$checkout,$account['id'],$planCode,$session['id'],$config['environment'],gmdate('Y-m-d H:i:s',$session['expires_at'])]);
            billing_query('UPDATE plugin_checkout_attempts SET completed_at=UTC_TIMESTAMP() WHERE id=?',[$checkout]);
            $db->commit(); $result=['ok'=>true,'url'=>$session['url']]; break;
        case 'portal':
            billing_rate('portal',10); $account=billing_account();
            $customer=billing_query('SELECT external_customer_id FROM plugin_customers WHERE account_id=? AND environment=?',[$account['id'],$config['environment']])->fetchColumn();
            if (!$customer) billing_fail(409,'No billing account exists yet.');
            $portal=billing_stripe('POST','/billing_portal/sessions',['customer'=>$customer,'return_url'=>'https://oyonx.com/account/']);
            $result=['ok'=>true,'url'=>$portal['url']]; break;
        default: billing_fail(404,'Unknown action.');
        }
    } else { header('Allow: GET, POST'); billing_fail(405,'Method not allowed.'); }
    echo json_encode($result);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log('Oyonx billing failure: ' . get_class($e));
    billing_fail(503,'Account services are temporarily unavailable. Please try later or contact info@oyonx.com.');
}
