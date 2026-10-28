<?php
declare(strict_types=1);
// Run only against the disposable Docker MySQL database; never production.
require dirname(__DIR__).'/server/billing/bootstrap.php';
$config=billing_config();
if($config['environment']!=='test'||strpos($config['dsn'],'host=oyonx-billing-db;')===false)throw new RuntimeException('Disposable database required.');
$checks=0;
function same($actual,$expected,string $label): void {global $checks;if($actual!==$expected)throw new RuntimeException($label.' expected '.json_encode($expected).' got '.json_encode($actual));$checks++;}
function http(string $action,?array $data=null,array $headers=[],?string &$cookie=null): array {
 $url='http://127.0.0.1/api/billing.php?action='.rawurlencode($action);
 $c=curl_init($url);$head=[];$headers[]='Connection: close';
 if($cookie)$headers[]='Cookie: '.$cookie;
 if($data!==null){$headers[]='Content-Type: application/json';curl_setopt($c,CURLOPT_POSTFIELDS,json_encode((object)$data));}
 curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>15,CURLOPT_HEADERFUNCTION=>function($c,$line)use(&$head,&$cookie){$head[]=$line;if(preg_match('/^Set-Cookie: (oyonx_account=[^;]+)/i',$line,$m))$cookie=$m[1];return strlen($line);}]);
 $body=curl_exec($c);$status=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);$json=json_decode((string)$body,true);
 if(!is_array($json))throw new RuntimeException('Non-JSON response: '.$status.' '.$body);
 return [$status,$json,implode('',$head)];
}
$products=json_decode(file_get_contents(dirname(__DIR__).'/scripts/plugins/products.json'),true);
// Clear test accounts only, using a unique namespace each run.
$accounts=[];$tokens=[];$password='Test-only-password-391';
for($i=0;$i<3;$i++){
 $id=billing_id();$email='test-'.bin2hex(random_bytes(6)).'@example.invalid';
 billing_query('INSERT INTO plugin_accounts(id,email,password_hash,email_verified_at,session_version) VALUES(?,?,?,?,?)',[$id,$email,password_hash($password,PASSWORD_DEFAULT),$i===2?null:gmdate('Y-m-d H:i:s'),bin2hex(random_bytes(32))]);
 $accounts[]=['id'=>$id,'email'=>$email];$tokens[]=billing_token($id,'plugin',3600);
}
billing_query('DELETE FROM plugin_rate_limits');
billing_query('UPDATE plugin_products SET active=1');
try {
 $cookie=null;[$code,$session,$headers]=http('session',null,[],$cookie);same($code,200,'session');same($session['account'],null,'anonymous');same(stripos($headers,'HttpOnly')!==false,true,'HttpOnly');same(stripos($headers,'secure')!==false,true,'secure');
 [$code]=http('login',['email'=>$accounts[0]['email'],'password'=>$password],[],$cookie);same($code,403,'CSRF required');
 [$code]=http('login',['email'=>$accounts[0]['email'],'password'=>$password],['X-CSRF-Token: '.$session['csrf'],'Origin: https://evil.example'],$cookie);same($code,403,'cross-site rejected');
 [$code]=http('login',['email'=>$accounts[0]['email'],'password'=>$password],['X-CSRF-Token: '.$session['csrf']],$cookie);same($code,200,'login');
 [$code,$session]=http('session',null,[],$cookie);same($session['account']['email'],$accounts[0]['email'],'identity');
 [$code,$access]=http('access',null,[],$cookie);same($code,200,'access');same(count($access['products']),7,'seven products');same($access['products'][0]['freeUsesRemaining'],5,'five free uses');
 $product='fontstyle-pro';$headers=['Authorization: Bearer '.$tokens[0],'Origin: null'];$first=bin2hex(random_bytes(32));
 [$code,$reservation,$cors]=http('use',['product'=>$product,'requestKey'=>$first],$headers);same($code,200,'reserve first');same($reservation['source'],'trial','trial source');same(stripos($cors,'Access-Control-Allow-Origin: null')!==false,true,'plugin CORS');
 [$code,$retry]=http('use',['product'=>$product,'requestKey'=>$first],$headers);same($code,200,'idempotent reserve');same($retry['replayed'],true,'same reservation');
 [$code]=http('use',['product'=>'colorstyle-pro','requestKey'=>$first],$headers);same($code,409,'request bound to product');
 [$code]=http('use-complete',['product'=>$product,'requestKey'=>$first],$headers);same($code,200,'complete');
 [$code]=http('use-complete',['product'=>$product,'requestKey'=>$first],$headers);same($code,200,'idempotent complete');
 [$code]=http('use',['product'=>$product,'requestKey'=>$first],$headers);same($code,409,'completed use not replayable');
 $cancelKey=bin2hex(random_bytes(32));http('use',['product'=>$product,'requestKey'=>$cancelKey],$headers);
 [$code]=http('use-cancel',['product'=>$product,'requestKey'=>$cancelKey],$headers);same($code,200,'cancel failed action');
 same((int)billing_query('SELECT uses_consumed FROM plugin_trial_usage WHERE account_id=? AND product_slug=?',[$accounts[0]['id'],$product])->fetchColumn(),1,'cancel does not charge');
 $keys=[];for($i=0;$i<4;$i++){$key=bin2hex(random_bytes(32));$keys[]=$key;[$code]=http('use',['product'=>$product,'requestKey'=>$key],$headers);same($code,200,'remaining reservation');}
 [$code]=http('use',['product'=>$product,'requestKey'=>bin2hex(random_bytes(32))],$headers);same($code,402,'reservation cap prevents parallel overspend');
 foreach($keys as $key)http('use-complete',['product'=>$product,'requestKey'=>$key],$headers);
 same((int)billing_query('SELECT uses_consumed FROM plugin_trial_usage WHERE account_id=? AND product_slug=?',[$accounts[0]['id'],$product])->fetchColumn(),5,'exactly five uses');
 [$code]=http('use',['product'=>$product,'requestKey'=>bin2hex(random_bytes(32))],$headers);same($code,402,'sixth rejected');
 [$code]=http('use',['product'=>$product,'requestKey'=>bin2hex(random_bytes(32))],['Authorization: Bearer '.$tokens[1]]);same($code,200,'separate account separate allowance');
 [$code]=http('use',['product'=>'colorstyle-pro','requestKey'=>bin2hex(random_bytes(32))],$headers);same($code,200,'separate product separate allowance');
 [$code]=http('access',null,['Authorization: Bearer '.$tokens[2]]);same($code,403,'unverified blocked');
 [$code]=http('plugin-token',[],array_merge($headers,['X-CSRF-Token: '.$session['csrf']]),$cookie);same($code,403,'plugin cannot issue tokens');
 [$code]=http('access',null,['Authorization: Bearer '.str_repeat('1',64)]);same($code,401,'invalid token');
 // Ten simultaneous requests against a fresh product must reserve exactly five uses.
 $multi=curl_multi_init();$handles=[];
 for($n=0;$n<10;$n++){
  $h=curl_init('http://127.0.0.1/api/billing.php?action=use');
  curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POSTFIELDS=>json_encode(['product'=>'mindmap-pro','requestKey'=>bin2hex(random_bytes(32))]),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$tokens[1]],CURLOPT_TIMEOUT=>20]);
  curl_multi_add_handle($multi,$h);$handles[]=$h;
 }
 do{$status=curl_multi_exec($multi,$running);if($running)curl_multi_select($multi,1);}while($running&&$status===CURLM_OK);
 $codes=[];foreach($handles as $h){$codes[]=curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_multi_remove_handle($multi,$h);curl_close($h);}curl_multi_close($multi);
 same(count(array_filter($codes,fn($c)=>$c===200)),5,'concurrent requests limited to five');
 same(count(array_filter($codes,fn($c)=>$c===402)),5,'concurrent overflow denied');
 // Monthly individual: just one product, future paid-through timestamp.
 $sub=billing_id();billing_query("INSERT INTO plugin_subscriptions(id,account_id,plan_code,provider,environment,external_subscription_id,status,access_until) VALUES(?,?,?,'stripe','test',?,'active',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 MONTH))",[$sub,$accounts[0]['id'],$product.'-month','sub_local_'.bin2hex(random_bytes(6))]);
 [$code,$paid]=http('use',['product'=>$product,'requestKey'=>bin2hex(random_bytes(32))],$headers);same($code,200,'paid bypasses exhausted trial');same($paid['source'],'paid','paid source');
 billing_query("UPDATE plugin_subscriptions SET access_until=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?",[$sub]);
 [$code]=http('use',['product'=>$product,'requestKey'=>bin2hex(random_bytes(32))],$headers);same($code,402,'expired blocked');
 billing_query("UPDATE plugin_subscriptions SET plan_code='current-seven-year',access_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 YEAR),cancel_at_period_end=1 WHERE id=?",[$sub]);
 [$code,$access]=http('access',null,$headers);same(count(array_filter($access['products'],fn($p)=>$p['paid'])),7,'yearly bundle covers seven');
 billing_query("UPDATE plugin_subscriptions SET status='revoked' WHERE id=?",[$sub]);
 [$code,$access]=http('access',null,$headers);same(count(array_filter($access['products'],fn($p)=>$p['paid'])),0,'refund revocation');
 // Reset invalidates all sessions and plugin credentials.
 $reset=billing_token($accounts[0]['id'],'reset',300);
 [$code]=http('reset',['token'=>$reset,'password'=>'New-test-password-392'],['X-CSRF-Token: '.$session['csrf']],$cookie);same($code,200,'password reset');
 [$code]=http('access',null,$headers);same($code,401,'reset revokes plugin token');
 [$code]=http('reset',['token'=>$reset,'password'=>'New-test-password-392'],['X-CSRF-Token: '.$session['csrf']],$cookie);same(in_array($code,[400,403],true),true,'reset single use');
 // Signature tests do not call an external provider.
 $body='{"id":"evt_test"}';$now=time();$secret='whsec_unit';$sig=hash_hmac('sha256',$now.'.'.$body,$secret);
 same(billing_signature_valid($body,'t='.$now.',v1='.$sig,$secret,$now),true,'valid webhook');
 same(billing_signature_valid($body.'x','t='.$now.',v1='.$sig,$secret,$now),false,'tampered webhook');
 same(billing_signature_valid($body,'t='.$now.',v1='.$sig,$secret,$now+301),false,'stale webhook');
 same(billing_signature_valid($body,'t='.$now.',v1='.$sig,'',$now),false,'missing webhook secret');
 echo "Passed {$checks} billing database/API assertions. No real payments or emails sent.\n";
} finally {
 foreach($accounts as $a){foreach(['plugin_usage_events','plugin_trial_usage','plugin_subscriptions','plugin_checkouts','plugin_customers','plugin_account_tokens']as $table)billing_query('DELETE FROM '.$table.' WHERE account_id=?',[$a['id']]);billing_query('DELETE FROM plugin_accounts WHERE id=?',[$a['id']]);}
 billing_query('UPDATE plugin_products SET active=0');
}
