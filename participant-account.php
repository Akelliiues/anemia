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
$profileEditing=false;
$passwordMode=in_array($_GET['mode']??'',['password-register','password-migrate'],true)?$_GET['mode']:'password-login';
try {
    if ($account!==null) {
        $epoch=anemiaParticipantEpoch(participantDb(),$account);
        if ($epoch===null || $epoch!==(int)($_SESSION['epoch']??0)) {
            unset($_SESSION['account'],$_SESSION['epoch'],$_SESSION['until'],$_SESSION['oauth']);
            $account=null;
            throw new DomainException('เซสชันบัญชีสิ้นสุดแล้ว กรุณาเข้าสู่ระบบใหม่');
        }
    }
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])) { http_response_code(403); exit('กรุณาเปิดหน้าใหม่'); }
        $key=$_POST['provider']??'';
        if ($key==='profile-save') {
            $profileEditing=true;
            if ($account===null) throw new DomainException('กรุณาเข้าสู่ระบบก่อนแก้โปรไฟล์');
            $photo=anemiaProfilePhoto($_FILES['profile_photo']??[]);
            $db=participantDb();
            $db->beginTransaction();
            try {
                $epoch=(int)($_SESSION['epoch']??0);
                $check=$db->prepare('SELECT id FROM anemia_participant_account WHERE id=? AND suspended_at IS NULL AND session_epoch=? FOR UPDATE');
                $check->execute([$account,$epoch]);
                if (!$check->fetchColumn()) throw new DomainException('เซสชันสิ้นสุดแล้ว กรุณาเข้าสู่ระบบใหม่');
                anemiaSaveParticipantProfile($db,$account,$epoch,$_POST);
                if ($photo!==null) {
                    $db->prepare('INSERT INTO anemia_participant_photo VALUES (?,?) ON DUPLICATE KEY UPDATE photo=VALUES(photo)')->execute([$account,$photo]);
                } elseif (($_POST['remove_photo']??'')==='1') {
                    $db->prepare('DELETE FROM anemia_participant_photo WHERE account_id=?')->execute([$account]);
                }
                $db->commit();
            } catch (Throwable $error) {
                $db->rollBack();
                throw $error;
            }
            $_SESSION['profile_saved']=true;
            header('Location: participant-account.php'.($returnToPortal?'?return=portal':''),true,303); exit;
        }
        if ($key==='logout') {
            $_SESSION=[];
            session_regenerate_id(true);
            header('Location: participant-account.php'.($returnToPortal?'?return=portal':''),true,303); exit;
        }
        if (in_array($key,['password-login','password-register','password-migrate'],true)) {
            if ($account!==null) throw new DomainException('กรุณาออกจากบัญชีเดิมก่อน');
            $passwordMode=$key;
            $db=participantDb();
            if (!consumeAnemiaAttempt($db,'participant-password:'.($_SERVER['REMOTE_ADDR']??'unknown'))) {
                http_response_code(429);
                throw new DomainException('ลองหลายครั้งเกินไป กรุณารอสักครู่');
            }
            $account=anemiaParticipantPassword($db,$key==='password-register',$_POST,anemiaTeamCookieHash(),$key==='password-migrate');
            anemiaStartParticipantSession($db,$account);
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
        $db=participantDb();
        $account=anemiaConnectParticipant($db,$key,$subject,$account,anemiaTeamCookieHash());
        anemiaStartParticipantSession($db,$account);
        $target=$returnToPortal?'index.html':'index.html?team=1'.($invite!==''?'&invite='.$invite:'');
        header('Location: '.$target,true,303); exit;
    }
} catch (Throwable $error) {
    if (!$error instanceof DomainException) error_log('Anemia participant OAuth: '.get_class($error));
    $notice=$error instanceof DomainException?$error->getMessage():'เชื่อมบัญชีไม่สำเร็จ กรุณาลองอีกครั้ง';
}
$linked=[];
$username=null;
$profile=['avatar'=>'drop','display_name'=>'','profile_status'=>''];
$teamPoints=0;
$teamJoined=false;
$hasPhoto=false;
if ($account) {
    try {
        $db=participantDb();
        $query=$db->prepare('SELECT provider FROM anemia_participant_identity WHERE account_id=?');
        $query->execute([$account]);
        $linked=$query->fetchAll(PDO::FETCH_COLUMN);
        $query=$db->prepare('SELECT username FROM anemia_participant_password WHERE account_id=?');
        $query->execute([$account]);
        $username=$query->fetchColumn()?:null;
        $query=$db->prepare('SELECT avatar,display_name,profile_status FROM anemia_participant_profile WHERE account_id=?');
        $query->execute([$account]);
        $profile=$query->fetch(PDO::FETCH_ASSOC)?:$profile;
        $query=$db->prepare('SELECT 1 FROM anemia_participant_photo WHERE account_id=?');
        $query->execute([$account]);
        $hasPhoto=(bool)$query->fetchColumn();
        $query=$db->prepare('SELECT m.completed_at,(SELECT COUNT(*) FROM anemia_team_bonus b WHERE b.inviter=m.id) AS invites FROM anemia_team_member m WHERE m.account_id=?');
        $query->execute([$account]);
        $team=$query->fetch(PDO::FETCH_ASSOC);
        $teamJoined=(bool)$team;
        $teamPoints=($linked?5:0)+(!empty($team['completed_at'])?10:0)+5*(int)($team['invites']??0);
        if (!empty($_SESSION['profile_saved'])) { $notice='บันทึกโปรไฟล์แล้ว'; unset($_SESSION['profile_saved']); }
    } catch (Throwable $error) { $notice='โหลดข้อมูลบัญชีไม่สำเร็จ'; }
}
?>
<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>บัญชีของฉัน · Anemia Portal</title><script src="theme.js?v=1"></script><script defer src="installed-update.js"></script><link rel="stylesheet" href="style.css?v=participant-photo-v56">
<body<?= $account?' class="participant-profile-page"':'' ?>>
<main class="participant-account-main"><section class="panel participant-account">
<nav class="participant-account-return" aria-label="ทางกลับหน้าหลัก">
 <a href="index.html"><?= workspaceIcon('back') ?>กลับหน้าหลัก</a>
</nav>
<?php if ($account): ?>
<header class="participant-profile-hero">
 <span class="participant-avatar" data-avatar="<?= participantEscape($profile['avatar']) ?>"><?php if ($hasPhoto): ?><img src="participant-photo.php" width="72" height="72" alt="รูปโปรไฟล์"><?php else: ?><?= workspaceIcon($profile['avatar']) ?><?php endif; ?></span>
 <div><p>บัญชีของฉัน</p><h1 class="title"><?= participantEscape($profile['display_name']?:($username??'สมาชิก Anemia Portal')) ?></h1>
 <?php if ($profile['profile_status']!==''): ?><span class="participant-profile-status"><?= workspaceIcon(['learning'=>'book','mission'=>'star','invite'=>'spark'][$profile['profile_status']]??'ready') ?> <?= participantEscape(ANEMIA_PROFILE_STATUSES[$profile['profile_status']]??'') ?></span><?php endif; ?></div>
 <button class="action" type="button" data-open-profile><?= workspaceIcon('edit') ?>แก้ไขโปรไฟล์</button>
</header>
<div class="participant-profile-points">
 <div class="participant-profile-tile"><span class="participant-reward-mark"><?= workspaceIcon('star') ?></span><span><small>แต้มสะสม</small><strong><?= number_format($teamPoints) ?> แต้ม</strong></span></div>
 <a class="participant-profile-tile participant-team-link" href="index.html?team=1<?= $invite!==''?'&amp;invite='.participantEscape($invite):'' ?>"><span class="participant-team-mark"><?= workspaceIcon('users') ?></span><span><strong>ทีมของฉัน</strong><small><?= $teamJoined?'ดูความคืบหน้าทีม':'สร้างทีมหรือชวนเพื่อน' ?></small></span><?= workspaceIcon('next') ?></a>
</div>
<?php else: ?>
<h1 class="title"><span class="iconbox"><?= workspaceIcon('person') ?></span>บัญชีของฉัน</h1>
<p>เก็บแต้มและกลับมาเรียนต่อบนเครื่องอื่น รับ 5 แต้มเมื่อเชื่อม LINE, Google หรือช่องทางภายนอกครั้งแรก</p>
<?php endif; ?>
<?php if ($notice!==''): ?><p class="status" role="status"><?= participantEscape($notice) ?></p><?php endif; ?>
<?php if (!$account): ?>
<section class="participant-signup">
 <?php
 $mode=$passwordMode;
 $register=$mode==='password-register';
 $migrate=$mode==='password-migrate';
 $accountModeUrl=static fn(string $mode): string => 'participant-account.php?'.http_build_query(array_filter([
     'mode'=>$mode,'invite'=>$invite,'return'=>$returnToPortal?'portal':''
 ]));
 ?>
 <nav class="participant-account-tabs" aria-label="เลือกวิธีเข้าใช้บัญชี">
  <a href="<?= participantEscape($accountModeUrl('password-login')) ?>" <?= $mode==='password-login'?'aria-current="page"':'' ?>><?= workspaceIcon('login') ?> เข้าสู่ระบบ</a>
  <a href="<?= participantEscape($accountModeUrl('password-register')) ?>" <?= $register?'aria-current="page"':'' ?>><?= workspaceIcon('users') ?> สมัครบัญชี</a>
 </nav>
 <div class="participant-entry-card">
  <?php if ($migrate): ?><h2 class="title"><?= workspaceIcon('lock') ?> เปลี่ยนรหัสผ่านเดิมเป็น PIN</h2><?php endif; ?>
  <form method="post" action="participant-account.php">
   <input type="hidden" name="csrf" value="<?= participantEscape($_SESSION['csrf']) ?>">
   <input type="hidden" name="invite" value="<?= participantEscape($invite) ?>">
   <?php if ($returnToPortal): ?><input type="hidden" name="return" value="portal"><?php endif; ?>
   <label><span><?= workspaceIcon('person') ?> ชื่อบัญชี</span>
    <input name="username" autocomplete="username" maxlength="40" required autocapitalize="none" spellcheck="false">
   </label>
   <?php foreach ($migrate?['password','new_pin','confirm_password']:($register?['password','confirm_password']:['password']) as $field):
      $confirm=$field==='confirm_password'; $id=$mode.'-'.$field; $legacy=$migrate&&$field==='password';
      $label=$legacy?'รหัสผ่านเดิม':($confirm?'ยืนยัน PIN':($mode==='password-login'?'PIN':'ตั้ง PIN 6 หลัก')); ?>
    <div class="participant-password-field">
     <label for="<?= $id ?>"><span><?= workspaceIcon($confirm?'ready':'lock') ?> <?= $label ?></span></label>
     <div class="participant-password-control">
      <input type="password" id="<?= $id ?>" name="<?= $field ?>" autocomplete="<?= $legacy||$mode==='password-login'?'current-password':'new-password' ?>" <?= !$legacy?'inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" data-pin':'' ?> required>
      <button type="button" data-password-visibility aria-controls="<?= $id ?>" aria-label="แสดง<?= $label ?>" aria-pressed="false" hidden>
       <span data-password-show><?= workspaceIcon('eye') ?></span><span data-password-hide hidden><?= workspaceIcon('eye-off') ?></span>
      </button>
     </div>
    </div>
   <?php endforeach; ?>
   <button class="action primary" name="provider" value="<?= $mode ?>"><?= workspaceIcon($register?'users':'login') ?><?= $register?'สมัครบัญชี':($migrate?'บันทึก PIN':'เข้าสู่ระบบ') ?></button>
  </form>
 </div>
 <?php if ($mode==='password-login'): ?><a class="participant-pin-migration" href="<?= participantEscape($accountModeUrl('password-migrate')) ?>"><?= workspaceIcon('lock') ?> เปลี่ยนรหัสผ่านเดิมเป็น PIN</a><?php endif; ?>
</section>
<?php endif; ?>
<?php if ($account): ?><details class="participant-settings"><summary><span><?= workspaceIcon('lock') ?> ตั้งค่าบัญชี</span><?= workspaceIcon('next') ?></summary><?php endif; ?>
<form method="post" action="participant-account.php" class="participant-provider-form">
 <h2 class="title"><?= $account?'ช่องทางที่เชื่อมกับบัญชี':'เข้าสู่ระบบด้วยบัญชีอื่น' ?></h2>
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
<?php if ($account): ?></details><?php endif; ?>
<?php if (!$returnToPortal && !$account): ?>
 <a class="action participant-team-return" href="index.html?team=1<?= $invite!==''?'&amp;invite='.participantEscape($invite):'' ?>"><?= workspaceIcon('back') ?>กลับไปที่ทีมและแต้ม</a>
<?php endif; ?>
</section></main>
<?php if ($account): ?>
<dialog class="workspace-dialog participant-profile-dialog" id="participant-profile-editor" aria-labelledby="participant-profile-title" data-reopen="<?= $profileEditing?'1':'0' ?>">
 <header class="workspace-dialog-heading"><h2 id="participant-profile-title"><?= workspaceIcon('edit') ?> แก้ไขโปรไฟล์</h2><form method="dialog"><button class="action" aria-label="ปิด"><?= workspaceIcon('close') ?></button></form></header>
 <form method="post" action="participant-account.php" class="participant-signup" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= participantEscape($_SESSION['csrf']) ?>">
  <?php if ($returnToPortal): ?><input type="hidden" name="return" value="portal"><?php endif; ?>
  <?php if ($profileEditing&&$notice!==''): ?><p role="status"><?= participantEscape($notice) ?></p><?php endif; ?>
  <div class="participant-avatar-preview" aria-label="ตัวอย่างไอคอน">
   <?php foreach (ANEMIA_PROFILE_AVATARS as $key=>$label): ?>
    <span class="participant-avatar" data-avatar="<?= $key ?>" data-preview-avatar <?= !$hasPhoto&&$profile['avatar']===$key?'':'hidden' ?>><?= workspaceIcon($key) ?></span>
   <?php endforeach; ?>
   <span class="participant-avatar" data-photo-preview <?= $hasPhoto?'':'hidden' ?>><img<?= $hasPhoto?' src="participant-photo.php"':'' ?> width="72" height="72" alt="ตัวอย่างรูปโปรไฟล์"></span>
  </div>
  <label><span><?= workspaceIcon('person') ?> อัปโหลดรูปโปรไฟล์</span><input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG หรือ WebP ขนาดไม่เกิน 5 MB · ตัดกลางเป็นรูปสี่เหลี่ยม</small></label>
  <?php if ($hasPhoto): ?><label class="participant-remove-photo"><input type="checkbox" name="remove_photo" value="1"> ใช้ไอคอนแทนรูป</label><?php endif; ?>
  <fieldset class="participant-avatar-picker"><legend>เลือกไอคอนโปรไฟล์</legend>
   <?php foreach (ANEMIA_PROFILE_AVATARS as $key=>$label): ?>
    <label><input type="radio" name="avatar" value="<?= $key ?>" <?= $profile['avatar']===$key?'checked':'' ?> required><span><?= workspaceIcon($key) ?> <?= participantEscape($label) ?></span></label>
   <?php endforeach; ?>
  </fieldset>
  <label>ชื่อที่แสดง<input name="display_name" maxlength="40" value="<?= participantEscape($profile['display_name']) ?>"></label>
  <label>สถานะ<select name="profile_status"><?php foreach (ANEMIA_PROFILE_STATUSES as $key=>$label): ?><option value="<?= $key ?>" <?= $profile['profile_status']===$key?'selected':'' ?>><?= participantEscape($label) ?></option><?php endforeach; ?></select></label>
  <button class="action primary" name="provider" value="profile-save"><?= workspaceIcon('save') ?>บันทึกโปรไฟล์</button>
 </form>
</dialog>
<?php endif; ?>
<script>
const profileEditor=document.querySelector('#participant-profile-editor');
if(profileEditor){
 const photo=profileEditor.querySelector('[name=profile_photo]');
 const remove=profileEditor.querySelector('[name=remove_photo]');
 const preview=profileEditor.querySelector('[data-photo-preview]');
 const previewImage=preview.querySelector('img');
 const existing=previewImage.getAttribute('src');
 let photoUrl;
 document.querySelector('[data-open-profile]').addEventListener('click',()=>profileEditor.showModal());
 if(profileEditor.dataset.reopen==='1')profileEditor.showModal();
 profileEditor.addEventListener('change',event=>{
  if(event.target===photo){
   if(photoUrl)URL.revokeObjectURL(photoUrl);
   photoUrl=photo.files[0]?URL.createObjectURL(photo.files[0]):null;
   if(photoUrl&&remove)remove.checked=false;
  }
  if(event.target===remove&&remove.checked&&photoUrl){
   URL.revokeObjectURL(photoUrl);
   photoUrl=null;
   photo.value='';
  }
  const url=photoUrl||(!remove?.checked&&existing);
  const avatar=profileEditor.querySelector('[name=avatar]:checked')?.value;
  preview.hidden=!url;
  if(url)previewImage.src=url;
  profileEditor.querySelectorAll('[data-preview-avatar]').forEach(icon=>icon.hidden=Boolean(url)||icon.dataset.avatar!==avatar);
 });
}
for(const input of document.querySelectorAll('[data-pin]')){
 input.addEventListener('input',()=>{
  const pin=input.value.replace(/[^0-9]/g,'').slice(0,6);
  if(pin!==input.value)input.value=pin;
 });
}
for(const button of document.querySelectorAll('[data-password-visibility]')){
 const input=document.getElementById(button.getAttribute('aria-controls'));
 button.hidden=false;
 button.addEventListener('click',()=>{
  const visible=input.type==='password';
  input.type=visible?'text':'password';
  button.setAttribute('aria-pressed',String(visible));
  button.querySelector('[data-password-show]').hidden=visible;
  button.querySelector('[data-password-hide]').hidden=!visible;
 });
}
</script>
</body></html>
