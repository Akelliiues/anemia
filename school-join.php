<?php
declare(strict_types=1);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
$secure=!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
if (!$secure && PHP_SAPI!=='cli-server') { http_response_code(403); exit; }
date_default_timezone_set('Asia/Bangkok');
define('ANEMIA_STAFF_BOOT',true);
require __DIR__.'/_private/participant-account.php';
require __DIR__.'/_private/database.php';
require __DIR__.'/_private/schools.php';
anemiaParticipantSession($secure);
$input=$_GET['code']??null;
$incoming=$input!==null;
if (is_string($input)) $input=strtolower(trim($input));
if ($incoming && is_string($input) && preg_match('/^[a-f0-9]{32}$/D',$input)) $_SESSION['school_invite']=$input;
$code=$incoming?$input:($_SESSION['school_invite']??'');
$valid=is_string($code) && preg_match('/^[a-f0-9]{32}$/D',$code);
$account=($_SESSION['until']??0)>=time()?($_SESSION['account']??null):null;
$epoch=(int)($_SESSION['epoch']??0);
$csrf=$_SESSION['csrf']??=bin2hex(random_bytes(32));
$cohort=null;
$open=false;
$joined=false;
$notice='';
if ($valid) {
    try {
        $config=require __DIR__.'/_private/config.php';
        $db=anemiaDatabase($config);
        initializeAnemia($db,$config);
        anemiaEnsureSchools($db);
        $posting=$_SERVER['REQUEST_METHOD']==='POST';
        if ($posting && (!is_string($_POST['csrf']??null) || !hash_equals($csrf,$_POST['csrf']))) {
            http_response_code(403); exit('คำขอหมดอายุ');
        }
        if ($posting) {
            usageAuditEnsure($db);
            $db->beginTransaction();
        }
        if (!is_string($account) || anemiaParticipantEpoch($db,$account)!==$epoch) $account=null;
        $state=schoolJoinState($db,$code,$account,$posting,date('Y-m-d'));
        ['cohort'=>$cohort,'open'=>$open,'joined'=>$joined]=$state;
        if ($posting) {
            schoolConfirmEnrollment($db,$state,$account);
            if (!$joined) {
                usageAuditWrite($db,['username'=>'','role'=>'participant'],'enrollment','school-join',usageAuditSchool($db,(int)$cohort['school_id']),null,(int)$cohort['id']);
            }
            $db->commit();
            unset($_SESSION['school_invite']);
            header('Location: participant-programme.php?room='.(int)$cohort['id'],true,303); exit;
        }
    } catch(DomainException $error) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        $notice=$error->getMessage();
    } catch(Throwable $error) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        error_log('Anemia school join failed: '.get_class($error));
        $notice='ยังเปิดรุ่นกิจกรรมไม่ได้ กรุณาลองอีกครั้ง';
    }
} elseif ($incoming) {
    $notice='กรุณาตรวจรหัสเข้าร่วมกับครู';
}
?>
<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>เข้าร่วมกิจกรรมโรงเรียน · Anemia Portal</title>
<script src="theme.js?v=5"></script><script defer src="installed-update.js"></script><link rel="stylesheet" href="style.css?v=school-v1">
<body class="public-site"><main><section class="panel">
 <a href="index.html">กลับหน้าหลัก</a><h1 class="title">เข้าร่วมกิจกรรมโรงเรียน</h1>
 <?php if ($notice!==''): ?><p role="status"><?= schoolEscape($notice) ?></p><?php endif; ?>
 <?php if (!$cohort): ?>
  <form class="network-form" method="get"><label>รหัสจากครู<input name="code" maxlength="32" required></label><button class="action primary">ตรวจรหัส</button></form>
  <p><a href="participant-account.php?return=school&amp;method=staff">เข้าสู่ระบบสำหรับครู</a></p>
 <?php else: ?>
  <h2><?= schoolEscape($cohort['name']) ?></h2>
  <p><?= schoolEscape($cohort['title'].' · '.$cohort['classroom'].($cohort['academic_year']?' · ปีการศึกษา '.$cohort['academic_year']:'')) ?></p>
  <?php if ($joined): ?><a class="action primary" href="participant-programme.php?room=<?= (int)$cohort['id'] ?>">เรียนต่อ</a>
  <?php elseif (!$open): ?><p>รุ่นนี้ยังไม่เปิดรับผู้เข้าร่วม</p>
  <?php else: ?>
   <p>เมื่อเข้าร่วม ครูผู้ดูแลจะเห็นชื่อที่แสดงในโปรไฟล์และความคืบหน้าการเรียนของคุณ</p>
   <?php if (!$account): ?><a class="action primary" href="participant-account.php?return=school">เข้าสู่ระบบเพื่อเข้าร่วม</a>
   <?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?= schoolEscape($csrf) ?>"><button class="action primary">ยืนยันเข้าร่วม</button></form><?php endif; ?>
  <?php endif; ?>
 <?php endif; ?>
</section></main></body></html>
