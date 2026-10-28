<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/server/billing/bootstrap.php';
$config=billing_config();
if($config['environment']!=='test')throw new RuntimeException('This setup command provisions sandbox prices only. Live prices require a separately reviewed activation.');
if(!in_array('--provision-test-prices',$argv,true)){echo "Usage: php payments/setup-stripe.php --provision-test-prices\n";exit(1);}
$plans=billing_query('SELECT * FROM plugin_plans ORDER BY code')->fetchAll();
foreach($plans as $plan){
 $lookup='oyonx-'.$plan['code'].'-v1';
 $existing=billing_stripe('GET','/prices?lookup_keys[]='.$lookup.'&limit=2');
 if(count($existing['data']??[])>1)throw new RuntimeException('Ambiguous sandbox price.');
 $price=$existing['data'][0]??null;
 if(!$price){
  $group=preg_replace('/-(month|year)$/','',$plan['code']);
  $product=billing_stripe('POST','/products',['name'=>$group==='current-seven'?'Oyonx current seven bundle':$group,'metadata'=>['oyonx_group'=>$group]],'oyonx-product-'.$group.'-v1');
  $price=billing_stripe('POST','/prices',['currency'=>strtolower($plan['currency']),'unit_amount'=>(int)$plan['amount_minor'],'recurring'=>['interval'=>$plan['interval_unit']],'product'=>$product['id'],'lookup_key'=>$lookup,'metadata'=>['oyonx_plan'=>$plan['code']]],'oyonx-price-'.$plan['code'].'-v1');
 }
 if(empty($price['active'])||!empty($price['livemode']))throw new RuntimeException('An active sandbox price is required.');
 if(!billing_price_matches($price,$plan))throw new RuntimeException('Provider price mismatch.');
 billing_query("INSERT INTO plugin_provider_prices(provider,environment,external_price_id,plan_code) VALUES('stripe','test',?,?) ON DUPLICATE KEY UPDATE plan_code=VALUES(plan_code)",[$price['id'],$plan['code']]);
 echo 'Mapped sandbox price for ',$plan['code'],"\n";
}
echo "Plans remain inactive. Verify webhook configuration, cancellation-only billing portal, email and all seven plugin releases before activation.\n";
