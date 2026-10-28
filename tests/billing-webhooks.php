<?php
declare(strict_types=1);
require dirname(__DIR__).'/server/billing/webhooks.php';
$c=billing_config();if($c['environment']!=='test'||strpos($c['dsn'],'host=oyonx-billing-db;')===false)throw new RuntimeException('Disposable database required.');
$n=0;function expect($actual,$wanted,string $message):void{global $n;if($actual!==$wanted)throw new RuntimeException($message.' '.json_encode([$actual,$wanted]));$n++;}
$id=billing_id();$run=bin2hex(random_bytes(6));$customer='cus_'.$run;$subId='sub_'.$run;$priceId='price_'.$run;$eventPrefix='evt_'.$run;$now=time();
billing_query('INSERT INTO plugin_accounts(id,email,password_hash,email_verified_at) VALUES(?,?,?,UTC_TIMESTAMP())',[$id,$run.'@example.invalid',password_hash('test-password-only',PASSWORD_DEFAULT)]);
billing_query("INSERT INTO plugin_customers(account_id,environment,external_customer_id) VALUES(?,'test',?)",[$id,$customer]);
$providerState=[];$provider=function($method,$path)use(&$providerState){if(!array_key_exists($path,$providerState))throw new RuntimeException('Unexpected provider request: '.$path);return $providerState[$path];};
$event=function(string $type,array $object,string $suffix)use($eventPrefix){return ['id'=>$eventPrefix.$suffix,'type'=>$type,'livemode'=>false,'data'=>['object'=>$object]];};
$stored=function()use($subId){return billing_query("SELECT * FROM plugin_subscriptions WHERE external_subscription_id=?",[$subId])->fetch();};
$price=['id'=>$priceId,'currency'=>'usd','unit_amount'=>200,'recurring'=>['interval'=>'month','interval_count'=>1]];
$sub=['id'=>$subId,'customer'=>$customer,'metadata'=>['oyonx_account'=>$id,'oyonx_plan'=>'fontstyle-pro-month'],'items'=>['data'=>[['price'=>$price,'quantity'=>1]]],'status'=>'incomplete','current_period_end'=>$now+2592000,'latest_invoice'=>'in_first','cancel_at_period_end'=>false];
$invoice=['id'=>'in_first','subscription'=>$subId,'paid'=>false,'status'=>'open','lines'=>['has_more'=>false,'data'=>[['type'=>'subscription','price'=>['id'=>$priceId],'period'=>['end'=>$now+2592000],'proration'=>false]]]];
try{
 billing_query("INSERT INTO plugin_provider_prices(provider,environment,external_price_id,plan_code) VALUES('stripe','test',?,'fontstyle-pro-month')",[$priceId]);
 $providerState['/subscriptions/'.$subId]=$sub;$providerState['/invoices/in_first']=$invoice;
 $e=$event('checkout.session.completed',['subscription'=>$subId],'1');billing_apply_stripe_event($e,$provider);expect($stored()['access_until'],null,'unpaid checkout does not grant');
 $sub['status']='active';$invoice['paid']=true;$invoice['status']='paid';$providerState['/subscriptions/'.$subId]=$sub;$providerState['/invoices/in_first']=$invoice;
 $paid=$event('invoice.paid',['subscription'=>$subId],'2');billing_apply_stripe_event($paid,$provider);expect($stored()['access_until'],gmdate('Y-m-d H:i:s',$now+2592000),'paid monthly grant');
 $before=$stored();billing_apply_stripe_event($paid,$provider);expect($stored(),$before,'duplicate event no mutation');
 // Provider period advances but invoice is not paid: no extension.
 $sub['status']='past_due';$sub['current_period_end']=$now+5184000;$sub['latest_invoice']='in_renewal';$invoice['id']='in_renewal';$invoice['paid']=false;$invoice['status']='open';$invoice['lines']['data'][0]['period']['end']=$now+5184000;
 $providerState['/subscriptions/'.$subId]=$sub;$providerState['/invoices/in_renewal']=$invoice;
 billing_apply_stripe_event($event('invoice.payment_failed',['subscription'=>$subId],'3'),$provider);expect($stored()['access_until'],$before['access_until'],'failed renewal no extension');
 $sub['status']='active';$invoice['paid']=true;$invoice['status']='paid';$providerState['/subscriptions/'.$subId]=$sub;$providerState['/invoices/in_renewal']=$invoice;
 billing_apply_stripe_event($event('invoice.paid',['subscription'=>$subId],'4'),$provider);expect($stored()['access_until'],gmdate('Y-m-d H:i:s',$now+5184000),'paid renewal extends');
 $sub['cancel_at_period_end']=true;$providerState['/subscriptions/'.$subId]=$sub;
 billing_apply_stripe_event($event('customer.subscription.updated',['id'=>$subId],'5'),$provider);expect((int)$stored()['cancel_at_period_end'],1,'scheduled cancellation');expect(billing_subscription_access($stored(),$now),true,'paid period retained');
 // Historical event with current provider state cannot regress it.
 billing_apply_stripe_event($event('customer.subscription.created',['id'=>$subId,'status'=>'incomplete'],'6'),$provider);expect($stored()['status'],'active','old event uses latest provider status');
 $providerState['/invoices/in_first']['paid']=true;$providerState['/invoices/in_first']['status']='paid';
 billing_apply_stripe_event($event('charge.refunded',['invoice'=>'in_first'],'7'),$provider);expect($stored()['status'],'active','historical refund preserves newer period');
 billing_apply_stripe_event($event('charge.refunded',['invoice'=>'in_renewal'],'8'),$provider);expect($stored()['status'],'revoked','current invoice refund revokes');
 billing_apply_stripe_event($event('customer.subscription.updated',['id'=>$subId],'9'),$provider);expect($stored()['status'],'revoked','later update cannot undo refund');
 // A newly paid subsequent period can restore access.
 $sub['latest_invoice']='in_next';$sub['cancel_at_period_end']=false;$invoice['id']='in_next';$invoice['lines']['data'][0]['period']['end']=$now+7776000;$providerState['/subscriptions/'.$subId]=$sub;$providerState['/invoices/in_next']=$invoice;
 billing_apply_stripe_event($event('invoice.paid',['subscription'=>$subId],'10'),$provider);expect($stored()['status'],'active','new paid period restores');
 $sub['status']='canceled';$providerState['/subscriptions/'.$subId]=$sub;
 billing_apply_stripe_event($event('customer.subscription.deleted',['id'=>$subId],'11'),$provider);expect(billing_subscription_access($stored(),$now),false,'ended cancellation revokes');
 // Yearly bundle mapping is explicit, no arbitrary invoice upgrades.
 $yearPrice='price_year_'.$run;billing_query("INSERT INTO plugin_provider_prices(provider,environment,external_price_id,plan_code) VALUES('stripe','test',?,'current-seven-year')",[$yearPrice]);
 $sub['status']='active';$sub['metadata']['oyonx_plan']='current-seven-year';$sub['items']['data'][0]['price']=['id'=>$yearPrice,'currency'=>'usd','unit_amount'=>18000,'recurring'=>['interval'=>'year','interval_count'=>1]];$sub['latest_invoice']='in_year';$invoice['id']='in_year';$invoice['lines']['data'][0]['price']['id']=$yearPrice;$invoice['lines']['data'][0]['period']['end']=$now+31536000;$providerState['/subscriptions/'.$subId]=$sub;$providerState['/invoices/in_year']=$invoice;
 // Existing subscription cannot silently change plans: reject portal upgrade until explicit plan-change flow exists.
 try{billing_apply_stripe_event($event('invoice.paid',['subscription'=>$subId],'12'),$provider);throw new RuntimeException('plan mutation accepted');}catch(RuntimeException $err){expect($err->getMessage(),'Subscription plan cannot change implicitly.','prevent plan remap');}
 // Foreign subscription is ignored; do not break other Oyonx merchant products.
 $sub['metadata']=[];$providerState['/subscriptions/'.$subId]=$sub;
 expect(billing_apply_stripe_event($event('customer.subscription.updated',['id'=>$subId],'13'),$provider)['ignored'],true,'unrelated subscription ignored');
 echo "Passed {$n} subscription webhook lifecycle checks using provider fixtures, not Stripe sandbox.\n";
}finally{
 billing_query('DELETE FROM plugin_webhook_events WHERE external_event_id LIKE ?',[$eventPrefix.'%']);
 billing_query('DELETE FROM plugin_subscriptions WHERE account_id=?',[$id]);billing_query('DELETE FROM plugin_customers WHERE account_id=?',[$id]);billing_query('DELETE FROM plugin_accounts WHERE id=?',[$id]);billing_query("DELETE FROM plugin_provider_prices WHERE external_price_id IN (?,?)",[$priceId,'price_year_'.$run]);
}
