<?php
declare(strict_types=1);
require dirname(__DIR__).'/server/billing/domain.php';
$n=0;
function expect($actual,$wanted,string $message):void{global $n;if($actual!==$wanted)throw new RuntimeException($message);$n++;}
$now=time();
foreach(['active','past_due']as $status)expect(billing_subscription_access(['status'=>$status,'access_until'=>gmdate('Y-m-d H:i:s',$now+60)],$now),true,'paid period');
foreach(['canceled','unpaid','incomplete','revoked','paused']as $status)expect(billing_subscription_access(['status'=>$status,'access_until'=>gmdate('Y-m-d H:i:s',$now+60)],$now),false,'inactive period');
expect(billing_subscription_access(['status'=>'active','access_until'=>gmdate('Y-m-d H:i:s',$now)],$now),false,'boundary expiry');
expect(billing_subscription_access(['status'=>'active'], $now),false,'unpaid no date');
$price=['id'=>'price_month','currency'=>'usd','unit_amount'=>200,'recurring'=>['interval'=>'month','interval_count'=>1]];
$plan=['currency'=>'USD','amount_minor'=>200,'interval_unit'=>'month'];
expect(billing_price_matches($price,$plan),true,'monthly price');
foreach(['currency'=>'aed','unit_amount'=>2]as $key=>$value){$bad=$price;$bad[$key]=$value;expect(billing_price_matches($bad,$plan),false,'price mismatch');}
$bad=$price;$bad['recurring']['interval']='year';expect(billing_price_matches($bad,$plan),false,'interval mismatch');
$line=['type'=>'subscription','price'=>['id'=>'price_month'],'period'=>['end'=>$now+60],'proration'=>false];
$invoice=['subscription'=>'sub_123','paid'=>true,'status'=>'paid','lines'=>['data'=>[$line],'has_more'=>false]];
expect(billing_paid_through($invoice,'sub_123','price_month'),$now+60,'paid monthly invoice');
expect(billing_paid_through($invoice,'sub_other','price_month'),null,'invoice ownership');
expect(billing_paid_through($invoice,'sub_123','price_other'),null,'invoice product');
foreach(['paid'=>false,'status'=>'open']as $key=>$value){$bad=$invoice;$bad[$key]=$value;expect(billing_paid_through($bad,'sub_123','price_month'),null,'unpaid invoice');}
$bad=$invoice;$bad['lines']['data'][0]['proration']=true;expect(billing_paid_through($bad,'sub_123','price_month'),null,'proration no full period');
$bad=$invoice;$bad['lines']['has_more']=true;try{billing_paid_through($bad,'sub_123','price_month');throw new RuntimeException('pagination accepted');}catch(RuntimeException $e){expect($e->getMessage(),'Invoice lines require pagination.','no partial invoice grants');}
expect(billing_plan_code('bundle','year',['fontstyle-pro']),'current-seven-year','year bundle');
expect(billing_plan_code('fontstyle-pro','month',['fontstyle-pro']),'fontstyle-pro-month','individual');
try{billing_plan_code('unknown','month',['fontstyle-pro']);throw new RuntimeException('bad product accepted');}catch(InvalidArgumentException $e){$n++;}
echo "Passed {$n} billing policy checks.\n";
