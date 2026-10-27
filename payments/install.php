<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(!in_array('--apply',$argv,true)){fwrite(STDERR,"Usage: php payments/install.php --apply\nBack up the target database before applying migrations.\n");exit(1);}
require dirname(__DIR__).'/server/billing/bootstrap.php';
foreach(['pdo_mysql','curl','openssl']as $extension)if(!extension_loaded($extension))throw new RuntimeException('Required PHP extension: '.$extension);
$db=billing_db();
if(!billing_query("SELECT GET_LOCK('oyonx-billing-migrations',10)")->fetchColumn())throw new RuntimeException('Another migration is running.');
try{
 $db->exec('CREATE TABLE IF NOT EXISTS plugin_schema_migrations (version VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
 foreach(glob(__DIR__.'/migrations/*.sql')as $path){
  $version=basename($path);$body=file_get_contents($path);$sha=hash('sha256',$body);
  $old=billing_query('SELECT sha256 FROM plugin_schema_migrations WHERE version=?',[$version])->fetchColumn();
  if($old){if(!hash_equals($old,$sha))throw new RuntimeException('Applied migration changed: '.$version);continue;}
  // MySQL DDL auto-commits. Never retry a partially applied migration blindly.
  $db->exec($body);billing_query('INSERT INTO plugin_schema_migrations(version,sha256) VALUES(?,?)',[$version,$sha]);
  echo 'Applied ',$version,"\n";
 }
}finally{billing_query("SELECT RELEASE_LOCK('oyonx-billing-migrations')");}
echo "Schema ready. Run seed-catalog.php; checkout stays inactive until price mapping and activation.\n";
