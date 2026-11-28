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
    if ($identity['role']!=='school_teacher' && $_SERVER['REQUEST_METHOD']!=='POST') {
        $query=['view'=>'schools']+array_intersect_key($_GET,array_flip(['region','province','school','cohort','district','q','school_tab','year','school_status','room_year','unassigned','directory_page']));
        header('Location: dashboard.php?'.http_build_query($query),true,303); exit;
    }
    $renderSchools=require __DIR__.'/_private/school-view.php';
    $csrf=$_SESSION['csrf']??=bin2hex(random_bytes(32));
    ob_start();
    $renderSchools($db,$identity);
    $schoolContent=ob_get_clean();
} catch(Throwable $error) {
    http_response_code(503); error_log('Anemia schools unavailable: '.get_class($error)); exit('ยังเปิดกิจกรรมโรงเรียนไม่ได้ กรุณาลองอีกครั้ง');
}

?>
<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>กิจกรรมโรงเรียน · Anemia Portal</title>
<script src="theme.js?v=5"></script><script defer src="installed-update.js"></script><link rel="stylesheet" href="style.css?v=school-v4">
<script defer src="qrcode.js?v=2.0.4"></script><script defer src="school-qr.js?v=5"></script>
<body class="staff-dashboard-page"><main class="school-workspace hosted-workspace">
<header class="admin-top no-print">
 <div class="workspace-header-art" aria-hidden="true"></div>
 <div class="admin-top-main">
  <div class="admin-brand"><span class="workspace-brand-icon"><?= workspaceIcon('drop') ?></span><div><strong>Anemia Portal</strong><span class="workspace-brand-caption">พื้นที่ครู</span></div></div>
  <div class="workspace-account">
   <div class="workspace-identity"><span class="workspace-account-icon"><?= workspaceIcon('users') ?></span><span><strong><?= schoolEscape($identity['username']) ?></strong><small>ครูผู้ดูแลกิจกรรม</small></span></div>
   <form class="hosted-logout" method="post" action="login.php"><input type="hidden" name="csrf" value="<?= schoolEscape($csrf) ?>"><button class="action hosted-logout-button" name="action" value="logout" aria-label="ออกจากระบบ" title="ออกจากระบบ"><?= workspaceIcon('logout') ?></button></form>
  </div>
 </div>
</header>
<section class="card school-page-intro"><h1 class="title">กิจกรรมโรงเรียน</h1></section>
<?= $schoolContent ?>
</main>
<?php require __DIR__ . '/_private/staff-footer.php'; ?>
</body></html>
