<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Content-Security-Policy: default-src \'none\'; frame-ancestors \'none\'');
function respond(array $body, int $status=200): never {http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function fail(string $error,int $status=400): never {respond(['ok'=>false,'error'=>$error],$status);}
try {
 $configFile=dirname(__DIR__).'/private/config.php';
 if(!is_file($configFile)) fail('Server noch nicht konfiguriert.',503);
 $cfg=require $configFile;
 if(empty($cfg['secret'])||strlen($cfg['secret'])<32||str_starts_with($cfg['secret'],'CHANGE_')) fail('Server nicht sicher konfiguriert.',503);
 $moderationFile=dirname(__DIR__).'/private/moderation.php';
 $moderation=is_file($moderationFile)?require $moderationFile: ['enabled'=>true,'warning_threshold'=>0.10,'hold_threshold'=>0.30,'confirmations_required'=>2];
 if(!is_array($moderation)) fail('Moderationskonfiguration ungültig.',503);
 $moderationEnabled=(bool)($moderation['enabled']??true);
 $confirmationsRequired=max(1,min(5,(int)($moderation['confirmations_required']??2)));
 $warningThreshold=max(0.01,min(1.0,(float)($moderation['warning_threshold']??0.10)));
 $holdThreshold=max($warningThreshold,min(3.0,(float)($moderation['hold_threshold']??0.30)));
 $db=new PDO($cfg['dsn'],$cfg['user'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
} catch(Throwable $e){error_log('TP-Radar init: '.$e->getMessage());fail('Datenbank derzeit nicht erreichbar.',503);}
function query(PDO $db,string $sql,array $args=[]): PDOStatement {$s=$db->prepare($sql);$s->execute($args);return $s;}
function input(): array { $raw=file_get_contents('php://input');if(strlen($raw)>10000)fail('Anfrage zu groß.',413);$json=json_decode($raw,true);if(!is_array($json))fail('Ungültiges JSON.');return $json; }
function strfield(mixed $v,int $max,string $name):string {if(!is_string($v))fail("$name fehlt.");$v=trim(preg_replace('/\s+/u',' ',$v));if($v===''||mb_strlen($v)>$max)fail("$name ungültig.");return $v;}
function intfield(mixed $v,int $min,int $max,string $name):int {if(!is_int($v)&&!(is_string($v)&&preg_match('/^\d+$/D',$v)))fail("$name ungültig.");$n=filter_var($v,FILTER_VALIDATE_INT);if($n===false||$n<$min||$n>$max)fail("$name außerhalb der Grenzen.");return $n;}
function validDate(mixed $v):string {if(!is_string($v)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v))fail('Datum ungültig.');$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v,new DateTimeZone('Europe/Berlin'));if(!$d||$d->format('Y-m-d')!==$v)fail('Datum ungültig.');$today=new DateTimeImmutable('today',new DateTimeZone('Europe/Berlin'));if($d>$today||$d<$today->modify('-60 days'))fail('Datum darf nicht in der Zukunft oder mehr als 60 Tage zurückliegen.');return $v;}
function cents(mixed $v):int {if(!is_string($v)&&!is_int($v)&&!is_float($v))fail('Preis ungültig.');$s=str_replace(',','.',(string)$v);if(!preg_match('/^\d{1,4}(?:\.\d{1,2})?$/D',$s))fail('Preis ungültig.');$parts=explode('.',$s);$n=(int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');if($n<1||$n>999999)fail('Preis außerhalb der Grenzen.');return $n;}
function ipActor(array $cfg):string {
 return hash_hmac('sha256','ip:'.($_SERVER['REMOTE_ADDR']??'unknown'),$cfg['secret']);
}
// Ein zufaelliges, signiertes Cookie erkennt Browserinstallationen statt nur IP-Adressen.
// Achtung: ohne Login ist echte Unabhaengigkeit NICHT beweisbar (Cookies loeschbar).
function deviceActor(array $cfg):string {
 $name='tp_radar_device';$raw=$_COOKIE[$name]??'';$id=null;
 if(is_string($raw)&&preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/D',$raw,$m)) {
  $expected=hash_hmac('sha256','device:'.$m[1],$cfg['secret']);
  if(hash_equals($expected,$m[2]))$id=$m[1];
 }
 if($id===null){
  $id=bin2hex(random_bytes(16));
  $token=$id.'.'.hash_hmac('sha256','device:'.$id,$cfg['secret']);
  setcookie($name,$token,['expires'=>time()+31536000,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
 }
 return hash_hmac('sha256','reporter:'.$id,$cfg['secret']);
}
function throttle(PDO $db,string $actor,string $bucket,int $limit,int $seconds):void {
 $db->beginTransaction();
 try {
  $row=query($db,'SELECT requests,window_start FROM request_limits WHERE actor_hash=? AND bucket=? FOR UPDATE',[$actor,$bucket])->fetch();
  $now=time();$fresh=!$row||strtotime($row['window_start'])<=$now-$seconds;
  if($fresh){query($db,'INSERT INTO request_limits(actor_hash,bucket,window_start,requests) VALUES(?,?,NOW(),1) ON DUPLICATE KEY UPDATE window_start=NOW(),requests=1',[$actor,$bucket]);}
  else {if((int)$row['requests']>=$limit){$db->rollBack();fail('Zu viele Anfragen. Bitte später erneut versuchen.',429);}query($db,'UPDATE request_limits SET requests=requests+1 WHERE actor_hash=? AND bucket=?',[$actor,$bucket]);}
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function priceRows(PDO $db):array {
 return query($db,"SELECT pr.id,pr.product_id AS productId,s.name AS store,pr.price_cents/100 AS price,pr.observed_on AS date,pr.status,pr.created_at AS createdAt FROM prices pr JOIN stores s ON s.id=pr.store_id JOIN products p ON p.id=pr.product_id WHERE p.is_active=1 AND s.is_active=1 AND pr.status='confirmed' AND pr.observed_on >= CURRENT_DATE - INTERVAL 60 DAY ORDER BY pr.observed_on DESC,pr.id DESC LIMIT 5000")->fetchAll();
}
$action=$_GET['action']??'list';$method=$_SERVER['REQUEST_METHOD']??'GET';$ipActor=ipActor($cfg);$actor=deviceActor($cfg);
try {
 if($action==='list'&&$method==='GET'){
  throttle($db,$ipActor,'read',180,60);
  $products=query($db,'SELECT id,name,ean,rolls,sheets,layers FROM products WHERE is_active=1 ORDER BY name LIMIT 2000')->fetchAll();
  $pending=query($db,"SELECT pr.id,pr.product_id AS productId,p.name AS productName,s.name AS store,pr.price_cents/100 AS price,pr.observed_on AS date,pr.reason, (SELECT COUNT(*) FROM confirmations c WHERE c.price_id=pr.id) AS confirmations FROM prices pr JOIN products p ON p.id=pr.product_id JOIN stores s ON s.id=pr.store_id WHERE p.is_active=1 AND s.is_active=1 AND pr.status='pending' AND pr.observed_on >= CURRENT_DATE - INTERVAL 30 DAY ORDER BY pr.created_at DESC LIMIT 40")->fetchAll();
  respond(['ok'=>true,'products'=>$products,'prices'=>priceRows($db),'pending'=>$pending,'validDays'=>30,'moderationEnabled'=>$moderationEnabled,'confirmationsRequired'=>$confirmationsRequired]);
 }
 if($method!=='POST')fail('Methode nicht erlaubt.',405);
 if(!empty($_SERVER['HTTP_ORIGIN'])) {
  $expected=rtrim((string)($cfg['site_origin']??''),'/');
  if($expected==='' || !filter_var($expected,FILTER_VALIDATE_URL) || parse_url($expected,PHP_URL_SCHEME)!=='https') fail('site_origin muss in private/config.php konfiguriert sein.',503);
  if (!hash_equals($expected,(string)$_SERVER['HTTP_ORIGIN'])) fail('Fremde Herkunft nicht erlaubt.',403);
 }
 $fetchSite=$_SERVER['HTTP_SEC_FETCH_SITE']??'';
 if($fetchSite!==''&&!in_array($fetchSite,['same-origin','none'],true))fail('Fremde Herkunft nicht erlaubt.',403);
 $data=input();throttle($db,$ipActor,'write',12,3600);
 if($action==='add'){
  $store=strfield($data['store']??null,80,'Geschäft');$normalized=mb_strtolower($store);
  $date=validDate($data['date']??null);$price=cents($data['price']??null);
  $id=isset($data['productId'])?intfield($data['productId'],1,PHP_INT_MAX,'Produkt'):null;
  $p=$data['product']??null;
  if($id===null){
   if(!is_array($p))fail('Produkt fehlt.');
   $name=strfield($p['name']??null,120,'Produktname');$rolls=intfield($p['rolls']??null,1,500,'Rollen');$sheets=intfield($p['sheets']??null,1,2000,'Blätter');$layers=intfield($p['layers']??null,1,10,'Lagen');
   $ean=$p['ean']??null;if($ean==='')$ean=null;if($ean!==null&&(!is_string($ean)||!preg_match('/^\d{8,14}$/D',$ean)))fail('EAN ungültig.');
  }
  $db->beginTransaction();
  try {
   if($id===null){
    if($ean!==null){$existing=query($db,'SELECT id,rolls,sheets,layers FROM products WHERE ean=? AND is_active=1',[$ean])->fetch();if($existing){if((int)$existing['rolls']!==$rolls||(int)$existing['sheets']!==$sheets||(int)$existing['layers']!==$layers)fail('EAN existiert mit anderen Produktdaten. Bitte vorhandenes Produkt verwenden.');$id=(int)$existing['id'];}}
    if($id===null){query($db,'INSERT INTO products(name,ean,rolls,sheets,layers) VALUES(?,?,?,?,?)',[$name,$ean,$rolls,$sheets,$layers]);$id=(int)$db->lastInsertId();}
   }
   if(!query($db,'SELECT id FROM products WHERE id=? AND is_active=1',[$id])->fetch())fail('Produkt nicht gefunden.');
   query($db,'INSERT INTO stores(name,normalized_name) VALUES(?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',[$store,$normalized]);$storeId=(int)$db->lastInsertId();
   if(!query($db,'SELECT id FROM stores WHERE id=? AND is_active=1',[$storeId])->fetch())fail('Dieses Geschäft ist deaktiviert.');
   $duplicate=query($db,'SELECT id FROM prices WHERE product_id=? AND store_id=? AND price_cents=? AND observed_on=? AND reporter_hash=? AND created_at > NOW() - INTERVAL 24 HOUR LIMIT 1',[$id,$storeId,$price,$date,$actor])->fetch();
   if($duplicate)fail('Diesen Preis hast du bereits eingetragen.');
   $baseline=query($db,"SELECT price_cents FROM prices WHERE product_id=? AND store_id=? AND status='confirmed' AND observed_on >= CURRENT_DATE - INTERVAL 30 DAY ORDER BY observed_on DESC,id DESC LIMIT 1",[$id,$storeId])->fetchColumn();
   // Ohne bestaetigten Vergleichspreis wird der erste valide Preis direkt veroeffentlicht.
   $deviation=$baseline!==false?abs($price/(int)$baseline-1):null;
   $isPending=$moderationEnabled && $deviation!==null && $deviation>$holdThreshold;
   $status=$isPending?'pending':'confirmed';
   if($moderationEnabled&&$deviation!==null&&$deviation>$warningThreshold&&empty($data['ackDeviation']))fail('Preis weicht mehr als 10 % vom letzten bestätigten Preis ab. Bitte Abweichung bestätigen.');
   $reason=$isPending?'Ungewöhnliche Preisabweichung':null;
   query($db,'INSERT INTO prices(product_id,store_id,price_cents,observed_on,status,reason,reporter_hash) VALUES(?,?,?,?,?,?,?)',[$id,$storeId,$price,$date,$status,$reason,$actor]);$newId=(int)$db->lastInsertId();
   $db->commit();respond(['ok'=>true,'id'=>$newId,'status'=>$status,'message'=>$isPending?'Preis wurde gespeichert und wartet auf eine unabhängige Bestätigung.':'Preis wurde veröffentlicht.']);
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
 if($action==='confirm'){
  $id=intfield($data['id']??null,1,PHP_INT_MAX,'Meldung');
  $db->beginTransaction();
  try {
   $item=query($db,'SELECT id,status,reporter_hash FROM prices WHERE id=? FOR UPDATE',[$id])->fetch();if(!$item||$item['status']!=='pending')fail('Diese Meldung kann nicht bestätigt werden.');
   if(hash_equals($item['reporter_hash'],$actor))fail('Eigene Meldungen können nicht bestätigt werden.');
   $insert=query($db,'INSERT IGNORE INTO confirmations(price_id,reporter_hash) VALUES(?,?)',[$id,$actor]);
   $count=(int)query($db,'SELECT COUNT(*) FROM confirmations WHERE price_id=?',[$id])->fetchColumn();
   if($count >= $confirmationsRequired)query($db,"UPDATE prices SET status='confirmed',reason=NULL WHERE id=? AND status='pending'",[$id]);
   $db->commit();
   $published=$count >= $confirmationsRequired;
   $message=$published?'Preis bestätigt und veröffentlicht.':($insert->rowCount()===0?'Dieses Gerät hat bereits bestätigt.':'Bestätigung gespeichert. Noch '.($confirmationsRequired-$count).' weitere benötigt.');
   respond(['ok'=>true,'status'=>$published?'confirmed':'pending','confirmations'=>$count,'required'=>$confirmationsRequired,'message'=>$message]);
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
 fail('Unbekannte Aktion.',404);
}catch(PDOException $e){error_log('TP Radar SQL: '.$e->getMessage());fail('Datenbankfehler. Bitte später erneut versuchen.',503);}catch(Throwable $e){error_log('TP Radar: '.$e->getMessage());fail('Serverfehler. Bitte später erneut versuchen.',500);}
