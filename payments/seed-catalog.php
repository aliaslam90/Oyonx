<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/server/billing/bootstrap.php';
$catalog=json_decode(file_get_contents(__DIR__.'/catalog.json'),true);
$products=json_decode(file_get_contents(dirname(__DIR__).'/scripts/plugins/products.json'),true);
// Showcase additions do not automatically become billable products.
$products=array_values(array_filter($products,fn($product)=>in_array($product['slug'],$catalog['bundle']['productSlugs'],true)));
$db=billing_db();$db->beginTransaction();
try {
 foreach($products as $product) {
  billing_query('INSERT INTO plugin_products(slug,name,active) VALUES(?,?,0) ON DUPLICATE KEY UPDATE name=VALUES(name)',[$product['slug'],$product['name']]);
  foreach(['month'=>200,'year'=>2400]as $interval=>$amount){$code=$product['slug'].'-'.$interval;
   billing_query('INSERT INTO plugin_plans(code,currency,amount_minor,interval_unit,active) VALUES(?,?,?,?,0) ON DUPLICATE KEY UPDATE amount_minor=VALUES(amount_minor)',[$code,'USD',$amount,$interval]);
   billing_query('INSERT IGNORE INTO plugin_plan_products(plan_code,product_slug) VALUES(?,?)',[$code,$product['slug']]);
  }
 }
 foreach(['month'=>1500,'year'=>18000]as $interval=>$amount){$code='current-seven-'.$interval;
  billing_query('INSERT INTO plugin_plans(code,currency,amount_minor,interval_unit,active) VALUES(?,?,?,?,0) ON DUPLICATE KEY UPDATE amount_minor=VALUES(amount_minor)',[$code,'USD',$amount,$interval]);
  foreach($catalog['bundle']['productSlugs']as $slug)billing_query('INSERT IGNORE INTO plugin_plan_products(plan_code,product_slug) VALUES(?,?)',[$code,$slug]);
 }
 $db->commit();echo "Seeded inactive catalogue. Map test prices and complete end-to-end tests before activation.\n";
}catch(Throwable $e){$db->rollBack();throw $e;}
