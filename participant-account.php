<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
if (!$secure && PHP_SAPI!=='cli-server') { http_response_code(403); exit('เปิดหน้าบัญชีผ่าน HTTPS'); }
define('ANEMIA_STAFF_BOOT',true);
require __DIR__.'/_private/participant-account.php';
require __DIR__.'/_private/icons.php';
anemiaParticipantSession($secure);
$_SESSION['csrf']??=bin2hex(random_bytes(32));
$account=$_SESSION['account']??null;
if (($_SESSION['until']??0)<time()) { unset($_SESSION['account']); $account=null; }
$redirect=getenv('ANEMIA_ACCOUNT_CALLBACK')?:'';
$facebookVersion=getenv('ANEMIA_FACEBOOK_GRAPH_VERSION')?:'';
$providers=[
    'line'=>['LINE','https://access.line.me/oauth2/v2.1/authorize','https://api.line.me/oauth2/v2.1/token','openid'],
    'google'=>['Google','https://accounts.google.com/o/oauth2/v2/auth','https://oauth2.googleapis.com/token','openid profile'],
    'facebook'=>['Facebook',"https://www.facebook.com/$facebookVersion/dialog/oauth","https://graph.facebook.com/$facebookVersion/oauth/access_token",'public_profile'],
];
foreach ($providers as $key=>$details) {
    $prefix='ANEMIA_'.strtoupper($key);
    $providers[$key]+=[4=>getenv($prefix.'_CLIENT_ID')?:'',5=>getenv($prefix.'_CLIENT_SECRET')?:''];
}
$canConnect=static fn(string $key): bool => isset($providers[$key]) && $providers[$key][4]!=='' && $providers[$key][5]!=='' &&
    filter_var($redirect,FILTER_VALIDATE_URL)!==false && parse_url($redirect,PHP_URL_SCHEME)==='https' &&
    ($key!=='facebook'||preg_match('/^v\d+\.\d+$/D',$facebookVersion));
$invite=$_REQUEST['invite']??'';
$invite=is_string($invite)&&preg_match('/^[a-f0-9]{32}$/D',$invite)?$invite:'';
$returnToPortal=($_REQUEST['return']??'')==='portal';

function participantProviderJson(string $url,?array $form=null,?string $token=null): array
{
    $curl=curl_init($url);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
    if ($form!==null) curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($form));
    if ($token!==null) curl_setopt($curl,CURLOPT_HTTPHEADER,['Authorization: Bearer '.$token]);
    $body=curl_exec($curl);
    $status=curl_getinfo($curl,CURLINFO_HTTP_CODE);
    if (!is_string($body)||$status!==200) throw new RuntimeException('Provider request failed');
    return json_decode($body,true,16,JSON_THROW_ON_ERROR);
}
function participantDb(): PDO
{
    require_once __DIR__.'/_private/database.php';
    $config=require __DIR__.'/_private/config.php';
    $db=anemiaDatabase($config);
    initializeAnemia($db,$config);
    return $db;
}
function participantEscape(string $text): string {return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

$notice='';
$passwordMode='';
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])) { http_response_code(403); exit('กรุณาเปิดหน้าใหม่'); }
        $key=$_POST['provider']??'';
        if ($key==='logout') {
            $_SESSION=[];
            session_regenerate_id(true);
            header('Location: participant-account.php'.($returnToPortal?'?return=portal':''),true,303); exit;
        }
        if (in_array($key,['password-login','password-register'],true)) {
            if ($account!==null) throw new DomainException('กรุณาออกจากบัญชีเดิมก่อน');
            $passwordMode=$key;
            $db=participantDb();
            if (!consumeAnemiaAttempt($db,'participant-password:'.($_SERVER['REMOTE_ADDR']??'unknown'))) {
                http_response_code(429);
                throw new DomainException('ลองหลายครั้งเกินไป กรุณารอสักครู่');
            }
            $account=anemiaParticipantPassword($db,$key==='password-register',$_POST,anemiaTeamCookieHash());
            session_regenerate_id(true);
            $_SESSION=['account'=>$account,'until'=>time()+28800,'csrf'=>bin2hex(random_bytes(32))];
            $target=$returnToPortal?'index.html':'index.html?team=1'.($invite!==''?'&invite='.$invite:'');
            header('Location: '.$target,true,303); exit;
        }
        if (!is_string($key)||!$canConnect($key)) throw new DomainException('ช่องทางนี้ยังไม่เปิดใช้งาน');
        [, $authorize, , $scope,$client]=$providers[$key];
        $state=bin2hex(random_bytes(24));
        $nonce=bin2hex(random_bytes(24));
        $verifier=bin2hex(random_bytes(32));
        $_SESSION['oauth']=['state'=>$state,'provider'=>$key,'until'=>time()+600,'account'=>$account,'invite'=>$invite,'nonce'=>$nonce,'verifier'=>$verifier,'portal'=>$returnToPortal];
        $params=['response_type'=>'code','client_id'=>$client,'redirect_uri'=>$redirect,'state'=>$state,'scope'=>$scope];
        if ($key!=='facebook') $params+=['code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='),'code_challenge_method'=>'S256'];
        if ($key==='line') $params['nonce']=$nonce;
        header('Location: '.$authorize.'?'.http_build_query($params),true,303); exit;
    }
    if (isset($_GET['state'])||isset($_GET['code'])||isset($_GET['error'])) {
        $flow=$_SESSION['oauth']??null;
        $key=$flow['provider']??'';
        if (!$flow||$flow['until']<time()||!is_string($_GET['state']??null)||!hash_equals($flow['state'],$_GET['state'])||$account!==$flow['account']||!$canConnect($key)) {
            throw new DomainException('การเชื่อมบัญชีหมดอายุ กรุณาเริ่มใหม่');
        }
        unset($_SESSION['oauth']);
        $invite=$flow['invite'];
        $returnToPortal=$flow['portal']??false;
        $code=$_GET['code']??null;
        if (!is_string($code)||$code==='') throw new DomainException('ยังไม่ได้เชื่อมบัญชี');
        [,,$tokenUrl,,$client,$secret]=$providers[$key];
        $params=['grant_type'=>'authorization_code','client_id'=>$client,'client_secret'=>$secret,'redirect_uri'=>$redirect,'code'=>$code];
        if ($key!=='facebook') $params['code_verifier']=$flow['verifier'];
        // Meta accepts a GET for its server-side code exchange.
        $tokens=$key==='facebook'?participantProviderJson($tokenUrl.'?'.http_build_query($params)):participantProviderJson($tokenUrl,$params);
        if ($key==='line') {
            $info=participantProviderJson('https://api.line.me/oauth2/v2.1/verify',['id_token'=>$tokens['id_token']??'','client_id'=>$client,'nonce'=>$flow['nonce']]);
        } else {
            $token=$tokens['access_token']??null;
            if (!is_string($token)||$token==='') throw new RuntimeException('Missing access token');
            $url=$key==='google'?'https://openidconnect.googleapis.com/v1/userinfo':"https://graph.facebook.com/$facebookVersion/me?fields=id&appsecret_proof=".hash_hmac('sha256',$token,$secret);
            $info=participantProviderJson($url,null,$token);
        }
        $subject=$info[$key==='facebook'?'id':'sub']??null;
        if (!is_string($subject)||$subject==='') throw new RuntimeException('Missing provider identifier');
        $account=anemiaConnectParticipant(participantDb(),$key,$subject,$account,anemiaTeamCookieHash());
        session_regenerate_id(true);
        $_SESSION=['account'=>$account,'until'=>time()+28800,'csrf'=>bin2hex(random_bytes(32))];
        $target=$returnToPortal?'index.html':'index.html?team=1'.($invite!==''?'&invite='.$invite:'');
        header('Location: '.$target,true,303); exit;
    }
} catch (Throwable $error) {
    if (!$error instanceof DomainException) error_log('Anemia participant OAuth: '.get_class($error));
    $notice=$error instanceof DomainException?$error->getMessage():'เชื่อมบัญชีไม่สำเร็จ กรุณาลองอีกครั้ง';
}
$linked=[];
$username=null;
if ($account) {
    try {
        $db=participantDb();
        $query=$db->prepare('SELECT provider FROM anemia_participant_identity WHERE account_id=?');
        $query->execute([$account]);
        $linked=$query->fetchAll(PDO::FETCH_COLUMN);
        $query=$db->prepare('SELECT username FROM anemia_participant_password WHERE account_id=?');
        $query->execute([$account]);
        $username=$query->fetchColumn()?:null;
    } catch (Throwable $error) { $notice='โหลดข้อมูลบัญชีไม่สำเร็จ'; }
}
?>
<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>บัญชีของฉัน · Anemia Portal</title><script src="theme.js?v=1"></script><script defer src="installed-update.js"></script><link rel="stylesheet" href="style.css?v=participant-icons-v50">
<main><section class="panel participant-account">
<h1 class="title"><span class="iconbox"><?= workspaceIcon('person') ?></span>บัญชีของฉัน</h1>
<p>เก็บแต้มและกลับมาเรียนต่อบนเครื่องอื่น รับ 5 แต้มเมื่อเชื่อม LINE, Google หรือช่องทางภายนอกครั้งแรก</p>
<?php if ($username): ?><p>ชื่อบัญชี <strong><?= participantEscape($username) ?></strong></p><?php endif; ?>
<?php if ($linked): ?><p class="status">ได้รับโบนัสเชื่อมบัญชีแล้ว 5 แต้ม</p><?php endif; ?>
<?php if ($notice!==''): ?><p class="status" role="status"><?= participantEscape($notice) ?></p><?php endif; ?>
<?php if (!$account): ?>
<section class="participant-signup">
 <?php foreach (['password-login'=>'เข้าสู่ระบบด้วยรหัสผ่าน','password-register'=>'สมัครบัญชีใหม่'] as $mode=>$title): $register=$mode==='password-register'; ?>
 <details <?= $passwordMode===$mode || ($passwordMode==='' && !$register)?'open':'' ?>>
  <summary><?= workspaceIcon($register?'users':'login') ?> <?= participantEscape($title) ?></summary>
  <form method="post" action="participant-account.php">
   <input type="hidden" name="csrf" value="<?= participantEscape($_SESSION['csrf']) ?>">
   <input type="hidden" name="invite" value="<?= participantEscape($invite) ?>">
   <?php if ($returnToPortal): ?><input type="hidden" name="return" value="portal"><?php endif; ?>
   <label><span><?= workspaceIcon('person') ?> ชื่อบัญชี</span>
    <input name="username" autocomplete="username" maxlength="40" required autocapitalize="none" spellcheck="false">
   </label>
   <label><span><?= workspaceIcon('lock') ?> รหัสผ่าน<?php if ($register): ?> <small>อย่างน้อย 6 ตัวอักษร</small><?php endif; ?></span>
    <input type="password" name="password" autocomplete="<?= $register?'new-password':'current-password' ?>" <?= $register?'minlength="6"':'' ?> required>
   </label>
   <?php if ($register): ?><label><span><?= workspaceIcon('ready') ?> ยืนยันรหัสผ่าน</span><input type="password" name="confirm_password" autocomplete="new-password" minlength="6" required></label><?php endif; ?>
   <button class="action primary" name="provider" value="<?= $mode ?>"><?= workspaceIcon($register?'users':'login') ?><?= $register?'สมัครบัญชี':'เข้าสู่ระบบ' ?></button>
  </form>
 </details>
 <?php endforeach; ?>
</section>
<?php endif; ?>
<form method="post" action="participant-account.php">
 <input type="hidden" name="csrf" value="<?= participantEscape($_SESSION['csrf']) ?>">
 <input type="hidden" name="invite" value="<?= participantEscape($invite) ?>">
 <?php if ($returnToPortal): ?><input type="hidden" name="return" value="portal"><?php endif; ?>
 <div class="actions">
  <?php foreach (['line','google'] as $key): $label=$providers[$key][0]; $ready=$canConnect($key); $connected=in_array($key,$linked,true); ?>
   <button class="action" name="provider" value="<?= $key ?>" <?= !$ready||$connected?'disabled':'' ?>>
    <span class="participant-provider-mark" data-provider="<?= $key ?>" aria-hidden="true"><?= $key==='line'?'L':'G' ?></span>
    <?= $connected?'เชื่อมแล้ว · ':($ready?($account?'เชื่อมด้วย ':'เข้าสู่ระบบด้วย '):'') ?><?= participantEscape($label) ?><?= !$ready&&!$connected?' · ยังไม่เปิด':'' ?>
   </button>
  <?php endforeach; ?>
  <button class="action" type="button" disabled><?= workspaceIcon('person') ?>ThaID · รอเปิดบริการ</button>
 </div>
 <?php if ($canConnect('facebook') || in_array('facebook',$linked,true)): ?>
  <details><summary>ช่องทางอื่น</summary>
   <button class="action" name="provider" value="facebook" <?= !$canConnect('facebook')||in_array('facebook',$linked,true)?'disabled':'' ?>>Facebook<?= in_array('facebook',$linked,true)?' · เชื่อมแล้ว':'' ?></button>
  </details>
 <?php endif; ?>
 <?php if ($account): ?><div class="actions"><button class="action" name="provider" value="logout"><?= workspaceIcon('logout') ?>ออกจากบัญชี</button></div><?php endif; ?>
</form>
<p>ระบบเก็บรหัสบัญชีสำหรับเข้าสู่ระบบและจดจำแต้ม</p>
<?php if ($returnToPortal): ?>
 <a class="action" href="index.html">กลับหน้าบ้าน</a>
<?php else: ?>
 <a class="action" href="index.html?team=1<?= $invite!==''?'&amp;invite='.participantEscape($invite):'' ?>">กลับไปที่ทีมและแต้ม</a>
<?php endif; ?>
</section></main></html>
