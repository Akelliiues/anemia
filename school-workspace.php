<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$secure=!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
if (!$secure && PHP_SAPI!=='cli-server') { http_response_code(403); exit; }
date_default_timezone_set('Asia/Bangkok');
session_name('anemia_staff_session');
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);
session_start();
if (!is_string($_SESSION['username']??null) || ($_SESSION['until']??0)<time()) {
    header('Location: participant-account.php?return=school&method=staff',true,303); exit;
}
define('ANEMIA_STAFF_BOOT',true);
require __DIR__.'/_private/database.php';
require __DIR__.'/_private/access.php';
require __DIR__.'/_private/icons.php';
try {
    $config=require __DIR__.'/_private/config.php';
    $db=anemiaDatabase($config);
    initializeAnemia($db,$config);
    $identity=anemiaStaffIdentity($db,$_SESSION['username']);
    if (!$identity) { http_response_code(403); exit('บัญชีนี้เข้าใช้งานไม่ได้'); }
    $actor=$identity;
    $preview=array_key_exists('preview_teacher',$_GET);
    if ($preview) {
        if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); exit('มุมมองครูใช้สำหรับเปิดดูเท่านั้น'); }
        require_once __DIR__.'/_private/schools.php';
        anemiaEnsureSchools($db);
        $teacher=$_GET['preview_teacher'];
        $schoolId=filter_input(INPUT_GET,'school',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $school=is_string($teacher) && strlen($teacher)<=40 && $schoolId ? usageTeacherPreview($db,$actor,$schoolId,$teacher):null;
        if (!$school) { http_response_code(403); exit('บัญชีนี้ไม่มีสิทธิ์ดูมุมมองครูที่เลือก'); }
        usageAuditEnsure($db);
        usageAuditWrite($db,$actor,'teacher-preview','teacher-workspace',$school,$school['username']);
        $identity=['username'=>$school['username'],'role'=>'school_teacher'];
        $back='dashboard.php?'.http_build_query(['view'=>'schools','region'=>$school['region'],'province'=>$school['province_code'],'school'=>$schoolId]).'#school-activities';
    } elseif ($identity['role']!=='school_teacher' && $_SERVER['REQUEST_METHOD']!=='POST') {
        $query=['view'=>'schools']+array_intersect_key($_GET,array_flip(['region','province','school','cohort','district','q','school_tab','year','school_status','room_year','unassigned','directory_page']));
        header('Location: dashboard.php?'.http_build_query($query),true,303); exit;
    }
    $renderSchools=require __DIR__.'/_private/school-view.php';
    $csrf=$_SESSION['csrf']??=bin2hex(random_bytes(32));
    $teacherBrand=static function () use ($actor,$preview,$csrf): void { ?>
     <div class="admin-top-main teacher-brand-row">
      <div class="admin-brand"><span class="workspace-brand-icon"><?= workspaceIcon('drop') ?></span><div><strong>Anemia Portal</strong><span class="workspace-brand-caption">จัดการกิจกรรมโรงเรียน</span></div></div>
      <div class="workspace-account">
       <div class="workspace-identity"><span class="workspace-account-icon"><?= workspaceIcon('person') ?></span><span><strong><?= schoolEscape($actor['username']) ?></strong><small><?= $preview?'บัญชีผู้ดูแล':'บัญชีครู' ?></small></span></div>
       <form class="hosted-logout" method="post" action="login.php"><input type="hidden" name="csrf" value="<?= schoolEscape($csrf) ?>"><button class="action hosted-logout-button" name="action" value="logout" aria-label="ออกจากระบบ" title="ออกจากระบบ"><?= workspaceIcon('logout') ?></button></form>
      </div>
     </div>
    <?php };
    ob_start();
    $renderSchools($db,$identity,$preview?(int)$schoolId:null,$teacherBrand);
    $schoolContent=ob_get_clean();
} catch(Throwable $error) {
    http_response_code(503); error_log('Anemia schools unavailable: '.get_class($error)); exit('ยังเปิดกิจกรรมโรงเรียนไม่ได้ กรุณาลองอีกครั้ง');
}

?>
<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>พื้นที่ครู · Anemia Portal</title>
<script src="theme.js?v=5"></script><script defer src="installed-update.js"></script><link rel="stylesheet" href="style.css?v=school-v8">
<link rel="stylesheet" href="teacher-workspace.css?v=4">
<script defer src="qrcode.js?v=2.0.4"></script><script defer src="school-qr.js?v=7"></script>
<body class="staff-dashboard-page teacher-page"><main class="school-workspace hosted-workspace teacher-workspace">
<?php if ($preview): ?>
<section class="card teacher-preview-banner" aria-label="สถานะมุมมองครู">
 <div><strong><?= workspaceIcon('eye') ?> กำลังดูในมุมมองครู</strong><p><?= schoolEscape($identity['username']) ?> · อ่านอย่างเดียว</p></div>
 <a class="action" href="<?= schoolEscape($back) ?>"><?= workspaceIcon('back') ?> กลับสู่หลังบ้าน</a>
</section>
<?php endif; ?>
<?= $schoolContent ?>
<script>
{
 const navigation=document.querySelector('.teacher-tool-nav');
 if(navigation){
  const buttons=[...navigation.querySelectorAll('button')];
  const panels=buttons.map(button=>document.getElementById(button.getAttribute('aria-controls')));
  if(panels.every(Boolean)){
   const show=button=>buttons.forEach((item,index)=>{
    const selected=item===button;
    item.setAttribute('aria-pressed',String(selected));
    panels[index].hidden=!selected;
   });
   buttons.forEach(button=>button.addEventListener('click',()=>show(button)));
   show(buttons[0]);
   navigation.hidden=false;
  }
 }
}
</script>
</main>
<?php require __DIR__ . '/_private/staff-footer.php'; ?>
</body></html>
