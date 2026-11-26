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
$returnContext=$_REQUEST['return']??'';
$returnContext=in_array($returnContext,['portal','programme'],true)?$returnContext:'';
$returnToPortal=$returnContext!=='';
$accountReturnUrl=static fn(): string => 'participant-account.php'.($returnContext!==''?'?return='.$returnContext:'');
$participantDestination=static function(string $context,string $invite): string {
    return match ($context) {
        'programme'=>'participant-programme.php',
        'portal'=>'index.html',
        default=>'index.html?team=1'.($invite!==''?'&invite='.$invite:''),
    };
};

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
            header('Location: '.$accountReturnUrl(),true,303); exit;
        }
        if ($key==='logout') {
            $_SESSION=[];
            session_regenerate_id(true);
            header('Location: '.$accountReturnUrl(),true,303); exit;
        }
        if (in_array($key,['password-login','password-register','password-migrate'],true)) {
            if ($account!==null) throw new DomainException('กรุณาออกจากระบบก่อน');
            $passwordMode=$key;
            $db=participantDb();
            if (!consumeAnemiaAttempt($db,'participant-password:'.($_SERVER['REMOTE_ADDR']??'unknown'))) {
                http_response_code(429);
                throw new DomainException('ลองหลายครั้งเกินไป กรุณารอสักครู่');
            }
            $account=anemiaParticipantPassword($db,$key==='password-register',$_POST,anemiaTeamCookieHash(),$key==='password-migrate');
            anemiaStartParticipantSession($db,$account);
            $target=$participantDestination($returnContext,$invite);
            header('Location: '.$target,true,303); exit;
        }
        if (!is_string($key)||!$canConnect($key)) throw new DomainException('ช่องทางนี้ยังไม่เปิดใช้งาน');
        [, $authorize, , $scope,$client]=$providers[$key];
        $state=bin2hex(random_bytes(24));
        $nonce=bin2hex(random_bytes(24));
        $verifier=bin2hex(random_bytes(32));
        $_SESSION['oauth']=['state'=>$state,'provider'=>$key,'until'=>time()+600,'account'=>$account,'invite'=>$invite,'nonce'=>$nonce,'verifier'=>$verifier,'return_context'=>$returnContext];
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
        $returnContext=$flow['return_context']??(!empty($flow['portal'])?'portal':'');
        $returnToPortal=$returnContext!=='';
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
        $target=$participantDestination($returnContext,$invite);
        header('Location: '.$target,true,303); exit;
    }
} catch (Throwable $error) {
    if (!$error instanceof DomainException) error_log('Anemia participant OAuth: '.get_class($error));
    $notice=$error instanceof DomainException?$error->getMessage():'เชื่อมบัญชีไม่สำเร็จ กรุณาลองอีกครั้ง';
}
$linked=[];
$username=null;
$profile=['avatar'=>'drop','display_name'=>'','profile_status'=>''];
$activity=['events'=>[],'points'=>0,'joined'=>false,'team_finished'=>0];
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
        $activity=anemiaParticipantActivity($db,$account);
        if (!empty($_SESSION['profile_saved'])) { $notice='บันทึกโปรไฟล์แล้ว'; unset($_SESSION['profile_saved']); }
    } catch (Throwable $error) { $notice='โหลดข้อมูลบัญชีไม่สำเร็จ'; }
}
?>
<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $account?'บัญชีของฉัน':'เข้าสู่ระบบ' ?> · Anemia Portal</title><script src="theme.js?v=1"></script><script defer src="installed-update.js"></script><link rel="stylesheet" href="style.css?v=portal-login-v67">
<body class="<?= $account?'participant-profile-page':'staff-access participant-login-page' ?>">
<main class="<?= $account?'participant-account-main':'staff-access-main' ?>">
<?php if (!$account): ?>
<div class="staff-access-shell participant-login-shell">
 <aside class="staff-access-cover" aria-label="Anemia Portal">
  <div class="staff-cover-copy">
   <span class="staff-cover-mark" aria-hidden="true"><?= workspaceIcon('drop') ?></span>
   <strong>Anemia Portal</strong>
   <p>ประเมินความเสี่ยง เรียนรู้ และติดตามการดูแลเรื่องโลหิตจาง</p>
  </div>
  <div class="staff-cover-visual" aria-hidden="true"><i></i><i></i><img src="hero-women.webp" alt="" width="520" height="337"></div>
  <p class="staff-cover-agency">ศูนย์อนามัยที่ ๑๐ จังหวัดอุบลราชธานี</p>
 </aside>
<?php endif; ?>
<section class="<?= $account?'panel participant-account':'staff-access-panel participant-account' ?>">
<nav class="participant-account-return" aria-label="ทางกลับหน้าหลัก">
 <?php if ($returnContext==='programme'): ?><a href="participant-programme.php"><?= workspaceIcon('back') ?>กลับไปที่กิจกรรม 12 สัปดาห์</a>
 <?php else: ?><a href="index.html"><?= workspaceIcon('back') ?>กลับหน้าหลัก</a><?php endif; ?>
</nav>
<?php if ($account): ?>
<header class="participant-profile-hero">
 <?php if ($hasPhoto): ?>
 <button type="button" class="participant-avatar participant-photo-button" data-open-photo aria-haspopup="dialog" aria-label="ดูรูปโปรไฟล์ขนาดใหญ่"><img src="participant-photo.php" width="72" height="72" alt=""></button>
 <?php else: ?><span class="participant-avatar" data-avatar="<?= participantEscape($profile['avatar']) ?>"><?= workspaceIcon($profile['avatar']) ?></span><?php endif; ?>
 <div><p>บัญชีของฉัน</p><h1 class="title"><?= participantEscape($profile['display_name']?:($username??'สมาชิก Anemia Portal')) ?></h1>
 <?php if ($profile['profile_status']!==''): ?><span class="participant-profile-status"><?= workspaceIcon(['learning'=>'book','mission'=>'star','invite'=>'spark'][$profile['profile_status']]??'ready') ?> <?= participantEscape(ANEMIA_PROFILE_STATUSES[$profile['profile_status']]??'') ?></span><?php endif; ?></div>
 <button class="action" type="button" data-open-profile><?= workspaceIcon('edit') ?>แก้ไขโปรไฟล์</button>
</header>
<div class="participant-profile-points">
 <div class="participant-profile-tile"><span class="participant-reward-mark"><?= workspaceIcon('star') ?></span><span><small>แต้มสะสม</small><strong><?= number_format($activity['points']) ?> แต้ม</strong></span></div>
 <a class="participant-profile-tile participant-team-link" href="index.html?team=1<?= $invite!==''?'&amp;invite='.participantEscape($invite):'' ?>"><span class="participant-team-mark"><?= workspaceIcon('users') ?></span><span><strong>ทีมของฉัน</strong><small><?= $activity['joined']?'ดูความคืบหน้าทีม':'สร้างทีมหรือชวนเพื่อน' ?></small></span><?= workspaceIcon('next') ?></a>
 <a class="participant-profile-tile participant-team-link" href="participant-programme.php"><span class="participant-team-mark"><?= workspaceIcon('book') ?></span><span><strong>กิจกรรม 12 สัปดาห์</strong><small>ดูแบบประเมินและความคืบหน้า</small></span><?= workspaceIcon('next') ?></a>
</div>
<section class="participant-activity-card" aria-labelledby="participant-activity-title">
 <h2 id="participant-activity-title"><?= workspaceIcon('history') ?> กิจกรรมของฉัน</h2>
 <?php if (!$activity['events']): ?><p>ยังไม่มีรายการกิจกรรม</p><?php else: ?>
 <ol>
  <?php foreach ($activity['events'] as $event): ?>
   <li><span class="participant-team-mark"><?= workspaceIcon($event['icon']) ?></span>
    <span><strong><?= participantEscape($event['title']) ?></strong><small><?= participantEscape(anemiaParticipantActivityDate($event['date'])) ?></small></span>
    <?php if ($event['points']>0): ?><span class="participant-activity-award">+<?= $event['points'] ?> แต้ม</span><?php endif; ?>
   </li>
  <?php endforeach; ?>
 </ol>
 <?php endif; ?>
</section>
<?php if ($activity['team_finished']>=5): ?>
 <section class="participant-profile-tile" aria-label="เหรียญที่ได้รับ"><span class="participant-reward-mark"><?= workspaceIcon('star') ?></span><span><strong>เหรียญส่งต่อความรู้</strong><small><?= $activity['team_finished']>=10?'ทีมถึงเป้าหมาย 10 คนแล้ว':'ทีมเรียนจบอย่างน้อย 5 คน' ?></small></span></section>
<?php endif; ?>
<?php else: ?>
<h1>เข้าสู่ระบบ</h1>
<p class="staff-access-description" data-login-description>ใช้ PIN สมาชิกเพื่อดูแต้มและกลับมาเรียนต่อ</p>
<?php endif; ?>
<?php if ($notice!==''): ?><p class="status" role="status"><?= participantEscape($notice) ?></p><?php endif; ?>
<?php if (!$account): ?>
<section class="participant-signup">
 <?php
 $mode=$passwordMode;
 $register=$mode==='password-register';
 $migrate=$mode==='password-migrate';
 $accountModeUrl=static fn(string $mode): string => 'participant-account.php?'.http_build_query(array_filter([
     'mode'=>$mode,'invite'=>$invite,'return'=>$returnContext
 ]));
 ?>
 <nav class="participant-account-tabs" aria-label="เลือกวิธีเข้าสู่ระบบ">
  <a data-pin-mode href="<?= participantEscape($accountModeUrl('password-login')) ?>" <?= $mode==='password-login'?'aria-current="page"':'' ?>><?= workspaceIcon('login') ?> PIN สมาชิก</a>
  <a data-staff-mode href="login.php?entry=portal"><?= workspaceIcon('lock') ?> รหัสผ่านเจ้าหน้าที่</a>
 </nav>
 <div data-member-login>
 <div class="participant-entry-card">
  <?php if ($migrate): ?><h2 class="title"><?= workspaceIcon('lock') ?> เปลี่ยนรหัสผ่านเดิมเป็น PIN</h2><?php endif; ?>
  <form method="post" action="participant-account.php">
   <input type="hidden" name="csrf" value="<?= participantEscape($_SESSION['csrf']) ?>">
   <input type="hidden" name="invite" value="<?= participantEscape($invite) ?>">
   <?php if ($returnToPortal): ?><input type="hidden" name="return" value="<?= participantEscape($returnContext) ?>"><?php endif; ?>
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
 <?php if ($mode==='password-login'): ?>
  <div class="actions">
   <a class="action" href="<?= participantEscape($accountModeUrl('password-register')) ?>"><?= workspaceIcon('users') ?> สมัครบัญชี</a>
   <a class="participant-pin-migration" href="<?= participantEscape($accountModeUrl('password-migrate')) ?>"><?= workspaceIcon('lock') ?> เปลี่ยนรหัสผ่านเดิมเป็น PIN</a>
  </div>
 <?php endif; ?>
 </div>
 <div data-staff-login hidden>
  <div class="participant-entry-card">
   <form method="post" action="login.php?entry=inline" aria-label="เข้าสู่ระบบเจ้าหน้าที่">
    <input type="hidden" name="action" value="login">
    <label><span><?= workspaceIcon('person') ?> ชื่อผู้ใช้</span><input name="username" autocomplete="username" maxlength="40" required autocapitalize="none" spellcheck="false"></label>
    <label for="inline-staff-password"><?= workspaceIcon('lock') ?> รหัสผ่าน</label>
    <div class="participant-password-control">
     <input id="inline-staff-password" type="password" name="password" autocomplete="current-password" maxlength="128" required>
     <button type="button" data-password-visibility aria-controls="inline-staff-password" aria-label="แสดงรหัสผ่าน" aria-pressed="false" hidden><span data-password-show><?= workspaceIcon('eye') ?></span><span data-password-hide hidden><?= workspaceIcon('eye-off') ?></span></button>
    </div>
    <button class="action primary" type="submit"><?= workspaceIcon('login') ?> เข้าสู่ระบบ</button>
    <p role="status" data-staff-status hidden></p>
   </form>
  </div>
 </div>
</section>
<?php endif; ?>
<?php if ($account): ?><details class="participant-settings" id="participant-settings"><summary><span><?= workspaceIcon('lock') ?> ตั้งค่าบัญชี</span><?= workspaceIcon('next') ?></summary><?php endif; ?>
<form method="post" action="participant-account.php" class="participant-provider-form">
 <h2 class="title"><?= $account?'ช่องทางที่เชื่อมกับบัญชี':'เข้าสู่ระบบด้วยบัญชีอื่น' ?></h2>
 <input type="hidden" name="csrf" value="<?= participantEscape($_SESSION['csrf']) ?>">
 <input type="hidden" name="invite" value="<?= participantEscape($invite) ?>">
 <?php if ($returnToPortal): ?><input type="hidden" name="return" value="<?= participantEscape($returnContext) ?>"><?php endif; ?>
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
 <?php if ($account): ?><div class="actions"><a class="action" href="login.php?entry=portal"><?= workspaceIcon('lock') ?> เข้าสู่ระบบเจ้าหน้าที่</a><button class="action" name="provider" value="logout"><?= workspaceIcon('logout') ?>ออกจากระบบ</button></div><?php endif; ?>
</form>
<?php if ($account): ?></details><?php endif; ?>
<?php if (!$returnToPortal && !$account): ?>
 <a class="action participant-team-return" href="index.html?team=1<?= $invite!==''?'&amp;invite='.participantEscape($invite):'' ?>"><?= workspaceIcon('back') ?>กลับไปที่ทีมและแต้ม</a>
<?php endif; ?>
</section><?php if (!$account): ?></div><?php endif; ?></main>
<?php if ($account && $hasPhoto): ?>
<dialog class="workspace-dialog participant-photo-dialog" id="participant-photo-view" aria-labelledby="participant-photo-title">
 <header class="workspace-dialog-heading"><h2 id="participant-photo-title">รูปโปรไฟล์</h2><form method="dialog"><button class="action" aria-label="ปิดรูปโปรไฟล์"><?= workspaceIcon('close') ?></button></form></header>
 <img width="256" height="256" alt="รูปโปรไฟล์ของฉัน">
</dialog>
<?php endif; ?>
<?php if ($account): ?>
<dialog class="workspace-dialog participant-profile-dialog" id="participant-profile-editor" aria-labelledby="participant-profile-title" data-reopen="<?= $profileEditing?'1':'0' ?>">
 <header class="workspace-dialog-heading"><h2 id="participant-profile-title"><?= workspaceIcon('edit') ?> แก้ไขโปรไฟล์</h2><form method="dialog"><button class="action" aria-label="ปิด"><?= workspaceIcon('close') ?></button></form></header>
 <form method="post" action="participant-account.php" class="participant-signup" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= participantEscape($_SESSION['csrf']) ?>">
  <?php if ($returnToPortal): ?><input type="hidden" name="return" value="<?= participantEscape($returnContext) ?>"><?php endif; ?>
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
const staffMode=document.querySelector('[data-staff-mode]');
const pinMode=document.querySelector('[data-pin-mode]');
if(staffMode&&pinMode){
 const staffPanel=document.querySelector('[data-staff-login]');
 const memberPanel=document.querySelector('[data-member-login]');
 const providers=document.querySelector('.participant-provider-form');
 const description=document.querySelector('[data-login-description]');
 const form=staffPanel.querySelector('form');
 const endpoint=form.getAttribute('action');
 const status=form.querySelector('[data-staff-status]');
 const submit=form.querySelector('[type=submit]');
 const message=text=>{status.textContent=text;status.hidden=!text;};
 const normalPin=pinMode.hasAttribute('aria-current');
 const selectMode=staff=>{
  staffPanel.hidden=!staff;
  memberPanel.hidden=staff;
  providers.hidden=staff;
  description.textContent=staff?'ใช้ชื่อผู้ใช้และรหัสผ่านเจ้าหน้าที่เพื่อเปิดแดชบอร์ด':'ใช้ PIN สมาชิกเพื่อดูแต้มและกลับมาเรียนต่อ';
  staffMode.toggleAttribute('aria-current',staff);
  pinMode.toggleAttribute('aria-current',!staff&&normalPin);
  if(staff)staffMode.setAttribute('aria-current','page');
  if(!staff&&normalPin)pinMode.setAttribute('aria-current','page');
  if(!staff)form.elements.password.value='';
 };
 staffMode.addEventListener('click',event=>{event.preventDefault();selectMode(true);});
 pinMode.addEventListener('click',event=>{
  if(!normalPin)return;
  event.preventDefault();selectMode(false);
 });
 form.addEventListener('submit',async event=>{
  event.preventDefault();
  const body=new FormData(form);
  submit.disabled=true;
  message('กำลังตรวจบัญชีเจ้าหน้าที่…');
  try{
   const load=await fetch(endpoint,{cache:'no-store'});
   const session=await load.json();
   if(session.destination){location.assign(session.destination);return;}
   if(!session.csrf){message(session.message||'เปิดการเข้าสู่ระบบไม่ได้ กรุณาลองใหม่');return;}
   body.set('csrf',session.csrf);
   const response=await fetch(endpoint,{method:'POST',body,cache:'no-store'});
   const result=await response.json();
   if(result.destination){location.assign(result.destination);return;}
   message(result.message||'เข้าสู่ระบบไม่ได้ กรุณาลองใหม่');
  }catch{message('ติดต่อระบบไม่ได้ กรุณาลองใหม่');}
  finally{submit.disabled=false;}
 });
}
const photoButton=document.querySelector('[data-open-photo]');
const photoDialog=document.querySelector('#participant-photo-view');
if(photoButton&&photoDialog){
 photoButton.addEventListener('click',()=>{
  const image=photoDialog.querySelector('img');
  if(!image.hasAttribute('src'))image.src=photoButton.querySelector('img').src;
  photoDialog.showModal();
 });
 photoDialog.addEventListener('click',event=>{
  if(event.target!==photoDialog)return;
  const bounds=photoDialog.getBoundingClientRect();
  if(event.clientX<bounds.left||event.clientX>bounds.right||event.clientY<bounds.top||event.clientY>bounds.bottom)photoDialog.close();
 });
 photoDialog.addEventListener('close',()=>photoButton.focus({preventScroll:true}));
}
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
if(location.hash==='#participant-settings'){
 const settings=document.getElementById('participant-settings');
 if(settings){settings.open=true;settings.scrollIntoView({block:'start'});}
}
</script>
</body></html>
