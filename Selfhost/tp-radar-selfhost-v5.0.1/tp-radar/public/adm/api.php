<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
function out(array $v,int $code=200):never {http_response_code($code);echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function err(string $v,int $code=400):never {out(['ok'=>false,'error'=>$v],$code);}
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')err('HTTPS ist für den Adminbereich erforderlich.',403);
$root=dirname(__DIR__,2);
try {
 $cf=$root.'/private/config.php';$af=$root.'/private/admin.php';
 if(!is_file($cf)||!is_file($af))err('Adminzugang noch nicht eingerichtet.',503);
 $cfg=require $cf;$adm=require $af;
 if(!is_array($adm)||empty($adm['password_hash'])||empty($adm['totp_secret'])||empty($cfg['secret']))err('Admin-Konfiguration ungültig.',503);
 $db=new PDO($cfg['dsn'],$cfg['user'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
} catch(Throwable $e){error_log('TP admin init: '.$e->getMessage());err('Administration derzeit nicht erreichbar.',503);}
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_name('TP_RADAR_ADMIN');
session_set_cookie_params(['lifetime'=>0,'path'=>'/adm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
session_start();
function sql(PDO $db,string $q,array $args=[]):PDOStatement{$s=$db->prepare($q);$s->execute($args);return $s;}
function payload():array{$raw=file_get_contents('php://input');if(strlen($raw)>25000)err('Anfrage zu groß.',413);$d=json_decode($raw,true);if(!is_array($d))err('Ungültige Anfrage.');return $d;}
function stringvalfield(mixed $v,int $max,string $label):string{if(!is_string($v))err($label.' fehlt.');$v=trim(preg_replace('/\s+/u',' ',$v));if($v===''||mb_strlen($v)>$max)err($label.' ungültig.');return $v;}
function intvalfield(mixed $v,int $min,int $max,string $label):int {if(!is_int($v)&&!(is_string($v)&&preg_match('/^\d+$/D',$v)))err($label.' ungültig.');$i=filter_var($v,FILTER_VALIDATE_INT);if($i===false||$i<$min||$i>$max)err($label.' ungültig.');return (int)$i;}
function b32(string $s):string|false{$alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$s=strtoupper(str_replace(' ','',$s));if(!preg_match('/^[A-Z2-7]{16,}$/D',$s))return false;$bits='';foreach(str_split($s) as $ch){$pos=strpos($alphabet,$ch);if($pos===false)return false;$bits.=str_pad(decbin($pos),5,'0',STR_PAD_LEFT);} $out='';foreach(str_split($bits,8) as $piece){if(strlen($piece)===8)$out.=chr(bindec($piece));}return $out;}
function totpStep(string $secret,string $code):?int {
 if(!preg_match('/^\d{6}$/D',$code))return null;$key=b32($secret);if($key===false)return null;
 $now=intdiv(time(),30);foreach([$now-1,$now,$now+1] as $step){$bin=pack('N2',intdiv($step,4294967296),$step%4294967296);$h=hash_hmac('sha1',$bin,$key,true);$o=ord($h[19])&15;$num=((ord($h[$o])&127)<<24)|(ord($h[$o+1])<<16)|(ord($h[$o+2])<<8)|ord($h[$o+3]);$candidate=str_pad((string)($num%1000000),6,'0',STR_PAD_LEFT);if(hash_equals($candidate,$code))return $step;}return null;
}
function actor(array $cfg):string{return hash_hmac('sha256','admin:'.($_SERVER['REMOTE_ADDR']??'unknown'),$cfg['secret']);}
function audit(PDO $db,string $action,string $type,int $id,?array $before,?array $after):void {sql($db,'INSERT INTO admin_audit_log(action,entity_type,entity_id,before_json,after_json) VALUES(?,?,?,?,?)',[$action,$type,$id,$before===null?null:json_encode($before,JSON_UNESCAPED_UNICODE),$after===null?null:json_encode($after,JSON_UNESCAPED_UNICODE)]);}
function required():void {if(empty($_SESSION['admin'])||time()-(int)($_SESSION['last_active']??0)>1800){$_SESSION=[];err('Sitzung abgelaufen. Bitte neu anmelden.',401);}$_SESSION['last_active']=time();}
function csrfCheck(array $data):void{if(!is_string($data['csrf']??null)||!isset($_SESSION['csrf'])||!hash_equals((string)$_SESSION['csrf'],$data['csrf']))err('Sicherheits-Token ungültig.',403);}
function originCheck():void{
 // Shared hosting can rewrite the Origin header. A session-bound CSRF token
 // is required on *every* POST, including login; an explicit cross-site
 // Fetch Metadata signal remains a hard rejection.
 $fetch=(string)($_SERVER['HTTP_SEC_FETCH_SITE']??'');
 if($fetch==='cross-site')err('Anfragen von fremden Webseiten sind nicht erlaubt.',403);
}
function row(PDO $db,string $table,int $id):array{
 if(!in_array($table,['products','stores','prices'],true))err('Ungültiger Datentyp.');
 $x=sql($db,"SELECT * FROM $table WHERE id=? FOR UPDATE",[$id])->fetch();if(!$x)err('Eintrag nicht gefunden.',404);return $x;
}
$action=$_GET['action']??'status';$method=$_SERVER['REQUEST_METHOD']??'GET';
try {
 if($action==='status'&&$method==='GET'){
  $active=!empty($_SESSION['admin'])&&time()-(int)($_SESSION['last_active']??0)<=1800;
  if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
  out(['ok'=>true,'authenticated'=>$active,'csrf'=>$_SESSION['csrf']]);
 }
 if($method!=='POST')err('Methode nicht erlaubt.',405);
 originCheck();$data=payload();csrfCheck($data);
 if($action==='login'){
  $ip=actor($cfg);$attempt=sql($db,'SELECT * FROM admin_login_attempts WHERE actor_hash=?',[$ip])->fetch();
  if($attempt&&$attempt['blocked_until']!==null&&strtotime($attempt['blocked_until'])>time())err('Zu viele Versuche. Bitte später erneut versuchen.',429);
  $username=is_string($data['username']??null)?$data['username']:'';$password=is_string($data['password']??null)?$data['password']:'';
  $otp=is_string($data['totp']??null)?$data['totp']:'';
  $passOK=hash_equals((string)$adm['username'],$username)&&password_verify($password,(string)$adm['password_hash']);
  $step=$passOK?totpStep((string)$adm['totp_secret'],$otp):null;
  $success=false;
  if($passOK&&$step!==null){
   $db->beginTransaction();
   $state=sql($db,'SELECT last_totp_step FROM admin_security_state WHERE id=1 FOR UPDATE')->fetch();
   if($state&&$step>(int)$state['last_totp_step']){sql($db,'UPDATE admin_security_state SET last_totp_step=? WHERE id=1',[$step]);$success=true;}
   $db->commit();
  }
  if(!$success){
   sql($db,'INSERT INTO admin_login_attempts(actor_hash,window_start,attempts,blocked_until) VALUES(?,NOW(),1,NULL) ON DUPLICATE KEY UPDATE attempts=IF(window_start < NOW() - INTERVAL 15 MINUTE,1,attempts+1),blocked_until=IF(window_start < NOW() - INTERVAL 15 MINUTE,NULL,IF(attempts>=4,NOW() + INTERVAL 15 MINUTE,blocked_until)),window_start=IF(window_start < NOW() - INTERVAL 15 MINUTE,NOW(),window_start)',[$ip]);
   err('Anmeldedaten ungültig oder Code bereits verwendet.',401);
  }
  sql($db,'DELETE FROM admin_login_attempts WHERE actor_hash=?',[$ip]);
  session_regenerate_id(true);$_SESSION['admin']=true;$_SESSION['last_active']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));
  out(['ok'=>true,'csrf'=>$_SESSION['csrf']]);
 }
 required();csrfCheck($data);
 if($action==='logout'){$_SESSION=[];session_destroy();out(['ok'=>true]);}
 if($action==='list'){
  $products=sql($db,'SELECT id,name,ean,rolls,sheets,layers,is_active FROM products ORDER BY id DESC LIMIT 2000')->fetchAll();
  $stores=sql($db,'SELECT id,name,is_active FROM stores ORDER BY name LIMIT 1000')->fetchAll();
  $prices=sql($db,'SELECT p.id,p.product_id,p.store_id,p.price_cents,p.observed_on,p.status,p.reason,pr.name AS product_name,s.name AS store_name FROM prices p JOIN products pr ON pr.id=p.product_id JOIN stores s ON s.id=p.store_id ORDER BY p.id DESC LIMIT 2000')->fetchAll();
  $logs=sql($db,'SELECT id,action,entity_type,entity_id,created_at FROM admin_audit_log ORDER BY id DESC LIMIT 100')->fetchAll();
  out(['ok'=>true,'products'=>$products,'stores'=>$stores,'prices'=>$prices,'audit'=>$logs]);
 }
 if($action==='create_store'){
  // Only authenticated administrators with valid CSRF token can reach this path.
  $name=stringvalfield($data['name']??null,80,'Geschäft');
  $normalized=mb_strtolower($name);
  $db->beginTransaction();
  $exists=sql($db,'SELECT id FROM stores WHERE normalized_name=? LIMIT 1',[$normalized])->fetch();
  if($exists){$db->rollBack();err('Dieses Geschäft ist bereits vorhanden. Bitte vorhandenen Eintrag bearbeiten.',409);}
  sql($db,'INSERT INTO stores(name,normalized_name,is_active) VALUES(?,?,1)',[$name,$normalized]);
  $createdId=(int)$db->lastInsertId();
  audit($db,'create','stores',$createdId,null,['name'=>$name,'normalized_name'=>$normalized,'is_active'=>1]);
  $db->commit();
  out(['ok'=>true,'id'=>$createdId]);
 }
 $type=is_string($data['type']??null)?$data['type']:'';
 $id=intvalfield($data['id']??null,1,PHP_INT_MAX,'ID');
 if($action==='save'){
  if(!in_array($type,['products','stores','prices'],true))err('Ungültiger Bereich.');
  $db->beginTransaction();
  $before=row($db,$type,$id);
  if($type==='products'){
   $name=stringvalfield($data['name']??null,120,'Produktname');$rolls=intvalfield($data['rolls']??null,1,500,'Rollen');$sheets=intvalfield($data['sheets']??null,1,2000,'Blätter');$layers=intvalfield($data['layers']??null,1,10,'Lagen');
   $ean=$data['ean']??null;if($ean==='')$ean=null;if($ean!==null&&(!is_string($ean)||!preg_match('/^\d{8,14}$/D',$ean)))err('EAN ungültig.');
   $active=($data['is_active']??false)?1:0;
   sql($db,'UPDATE products SET name=?,ean=?,rolls=?,sheets=?,layers=?,is_active=? WHERE id=?',[$name,$ean,$rolls,$sheets,$layers,$active,$id]);
  }elseif($type==='stores'){
   $name=stringvalfield($data['name']??null,80,'Geschäft');$active=($data['is_active']??false)?1:0;
   sql($db,'UPDATE stores SET name=?,normalized_name=?,is_active=? WHERE id=?',[$name,mb_strtolower($name),$active,$id]);
  }else{
   $cents=intvalfield($data['price_cents']??null,1,999999,'Preis in Cent');$date=(string)($data['observed_on']??'');
   $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$dt||$dt->format('Y-m-d')!==$date)err('Datum ungültig.');
   $status=$data['status']??'';if(!in_array($status,['confirmed','pending','rejected'],true))err('Status ungültig.');
   sql($db,'UPDATE prices SET price_cents=?,observed_on=?,status=?,reason=? WHERE id=?',[$cents,$date,$status,$status==='rejected'?'Admin: deaktiviert':null,$id]);
  }
  $after=row($db,$type,$id);audit($db,'update',$type,$id,$before,$after);$db->commit();out(['ok'=>true]);
 }
 if($action==='merge_store'){
  $target=intvalfield($data['target_id']??null,1,PHP_INT_MAX,'Zielgeschäft');if($target===$id)err('Quell- und Zielgeschäft sind gleich.');
  $db->beginTransaction();$before=row($db,'stores',$id);row($db,'stores',$target);
  $count=(int)sql($db,'SELECT COUNT(*) FROM prices WHERE store_id=?',[$id])->fetchColumn();
  sql($db,'UPDATE prices SET store_id=? WHERE store_id=?',[$target,$id]);
  sql($db,'UPDATE stores SET is_active=0 WHERE id=?',[$id]);
  audit($db,'merge_store','stores',$id,$before,['target_id'=>$target,'moved_prices'=>$count]);$db->commit();out(['ok'=>true]);
 }
 err('Unbekannte Aktion.',404);
}catch(PDOException $e){if($db->inTransaction())$db->rollBack();error_log('Admin SQL: '.$e->getMessage());err('Datenbankfehler. Bitte Eintrag und Schema prüfen.',503);}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Admin action: '.$e->getMessage());err('Serverfehler.',500);}
