<?php
declare(strict_types=1);
// One-time, self-locking TP Radar administrator enrollment.
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Content-Security-Policy: default-src \'none\'; script-src \'self\'; style-src \'self\'; img-src \'self\' data:; form-action \'self\'; base-uri \'none\'; frame-ancestors \'none\'');
header('X-Robots-Tag: noindex, nofollow, noarchive');
$root = dirname(__DIR__, 2);
$adminFile = $root . '/private/admin.php';
$setupFile = $root . '/private/setup.php';
$cfgFile = $root . '/private/config.php';
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function stopPage(string $title, string $info, int $status = 503): never {
    http_response_code($status);
    $title = h($title); $info = h($info);
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>TP-Radar · Einrichtung</title><link rel="stylesheet" href="style.css?v=5.0.1"></head><body><main class="shell"><section class="card narrow"><h1>'.$title.'</h1><p>'.$info.'</p><p><a href="./">Zur Admin-Anmeldung</a></p><p class="muted">TP-Radar Setup-Version 5.0.1</p></section></main></body></html>';
    exit;
}
// The setup endpoint cannot re-enroll an existing administrator.
if (is_file($adminFile)) stopPage('Einrichtung bereits abgeschlossen', 'Der Adminzugang ist bereits eingerichtet. Ein erneuter Setup-Vorgang ist über diese Seite nicht möglich.', 410);
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') stopPage('HTTPS erforderlich', 'Bitte öffne den Einrichtungsassistenten über HTTPS.', 403);
if (!is_file($setupFile) || !is_file($cfgFile)) stopPage('Einrichtung gesperrt', 'Die private Einrichtungskonfiguration fehlt. Bitte zuerst private/setup.php mit einem eigenen Einrichtungsschlüssel anlegen.');
$settings = require $setupFile;
$cfg = require $cfgFile;
$key = is_array($settings) ? ($settings['setup_key'] ?? '') : '';
if (!is_string($key) || strlen($key) < 32 || $key === 'REPLACE_WITH_32_OR_MORE_RANDOM_CHARACTERS') stopPage('Einrichtung gesperrt', 'Der Einrichtungsschlüssel in private/setup.php ist noch nicht sicher konfiguriert.');
if (!is_array($cfg) || empty($cfg['secret']) || empty($cfg['dsn']) || empty($cfg['user'])) stopPage('Konfiguration unvollständig', 'Bitte die bestehende private/config.php prüfen.');
try {
    $db = new PDO($cfg['dsn'], $cfg['user'], $cfg['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
} catch (Throwable $e) {
    error_log('TP setup database connection: ' . $e->getMessage());
    stopPage('Datenbankverbindung fehlgeschlagen', 'Die Datenbank kann nicht verbunden werden. Bitte private/config.php (Host, Datenbank, Benutzer, Passwort) und Datenbankberechtigungen prüfen.');
}
try {
    // Avoid misreporting a missing seed record as a missing migration.
    $db->query('SELECT actor_hash FROM admin_login_attempts LIMIT 0');
    $db->query('SELECT id FROM admin_audit_log LIMIT 0');
    $db->query('SELECT is_active FROM products LIMIT 0');
    $db->query('SELECT is_active FROM stores LIMIT 0');
    $db->query('SELECT last_totp_step FROM admin_security_state LIMIT 0');
} catch (Throwable $e) {
    error_log('TP setup schema check: ' . $e->getMessage());
    stopPage('Datenbankmigration unvollständig', 'Mindestens eine Admin-Tabelle oder Spalte fehlt. Bitte Datenbank und Zugangsdaten pruefen. Fuer eine frische, leere Datenbank bitte schema.sql importieren.');
}
try {
    // Non-destructive repair for a partially imported V4.6 migration. This
    // requires only INSERT permission, and never replaces an existing step.
    $db->exec('INSERT IGNORE INTO admin_security_state(id,last_totp_step) VALUES(1,-1)');
    $stateRow = $db->query('SELECT last_totp_step FROM admin_security_state WHERE id=1')->fetch();
    if (!$stateRow) throw new RuntimeException('Admin security row missing after INSERT IGNORE');
} catch (Throwable $e) {
    error_log('TP setup security seed: ' . $e->getMessage());
    stopPage('Sicherheitsdatensatz nicht verfügbar', 'Die Admin-Tabellen sind vorhanden, aber der Sicherheitsdatensatz konnte nicht angelegt oder gelesen werden. Bitte die Datenbank in private/config.php sowie INSERT-/SELECT-Rechte kontrollieren. Fuer eine neue, leere Datenbank schema.sql importieren.');
}
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('TP_RADAR_SETUP');
session_set_cookie_params(['lifetime'=>0, 'path'=>'/adm', 'secure'=>true, 'httponly'=>true, 'samesite'=>'Strict']);
session_start();
if (empty($_SESSION['setup_csrf'])) $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
if (!isset($_SESSION['attempts'])) $_SESSION['attempts'] = 0;
function b32enc(string $data): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $buffer=0; $bits=0; $result='';
    for ($i=0; $i<strlen($data); $i++) {
        $buffer=($buffer<<8)|ord($data[$i]); $bits+=8;
        while ($bits>=5) { $bits-=5; $result.=$alphabet[($buffer>>$bits)&31]; }
        $buffer &= (1<<$bits)-1;
    }
    if ($bits) $result.=$alphabet[($buffer<<(5-$bits))&31];
    return $result;
}
function b32dec(string $data): string|false {
    if (!preg_match('/^[A-Z2-7]{32}$/D',$data)) return false;
    $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $buffer=0; $bits=0; $res='';
    foreach(str_split($data) as $char) {
        $buffer=($buffer<<5)|strpos($chars,$char);$bits+=5;
        if ($bits>=8) {$bits-=8;$res.=chr(($buffer>>$bits)&255);$buffer &= (1<<$bits)-1;}
    }
    return $res;
}
function verifyTOTP(string $secret, string $code): ?int {
    if (!preg_match('/^[0-9]{6}$/D',$code)) return null;
    $bin=b32dec($secret);if($bin===false)return null;
    $now=intdiv(time(),30);
    foreach([$now-1,$now,$now+1] as $step) {
        $mac=hash_hmac('sha1',pack('N2',0,$step),$bin,true);
        $pos=ord($mac[19])&15;
        $val=((ord($mac[$pos])&127)<<24)|(ord($mac[$pos+1])<<16)|(ord($mac[$pos+2])<<8)|ord($mac[$pos+3]);
        if(hash_equals(str_pad((string)($val%1000000),6,'0',STR_PAD_LEFT),$code))return $step;
    }
    return null;
}
function actor(array $cfg): string { return hash_hmac('sha256','setup:'.($_SERVER['REMOTE_ADDR']??'unknown'),(string)$cfg['secret']); }
function failAttempt(PDO $db,array $cfg): void {
    $_SESSION['attempts']++;
    $stmt=$db->prepare('INSERT INTO admin_login_attempts(actor_hash,window_start,attempts,blocked_until) VALUES(?,NOW(),1,NULL) ON DUPLICATE KEY UPDATE attempts=IF(window_start < NOW()-INTERVAL 15 MINUTE,1,attempts+1),blocked_until=IF(window_start < NOW()-INTERVAL 15 MINUTE,NULL,IF(attempts>=4,NOW()+INTERVAL 15 MINUTE,blocked_until)),window_start=IF(window_start < NOW()-INTERVAL 15 MINUTE,NOW(),window_start)');
    $stmt->execute([actor($cfg)]);
}
function isBlocked(PDO $db,array $cfg): bool {
    $stmt=$db->prepare('SELECT blocked_until FROM admin_login_attempts WHERE actor_hash=?');$stmt->execute([actor($cfg)]);
    $r=$stmt->fetch();return !empty($r['blocked_until']) && strtotime((string)$r['blocked_until'])>time();
}
$stage = isset($_SESSION['enroll']) && is_array($_SESSION['enroll']) ? 'verify' : 'start';
$error='';$success=false;
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    // Use a trusted, explicitly configured public URL. HTTP_HOST may contain
    // an internal reverse-proxy hostname on shared hosting.
    $expectedOrigin=rtrim((string)($settings['site_origin']??''),'/');
    if (!filter_var($expectedOrigin, FILTER_VALIDATE_URL) ||
        parse_url($expectedOrigin, PHP_URL_SCHEME)!=='https' ||
        parse_url($expectedOrigin, PHP_URL_PATH)!==null ||
        parse_url($expectedOrigin, PHP_URL_QUERY)!==null ||
        parse_url($expectedOrigin, PHP_URL_FRAGMENT)!==null) {
        stopPage('Konfiguration ungueltig','Bitte site_origin in private/setup.php auf die genaue HTTPS-Domain setzen.',503);
    }
    // Some shared hosts / browser privacy tools alter or omit Origin. The
    // exact Origin string is therefore not used as the sole CSRF gate.
    // Reject requests explicitly classified as cross-site by Fetch Metadata;
    // a Strict SameSite session cookie, a session-bound random CSRF token,
    // and a separate high-entropy setup key remain mandatory.
    $fetch=(string)($_SERVER['HTTP_SEC_FETCH_SITE']??'');
    if ($fetch==='cross-site') stopPage('Zugriff verweigert', 'Anfragen von fremden Webseiten sind nicht erlaubt.', 403);
    $csrfInput=$_POST['csrf']??null;
    if (!is_string($csrfInput) || !hash_equals((string)$_SESSION['setup_csrf'],$csrfInput))
        stopPage('Sicherheitsprüfung fehlgeschlagen','Bitte den Einrichtungsassistenten neu laden und erneut versuchen.',403);
    // Never log Origin/Referer: setup URLs or other header values may
    // inadvertently include sensitive information. A valid CSRF token is
    // required even when an Origin header is absent or noncanonical.
    if(isBlocked($db,$cfg) || $_SESSION['attempts']>=8) stopPage('Einrichtung vorübergehend gesperrt','Zu viele Fehlversuche. Bitte später mit einem neuen Browserfenster versuchen.',429);
    if(isset($_POST['begin']) && $stage==='start') {
        $inputKey=is_string($_POST['setup_key']??null)?$_POST['setup_key']:'';
        $username=is_string($_POST['username']??null)?trim($_POST['username']):'';
        $pass=is_string($_POST['password']??null)?$_POST['password']:'';
        $pass2=is_string($_POST['password_confirm']??null)?$_POST['password_confirm']:'';
        if (!hash_equals($key,$inputKey)) {failAttempt($db,$cfg);$error='Einrichtungsschlüssel ungültig.';}
        elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/D',$username)) $error='Benutzername: 3–40 Zeichen, Buchstaben, Ziffern, Punkt, Bindestrich oder Unterstrich.';
        elseif (strlen($pass)<16 || strlen($pass)>256) $error='Das Passwort muss zwischen 16 und 256 Zeichen lang sein.';
        elseif (!hash_equals($pass,$pass2)) $error='Die beiden Passwörter stimmen nicht überein.';
        else {
            $hash=password_hash($pass,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT);
            if($hash===false)stopPage('Fehler','Passwort-Hash konnte nicht erstellt werden.');
            session_regenerate_id(true);
            $_SESSION['enroll']=['username'=>$username,'hash'=>$hash,'secret'=>b32enc(random_bytes(20)),'since'=>time()];
            $stage='verify';
        }
    } elseif (isset($_POST['complete']) && $stage==='verify') {
        $enroll=$_SESSION['enroll'];
        if(time()-$enroll['since']>900){unset($_SESSION['enroll']);$stage='start';$error='Die Einrichtung ist abgelaufen. Bitte neu beginnen.';}
        else {
            $code=is_string($_POST['totp']??null)?trim($_POST['totp']):'';
            $step=verifyTOTP($enroll['secret'],$code);
            if($step===null){failAttempt($db,$cfg);$error='Authenticator-Code ungültig. Uhrzeit am Smartphone prüfen.';}
            else {
                if(is_file($adminFile)) stopPage('Bereits eingerichtet','Ein anderer Vorgang hat den Adminzugang bereits angelegt.',409);
                $contents="<?php\n// Created by protected TP Radar first-run setup. Keep OUTSIDE public/.\nreturn ".var_export(['username'=>$enroll['username'],'password_hash'=>$enroll['hash'],'totp_secret'=>$enroll['secret']],true).";\n";
                // Keep row lock until both the file and the TOTP replay state
                // are securely persisted. Roll back and remove only our newly
                // created file if either operation fails.
                $created=false;
                try {
                    $db->beginTransaction();
                    $state=$db->query('SELECT last_totp_step FROM admin_security_state WHERE id=1 FOR UPDATE')->fetch();
                    if (!$state) throw new RuntimeException('Admin security row not found');
                    if ($step <= (int)$state['last_totp_step']) throw new RuntimeException('TOTP code already used');
                    $fh=@fopen($adminFile,'x');
                    if($fh===false) throw new RuntimeException('Cannot create private/admin.php');
                    $created=true;
                    @chmod($adminFile,0600);
                    $bytes=fwrite($fh,$contents);
                    $flushed=fflush($fh);
                    fclose($fh);
                    if($bytes!==strlen($contents) || !$flushed) throw new RuntimeException('Incomplete admin file write');
                    $stmt=$db->prepare('UPDATE admin_security_state SET last_totp_step=? WHERE id=1');
                    $stmt->execute([$step]);
                    if ($stmt->rowCount() !== 1) throw new RuntimeException('Cannot persist TOTP step');
                    $db->commit();
                    unset($_SESSION['enroll']);$_SESSION=[];session_destroy();
                    $success=true;
                } catch (Throwable $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    if ($created) @unlink($adminFile);
                    error_log('TP setup completion: '.$e->getMessage());
                    $error='Die Einrichtung konnte nicht sicher abgeschlossen werden. Bitte Schreibrechte von private/ und die Datenbank prüfen und den Vorgang erneut versuchen.';
                }
            }
        }
    }
}
$csrf=h($_SESSION['setup_csrf']??'');
$uri='';$totpSecret='';
if($stage==='verify' && isset($_SESSION['enroll'])) {
    $enroll=$_SESSION['enroll'];
    $totpSecret=$enroll['secret'];
    $uri='otpauth://totp/'.rawurlencode('TP Radar:'.$enroll['username']).'?secret='.$totpSecret.'&issuer='.rawurlencode('TP Radar').'&algorithm=SHA1&digits=6&period=30';
}
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0d171c"><title>TP-Radar · Admin einrichten</title><link rel="icon" href="../favicon.ico"><link rel="stylesheet" href="style.css?v=5.0.1"><link rel="stylesheet" href="setup.css?v=5.0.1"></head>
<body><div class="shell"><header><div><span class="logo">🧻</span> <strong>TP Radar</strong> <span class="tag">Einrichtung</span></div><a href="/">Zur App</a></header><main><section class="card narrow setup-card">
<h1>Administrator einrichten</h1><p class="muted">TP-Radar Setup-Version 5.0.1</p>
<?php if($success): ?>
<p class="notice success">Der Adminzugang wurde erfolgreich eingerichtet und der Assistent automatisch gesperrt.</p>
<p>Entferne jetzt <code>private/setup.php</code> vom Webspace. Dein Authenticator-Konto und den Sicherungsschlüssel bitte sicher verwahren.</p>
<p><a class="buttonlink" href="./">Zur Admin-Anmeldung</a></p>
<?php else: ?>
<p>Einmalige Einrichtung mit starkem Passwort und Authenticator-App. Die öffentliche TP-Radar-App bleibt ohne Anmeldung nutzbar.</p>
<?php if($error): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>
<?php if($stage==='start'): ?>
<form method="post" action="setup.php" autocomplete="off">
<input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="begin" value="1">
<label>Einrichtungsschlüssel aus <code>private/setup.php</code><input name="setup_key" type="password" required minlength="32" autocomplete="off"></label>
<label>Administratorname<input name="username" required minlength="3" maxlength="40" value="admin" autocomplete="username"></label>
<label>Neues Administratorpasswort (mindestens 16 Zeichen)<input name="password" type="password" required minlength="16" autocomplete="new-password"></label>
<label>Passwort wiederholen<input name="password_confirm" type="password" required minlength="16" autocomplete="new-password"></label>
<button type="submit">Authenticator einrichten</button>
</form>
<?php else: ?>
<h2>Authenticator verbinden</h2>
<p>Öffne 2FAS, Aegis oder Google Authenticator. Scanne den QR-Code oder gib den Schlüssel manuell ein. Der QR-Code wird lokal im Browser erzeugt, ohne externe Dienste.</p>
<div class="qrbox" id="qrbox" data-otpauth="<?=h($uri)?>"><span>QR-Code wird erzeugt …</span></div>
<p class="secretlabel">Manueller Einrichtungsschlüssel:</p><p class="secret"><code><?=h($totpSecret)?></code></p>
<p class="setup-note">Zeitbasiert (TOTP), SHA1, 6 Ziffern, 30 Sekunden. Bewahre den Schlüssel sicher auf und teile ihn niemals.</p>
<form method="post" action="setup.php" autocomplete="off">
<input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="complete" value="1">
<label>Aktueller sechsstelliger Code aus der Authenticator-App<input name="totp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
<button type="submit">Code prüfen und Admin aktivieren</button>
</form>
<?php endif; ?>
<?php endif; ?>
</section></main></div>
<?php if($stage==='verify'&&!$success): ?><script src="qr.js?v=5.0.1" defer></script><script src="setup-qr.js?v=5.0.1" defer></script><?php endif; ?>
</body></html>
