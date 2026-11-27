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
    header('Location: login.php?next=schools',true,303); exit;
}
define('ANEMIA_STAFF_BOOT',true);
require __DIR__.'/_private/database.php';
require __DIR__.'/_private/access.php';
require __DIR__.'/_private/schools.php';
require __DIR__.'/_private/participant-programme.php';
require __DIR__.'/_private/icons.php';
try {
    $config=require __DIR__.'/_private/config.php';
    $db=anemiaDatabase($config);
    initializeAnemia($db,$config);
    $identity=anemiaStaffIdentity($db,$_SESSION['username']);
    if (!$identity) { http_response_code(403); exit('บัญชีนี้เข้าใช้งานไม่ได้'); }
    anemiaEnsureSchools($db);
    $admin=$identity['role']==='account_admin';
    $teacher=$identity['role']==='school_teacher';
    $csrf=$_SESSION['csrf']??=bin2hex(random_bytes(32));
    $notice=$_SESSION['school_notice']??'';
    unset($_SESSION['school_notice']);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!is_string($_POST['csrf']??null) || !hash_equals($csrf,$_POST['csrf'])) { http_response_code(403); exit('คำขอหมดอายุ'); }
        try {
            schoolSave($db,$identity,$_POST);
            $_SESSION['school_notice']='บันทึกการเปลี่ยนแปลงแล้ว';
        } catch (DomainException $error) {
            $_SESSION['school_notice']=$error->getMessage();
        } catch (PDOException $error) {
            $_SESSION['school_notice']='บันทึกไม่ได้ กรุณาตรวจว่าข้อมูลซ้ำหรือไม่';
            error_log('Anemia school save SQLSTATE '.$error->getCode());
        }
        $schoolId=filter_input(INPUT_POST,'school',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        header('Location: school-workspace.php'.($schoolId?'?school='.$schoolId:''),true,303); exit;
    }
    [$scope,$params]=schoolScope($identity);
    $query=$db->prepare("SELECT s.*,p.name AS province FROM anemia_school s JOIN anemia_province p ON p.code=s.province_code WHERE $scope ORDER BY p.name,s.name");
    $query->execute($params);
    $schools=array_column($query->fetchAll(PDO::FETCH_ASSOC),null,'id');
    $schoolId=filter_input(INPUT_GET,'school',FILTER_VALIDATE_INT);
    $school=$schools[$schoolId]??($schools[array_key_first($schools)]??null);
    $cohorts=[];
    $members=[];
    $assignments=[];
    if ($school) {
        $ownerFilter=$teacher?' AND c.teacher=?':'';
        $query=$db->prepare("SELECT c.*, COUNT(m.account_id) AS members, SUM(a.account_id IS NOT NULL) AS completed
            FROM anemia_school_cohort c LEFT JOIN anemia_school_member m ON m.cohort_id=c.id
            LEFT JOIN anemia_participant_programme_assessment a ON a.account_id=m.account_id AND a.programme_version=c.programme_version
            AND a.instrument_version=? AND a.phase='after'
            WHERE c.school_id=?$ownerFilter GROUP BY c.id ORDER BY c.id DESC");
        $query->execute([ANEMIA_PROGRAMME_INSTRUMENT_VERSION,$school['id'],...($teacher?[$identity['username']]:[])]);
        $cohorts=array_column($query->fetchAll(PDO::FETCH_ASSOC),null,'id');
        $cohortId=filter_input(INPUT_GET,'cohort',FILTER_VALIDATE_INT);
        if (($admin||$teacher) && isset($cohorts[$cohortId])) {
            $query=$db->prepare("SELECT COALESCE(NULLIF(p.display_name,''),'') AS display_name, COUNT(DISTINCT w.week_no) AS weeks,
                MAX(a.phase='after') AS completed, MAX(a.phase='before') AS started
                FROM anemia_school_member m JOIN anemia_school_cohort c ON c.id=m.cohort_id
                LEFT JOIN anemia_participant_profile p ON p.account_id=m.account_id
                LEFT JOIN anemia_participant_programme_progress w ON w.account_id=m.account_id AND w.programme_version=c.programme_version
                LEFT JOIN anemia_participant_programme_assessment a ON a.account_id=m.account_id AND a.programme_version=c.programme_version AND a.instrument_version=?
                WHERE c.id=? GROUP BY m.account_id,p.display_name,m.joined_at ORDER BY m.joined_at,m.account_id");
            $query->execute([ANEMIA_PROGRAMME_INSTRUMENT_VERSION,$cohortId]);
            $members=$query->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($admin) {
            $query=$db->prepare("SELECT t.username FROM anemia_school_teacher t JOIN anemia_staff u ON u.username=t.username WHERE school_id=? AND u.enabled=1 AND u.role='school_teacher' ORDER BY t.username");
            $query->execute([$school['id']]);
            $assignments=$query->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    $provinces=$admin?$db->query('SELECT code,name FROM anemia_province ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR):[];
    $audit=[];
    if($admin && $school) {
        $query=$db->prepare('SELECT actor,action_name,created_at FROM anemia_school_audit WHERE school_id=? ORDER BY id DESC LIMIT 20');
        $query->execute([$school['id']]);
        $audit=$query->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(Throwable $error) {
    http_response_code(503); error_log('Anemia schools unavailable: '.get_class($error)); exit('ยังเปิดกิจกรรมโรงเรียนไม่ได้ กรุณาลองอีกครั้ง');
}

function schoolFields(string $csrf,string $action,?int $school=null,?int $cohort=null): void
{
    ?>
    <input type="hidden" name="csrf" value="<?= schoolEscape($csrf) ?>">
    <input type="hidden" name="school_action" value="<?= schoolEscape($action) ?>">
    <?php if ($school): ?><input type="hidden" name="school" value="<?= $school ?>"><?php endif; ?>
    <?php if ($cohort): ?><input type="hidden" name="cohort" value="<?= $cohort ?>"><?php endif; ?>
    <?php
}
function schoolCohortFields(array $cohort=[]): void
{
    ?>
    <label>ชื่อรุ่น<input name="title" maxlength="100" value="<?= schoolEscape($cohort['title']??'') ?>" required></label>
    <label>ห้องเรียน<input name="classroom" maxlength="80" value="<?= schoolEscape($cohort['classroom']??'') ?>" required></label>
    <label>เปิดรับ<input type="date" name="opens" value="<?= schoolEscape($cohort['opens_on']??date('Y-m-d')) ?>" required></label>
    <label>ปิดรับ<input type="date" name="closes" value="<?= schoolEscape($cohort['closes_on']??date('Y-m-d',strtotime('+12 weeks'))) ?>" required></label>
    <?php
}
?>
<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>กิจกรรมโรงเรียน · Anemia Portal</title>
<script src="theme.js?v=5"></script><script defer src="installed-update.js"></script><link rel="stylesheet" href="style.css?v=school-v1">
<script defer src="qrcode.js?v=2.0.4"></script><script defer src="school-qr.js?v=1"></script>
<body class="staff-dashboard-page"><main class="school-workspace">
<header class="card">
 <?php if (!$teacher): ?><a href="dashboard.php">กลับหน้าแดชบอร์ด</a><?php endif; ?>
 <h1 class="title"><?= $teacher?'พื้นที่ครู':'กิจกรรมโรงเรียน' ?></h1>
 <p><?= schoolEscape($identity['username']) ?> · <?= $teacher?'ครูผู้ดูแลกิจกรรม':'จัดการและติดตามกิจกรรมโรงเรียน' ?></p>
 <?php if ($teacher): ?><form method="post" action="login.php"><input type="hidden" name="csrf" value="<?= schoolEscape($csrf) ?>"><button class="action" name="action" value="logout">ออกจากระบบ</button></form><?php endif; ?>
</header>
<?php if ($notice!==''): ?><p class="notice" role="status"><?= schoolEscape($notice) ?></p><?php endif; ?>
<?php if ($admin): ?>
<details class="card"><summary>เพิ่มโรงเรียน</summary>
 <form method="post" class="network-form school-fields"><?php schoolFields($csrf,'school-create'); ?>
  <label>ชื่อโรงเรียน<input name="name" maxlength="160" required></label>
  <label>จังหวัด<select name="province" required><?php foreach($provinces as $code=>$name): ?><option value="<?= schoolEscape((string)$code) ?>"><?= schoolEscape($name) ?></option><?php endforeach; ?></select></label>
  <button class="action primary">เพิ่มโรงเรียน</button>
 </form>
</details>
<?php endif; ?>
<?php if (!$school): ?><section class="card"><p>ยังไม่มีโรงเรียนที่บัญชีนี้ดูแล</p></section>
<?php else: ?>
<section class="card">
 <form method="get" class="network-form school-fields">
  <label>โรงเรียน<select name="school"><?php foreach($schools as $item): ?><option value="<?= (int)$item['id'] ?>" <?= $item['id']===$school['id']?'selected':'' ?>><?= schoolEscape($item['name'].' · '.$item['province']) ?></option><?php endforeach; ?></select></label>
  <button class="action">แสดงโรงเรียน</button>
 </form>
 <?php if ($admin): ?>
 <details><summary>จัดการสิทธิ์ครูและสถานะโรงเรียน</summary>
  <p>สร้างบัญชีบทบาทครูจากเมนูจัดการสิทธิ์ แล้วจึงมอบหมายโรงเรียนที่นี่</p>
  <p>ครูที่ได้รับมอบหมาย <?= schoolEscape(implode(', ',$assignments) ?: 'ยังไม่มี') ?></p>
  <form method="post" class="network-form school-fields"><?php schoolFields($csrf,'grant',(int)$school['id']); ?>
   <label>ชื่อบัญชีครู<input name="teacher" maxlength="40" required></label><button class="action primary">มอบหมายครู</button>
  </form>
  <?php foreach($assignments as $username): ?><form method="post" class="actions"><?php schoolFields($csrf,'revoke',(int)$school['id']); ?><input type="hidden" name="teacher" value="<?= schoolEscape($username) ?>"><button class="action">ถอนสิทธิ์ <?= schoolEscape($username) ?></button></form><?php endforeach; ?>
  <form method="post"><?php schoolFields($csrf,'school-toggle',(int)$school['id']); ?><button class="action"><?= $school['enabled']?'ปิดใช้งานโรงเรียน':'เปิดใช้งานโรงเรียน' ?></button></form>
 </details>
 <?php endif; ?>
</section>
<?php if ($school['enabled'] && ($teacher||$assignments)): ?>
<details class="card"><summary>เปิดรุ่นกิจกรรมและห้องเรียน</summary>
 <form method="post" class="network-form school-fields"><?php schoolFields($csrf,'cohort-create',(int)$school['id']); ?>
  <?php schoolCohortFields(); ?>
  <?php if ($admin): ?><label>ครูผู้รับผิดชอบ<select name="teacher"><?php foreach($assignments as $owner): ?><option><?= schoolEscape($owner) ?></option><?php endforeach; ?></select></label><?php endif; ?>
  <button class="action primary">สร้างรุ่นและรหัสเข้าร่วม</button>
 </form>
</details>
<?php endif; ?>
<section class="card"><h2>รุ่นกิจกรรม</h2>
 <?php if (!$cohorts): ?><p>ยังไม่มีรุ่นกิจกรรม</p><?php endif; ?>
 <?php foreach($cohorts as $item): ?>
  <details class="school-cohort" <?= $item['id']==($cohortId??null)?'open':'' ?>>
   <summary><?= schoolEscape($item['title'].' · '.$item['classroom']) ?> · เข้าร่วม <?= (int)$item['members'] ?> · เรียนครบ <?= (int)$item['completed'] ?></summary>
   <p><?= schoolEscape($item['opens_on'].' ถึง '.$item['closes_on']) ?> · <?= $item['accepting']?'เปิดรับตามช่วงวันที่':'ปิดรับ' ?></p>
   <?php if ($admin||$teacher): ?>
    <a class="action" href="?school=<?= (int)$school['id'] ?>&amp;cohort=<?= (int)$item['id'] ?>">ดูความคืบหน้ารายคน</a>
    <details><summary>ลิงก์และ QR เข้าร่วม</summary><a data-school-qr href="school-join.php?code=<?= schoolEscape($item['invite']) ?>">เปิดลิงก์สำหรับนักเรียน</a><div class="school-qr"></div></details>
    <details><summary>แก้ไขรุ่น</summary><form method="post" class="network-form school-fields">
     <?php schoolFields($csrf,'cohort-edit',(int)$school['id'],(int)$item['id']); schoolCohortFields($item); ?><button class="action primary">บันทึก</button>
    </form></details>
    <form method="post"><?php schoolFields($csrf,'cohort-toggle',(int)$school['id'],(int)$item['id']); ?><button class="action"><?= $item['accepting']?'ปิดรับเพิ่ม':'เปิดรับเพิ่ม' ?></button></form>
    <?php if ($item['id']==($cohortId??null)): ?>
     <?php if (!$members): ?><p>ยังไม่มีผู้เข้าร่วมในห้องนี้</p><?php else: ?>
     <div class="tablewrap"><table class="admin-table"><thead><tr><th>ผู้เข้าร่วม</th><th>บทที่ผ่าน</th><th>สถานะ</th></tr></thead><tbody>
     <?php foreach($members as $index=>$member): ?><tr><td><?= schoolEscape($member['display_name']?:'ผู้เรียน '.($index+1)) ?></td><td><?= (int)$member['weeks'] ?> / 12</td><td><?= $member['completed']?'เรียนครบ':($member['started']?'กำลังเรียน':'ยังไม่เริ่ม') ?></td></tr><?php endforeach; ?>
     </tbody></table></div><?php endif; ?>
    <?php endif; ?>
   <?php endif; ?>
  </details>
 <?php endforeach; ?>
</section>
<?php if ($audit): ?><details class="card"><summary>ประวัติการจัดการล่าสุด</summary><ul><?php foreach($audit as $entry): ?><li><?= schoolEscape(implode(' · ',$entry)) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
<?php endif; ?>
</main></body></html>
