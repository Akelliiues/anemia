<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

require_once __DIR__.'/schools.php';
require_once __DIR__.'/participant-programme.php';

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

return static function (PDO $db,array $identity): void {
    $schoolRoute=$identity['role']==='school_teacher'?[]:['view'=>'schools'];
    $schoolUrl=$schoolRoute?'dashboard.php':'school-workspace.php';
    anemiaEnsureSchools($db);
    $admin=$identity['role']==='account_admin';
    $teacher=$identity['role']==='school_teacher';
    $csrf=$_SESSION['csrf']??=bin2hex(random_bytes(32));
    $notice=$_SESSION['school_notice']??'';
    unset($_SESSION['school_notice']);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!is_string($_POST['csrf']??null) || !hash_equals($csrf,$_POST['csrf'])) { http_response_code(403); exit('คำขอหมดอายุ'); }
        try {
            $savedSchool=schoolSave($db,$identity,$_POST);
            $_SESSION['school_notice']='บันทึกการเปลี่ยนแปลงแล้ว';
        } catch (DomainException $error) {
            $_SESSION['school_notice']=$error->getMessage();
        } catch (PDOException $error) {
            $_SESSION['school_notice']='บันทึกไม่ได้ กรุณาตรวจว่าข้อมูลซ้ำหรือไม่';
            error_log('Anemia school save SQLSTATE '.$error->getCode());
        }
        $schoolId=$savedSchool??filter_input(INPUT_POST,'school',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $redirect=[];
        foreach (['region','province'] as $key) {
            if (is_string($_GET[$key]??null) && ctype_digit($_GET[$key])) $redirect[$key]=$_GET[$key];
        }
        if ($schoolId) $redirect['school']=$schoolId;
        if (($_POST['school_action']??'')==='school-create' && is_string($_POST['province']??null) && ctype_digit($_POST['province'])) {
            $redirect=['province'=>$_POST['province']]+$redirect;
            unset($redirect['region']);
        }
        if ($schoolId && in_array($_POST['school_action']??'', ['cohort-edit','cohort-toggle'],true)) {
            $cohort=filter_input(INPUT_POST,'cohort',FILTER_VALIDATE_INT);
            if ($cohort) $redirect['cohort']=$cohort;
        }
        $redirect=$schoolRoute+$redirect;
        header('Location: '.$schoolUrl.($redirect?'?'.http_build_query($redirect):''),true,303); exit;
    }
    [$scope,$params]=schoolScope($identity);
    $regions=[];
    $areaProvinces=[];
    $region=0;
    $province='';
    if (!$teacher) {
        [$areaScope,$areaParams]=anemiaProvinceScope($identity,0);
        $area=$db->prepare('SELECT code,name,region FROM anemia_province'.($areaScope?' WHERE '.$areaScope:'').' ORDER BY region,name');
        $area->execute($areaParams);
        $areaRows=$area->fetchAll(PDO::FETCH_ASSOC);
        $regions=array_values(array_unique(array_map('intval',array_column($areaRows,'region'))));
        $region=filter_input(INPUT_GET,'region',FILTER_VALIDATE_INT)?:0;
        if (!in_array($region,$regions,true)) $region=count($regions)===1?$regions[0]:0;
        $areaProvinces=array_column(array_filter($areaRows,static fn(array $row): bool =>
            !$region || (int)$row['region']===$region),'name','code');
        $requestedProvince=$_GET['province']??'';
        if (is_string($requestedProvince) && isset($areaProvinces[$requestedProvince])) $province=$requestedProvince;
        if ($region) { $scope.=' AND p.region=?'; $params[]=$region; }
        if ($province!=='') { $scope.=' AND s.province_code=?'; $params[]=$province; }
    }
    $areaFilters=($region?['region'=>$region]:[])+($province!==''?['province'=>$province]:[]);
    $schoolLocation=static fn(array $extra=[]): string => $schoolUrl.'?'.http_build_query($schoolRoute+$areaFilters+$extra);
    $schools=[];
    $schoolTab=($_GET['school_tab']??'')==='tracking'?'tracking':'registry';
    if ($teacher || $province!=='') {
        $query=$db->prepare("SELECT s.*,p.name AS province,p.region,sc.username AS coordinator FROM anemia_school s JOIN anemia_province p ON p.code=s.province_code LEFT JOIN anemia_school_coordinator sc ON sc.school_id=s.id WHERE $scope ORDER BY p.name,s.name");
        $query->execute($params);
        $schools=array_column($query->fetchAll(PDO::FETCH_ASSOC),null,'id');
    }
    $schoolId=filter_input(INPUT_GET,'school',FILTER_VALIDATE_INT);
    $school=$schools[$schoolId]??($schools[array_key_first($schools)]??null);
    $cohorts=[];
    $members=[];
    $assignments=[];
    $roomTeachers=[];
    $profile=[];
    $roomHistory=[];
    $manage=$school && ($admin || ($teacher && $school['coordinator']===$identity['username']));
    $canAppoint=$school && schoolCanAppoint($db,$identity,$school);
    if ($school && $schoolTab!=='tracking') {
        $ownerFilter=$teacher && !$manage ? ' AND ((r.cohort_id IS NULL AND c.teacher=?) OR EXISTS (
            SELECT 1 FROM anemia_school_room_teacher rt WHERE rt.cohort_id=c.id AND rt.username=?))' : '';
        $query=$db->prepare("SELECT c.*,r.academic_year,r.grade,r.room_no,COUNT(m.account_id) AS members, COUNT(a.account_id) AS completed
            FROM anemia_school_cohort c LEFT JOIN anemia_school_room r ON r.cohort_id=c.id LEFT JOIN anemia_school_member m ON m.cohort_id=c.id
            LEFT JOIN anemia_participant_programme_assessment a ON a.account_id=m.account_id AND a.programme_version=c.programme_version
            AND a.instrument_version=? AND a.phase='after'
            WHERE c.school_id=?$ownerFilter GROUP BY c.id,r.academic_year,r.grade,r.room_no ORDER BY r.academic_year DESC,r.grade,r.room_no,c.id DESC");
        $query->execute([ANEMIA_PROGRAMME_INSTRUMENT_VERSION,$school['id'],...($ownerFilter?[$identity['username'],$identity['username']]:[])]);
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
        if ($manage) {
            $query=$db->prepare("SELECT t.username,COALESCE(p.full_name,t.username) AS full_name,p.contact,u.enabled FROM anemia_school_teacher t
                JOIN anemia_staff u ON u.username=t.username AND u.role='school_teacher'
                LEFT JOIN anemia_school_teacher_profile p ON p.username=t.username WHERE t.school_id=? ORDER BY full_name");
            $query->execute([$school['id']]);
            $assignments=$query->fetchAll(PDO::FETCH_ASSOC);
            $query=$db->prepare("SELECT rt.*,COALESCE(p.full_name,rt.username) AS full_name,u.enabled FROM anemia_school_room_teacher rt
                JOIN anemia_school_room r ON r.cohort_id=rt.cohort_id JOIN anemia_staff u ON u.username=rt.username
                LEFT JOIN anemia_school_teacher_profile p ON p.username=rt.username WHERE r.school_id=?");
            $query->execute([$school['id']]);
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) $roomTeachers[$row['cohort_id']][$row['duty']]=$row;
            if (isset($cohorts[$cohortId])) {
                $query=$db->prepare("SELECT h.*,COALESCE(p.full_name,h.username) AS full_name FROM anemia_school_room_history h
                    LEFT JOIN anemia_school_teacher_profile p ON p.username=h.username WHERE h.cohort_id=? ORDER BY h.id DESC LIMIT 30");
                $query->execute([$cohortId]);
                $roomHistory=$query->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        if ($teacher) {
            $query=$db->prepare('SELECT full_name,contact FROM anemia_school_teacher_profile WHERE username=?');
            $query->execute([$identity['username']]);
            $profile=$query->fetch(PDO::FETCH_ASSOC)?:[];
        }
    }
    $tracking=[];
    $trackingYears=[];
    $trackingYear=filter_input(INPUT_GET,'year',FILTER_VALIDATE_INT,['options'=>['min_range'=>2500,'max_range'=>2642]])?:0;
    $trackingStatus=$_GET['school_status']??'';
    if (!in_array($trackingStatus,['no-coordinator','no-rooms','active','disabled'],true)) $trackingStatus='';
    if (!$teacher && $province!=='' && $schoolTab==='tracking') {
        $query=$db->prepare('SELECT DISTINCT r.academic_year FROM anemia_school_room r JOIN anemia_school s ON s.id=r.school_id WHERE s.province_code=? ORDER BY r.academic_year DESC');
        $query->execute([$province]);
        $trackingYears=array_map('intval',$query->fetchAll(PDO::FETCH_COLUMN));
        if (!in_array($trackingYear,$trackingYears,true)) $trackingYear=0;
        $yearClause=$trackingYear?' AND EXISTS (SELECT 1 FROM anemia_school_room r WHERE r.cohort_id=c.id AND r.academic_year=?)':'';
        $query=$db->prepare("SELECT s.id,s.name,s.enabled,staff.username AS coordinator,COUNT(DISTINCT c.id) AS rooms,
            COUNT(DISTINCT CASE WHEN s.enabled=1 AND c.accepting=1 AND ? BETWEEN c.opens_on AND c.closes_on THEN c.id END) AS accepting,
            COUNT(DISTINCT m.account_id) AS members,COUNT(DISTINCT a.account_id) AS completed
            FROM anemia_school s LEFT JOIN anemia_school_coordinator sc ON sc.school_id=s.id
            LEFT JOIN anemia_staff staff ON staff.username=sc.username AND staff.enabled=1 AND staff.role='school_teacher'
            LEFT JOIN anemia_school_cohort c ON c.school_id=s.id$yearClause LEFT JOIN anemia_school_member m ON m.cohort_id=c.id
            LEFT JOIN anemia_participant_programme_assessment a ON a.account_id=m.account_id AND a.programme_version=c.programme_version
                AND a.instrument_version=? AND a.phase='after'
            WHERE s.province_code=? GROUP BY s.id,s.name,s.enabled,staff.username ORDER BY s.name");
        $query->execute([date('Y-m-d'),...($trackingYear?[$trackingYear]:[]),ANEMIA_PROGRAMME_INSTRUMENT_VERSION,$province]);
        $tracking=$query->fetchAll(PDO::FETCH_ASSOC);
    }
    $trackingRows=array_filter($tracking,static fn(array $row): bool => match ($trackingStatus) {
        'no-coordinator'=> (bool)$row['enabled'] && !$row['coordinator'],
        'no-rooms'=> (bool)$row['enabled'] && !$row['rooms'],
        'active'=> (bool)$row['enabled'] && (bool)$row['rooms'],
        'disabled'=> !(bool)$row['enabled'],
        default=>true,
    });
    $provinces=$admin?$db->query('SELECT code,name FROM anemia_province ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR):[];
    $directory=$schoolTab==='registry' && $province==='34'?schoolUbonDirectory():['districts'=>[],'schools'=>[]];
    $district=$_GET['district']??'';
    if (!in_array($district,$directory['districts'],true)) $district='';
    $keyword=$_GET['q']??'';
    $keyword=is_string($keyword)&&strlen($keyword)<=180?trim($keyword):'';
    $listedSchools=array_values(array_filter($directory['schools'],static fn(array $row): bool =>
        ($district==='' || $row['district']===$district) &&
        ($keyword==='' || stripos($row['name'].' '.$row['code'],$keyword)!==false)));
    $registeredUbon=[];
    foreach ($schools as $registeredSchool) {
        if ($registeredSchool['province_code']==='34') $registeredUbon[$registeredSchool['name']]=$registeredSchool['id'];
    }
    $audit=[];
    if(($manage || $canAppoint) && $school && $schoolTab!=='tracking') {
        $query=$db->prepare('SELECT actor,action_name,created_at FROM anemia_school_audit WHERE school_id=? ORDER BY id DESC LIMIT 20');
        $query->execute([$school['id']]);
        $audit=$query->fetchAll(PDO::FETCH_ASSOC);
    }
    $roomYears=array_values(array_unique(array_map('intval',array_filter(array_column($cohorts,'academic_year')))));
    $roomYear=filter_input(INPUT_GET,'room_year',FILTER_VALIDATE_INT)?:0;
    if (!in_array($roomYear,$roomYears,true)) $roomYear=0;
    $unassigned=$manage && ($_GET['unassigned']??'')==='1';
    $visibleCohorts=array_filter($cohorts,static fn(array $row): bool =>
        (!$roomYear || $roomYear===(int)$row['academic_year']) &&
        (!$unassigned || ($row['academic_year'] && empty($roomTeachers[$row['id']]['primary']['enabled']))));
    $schoolPostUrl='school-workspace.php'.($areaFilters?'?'.http_build_query($areaFilters):'');
?>
<div class="school-workspace">
<?php if ($notice!==''): ?><p class="notice" role="status"><?= schoolEscape($notice) ?></p><?php endif; ?>
<?php if (!$teacher): ?>
<section class="card school-area" aria-labelledby="school-area-title">
 <h2 id="school-area-title"><?= workspaceIcon('map') ?> พื้นที่กิจกรรมโรงเรียน</h2>
 <form method="get" class="network-form school-directory-filters">
  <?php if (!$teacher): ?><input type="hidden" name="view" value="schools"><?php endif; ?>
  <label>เขตสุขภาพ<select name="region" onchange="this.form.elements.province.value='';this.form.submit()">
   <?php if (count($regions)>1): ?><option value="0">ทุกเขตตามสิทธิ์</option><?php endif; ?>
   <?php foreach ($regions as $zone): ?><option value="<?= $zone ?>" <?= $region===$zone?'selected':'' ?>><?= 'เขตสุขภาพที่ '.$zone.($zone===13?' กรุงเทพมหานคร':'') ?></option><?php endforeach; ?>
  </select></label>
  <label>จังหวัด<select name="province" required>
   <option value="">เลือกจังหวัด</option>
   <?php foreach ($areaProvinces as $code=>$name): ?><option value="<?= schoolEscape((string)$code) ?>" <?= $province===(string)$code?'selected':'' ?>><?= schoolEscape($name) ?></option><?php endforeach; ?>
  </select></label>
  <button class="action primary"><?= workspaceIcon('filter') ?>แสดงโรงเรียน</button>
 </form>
<?php if ($admin): ?>
<details class="school-create">
 <summary class="action primary school-create-toggle" aria-label="เพิ่มโรงเรียน" title="เพิ่มโรงเรียน"><?= workspaceIcon('school-add') ?></summary>
 <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>" class="network-form school-fields"><?php schoolFields($csrf,'school-create'); ?>
  <label>ชื่อโรงเรียน<input name="name" maxlength="160" required></label>
  <label>จังหวัด<select name="province" required><?php foreach($provinces as $code=>$name): ?><option value="<?= schoolEscape((string)$code) ?>"><?= schoolEscape($name) ?></option><?php endforeach; ?></select></label>
  <button class="action primary">เพิ่มโรงเรียน</button>
 </form>
</details>
<?php endif; ?>
</section>
<?php endif; ?>
<?php if (!$teacher && $province!==''): ?>
<nav class="actions" aria-label="เมนูกิจกรรมโรงเรียน">
 <?php foreach (['registry'=>'ทะเบียนโรงเรียน','tracking'=>'ติดตามกิจกรรม'] as $tab=>$label): ?>
  <a class="action<?= $schoolTab===$tab?' primary':'' ?>" href="<?= schoolEscape($schoolLocation(['school_tab'=>$tab])) ?>" <?= $schoolTab===$tab?'aria-current="page"':'' ?>><?= schoolEscape($label) ?></a>
 <?php endforeach; ?>
</nav>
<?php endif; ?>
<?php if ($schoolTab==='tracking' && $province!==''): ?>
<section class="card"><h2>ติดตามกิจกรรมรายโรงเรียน</h2>
 <div class="admin-kpis hosted-kpis">
  <div class="card"><span>โรงเรียนในทะเบียน</span><strong><?= count($tracking) ?></strong></div>
  <div class="card"><span>โรงเรียนที่มีห้องกิจกรรม</span><strong><?= count(array_filter(array_column($tracking,'rooms'))) ?></strong></div>
  <div class="card"><span>ห้องที่เปิดรับ</span><strong><?= array_sum(array_column($tracking,'accepting')) ?></strong></div>
  <div class="card"><span>โรงเรียนที่รอผู้ประสานงาน</span><strong><?= count(array_filter($tracking,static fn(array $row): bool => $row['enabled'] && !$row['coordinator'])) ?></strong></div>
 </div>
 <form method="get" class="network-form school-directory-filters">
  <input type="hidden" name="view" value="schools"><input type="hidden" name="school_tab" value="tracking">
  <?php foreach ($areaFilters as $key=>$value): ?><input type="hidden" name="<?= schoolEscape($key) ?>" value="<?= schoolEscape((string)$value) ?>"><?php endforeach; ?>
  <label>ปีการศึกษา<select name="year"><option value="">ทุกปี</option>
   <?php foreach ($trackingYears as $year): ?><option value="<?= $year ?>" <?= $trackingYear===$year?'selected':'' ?>><?= $year ?></option><?php endforeach; ?>
  </select></label>
  <label>สถานะ<select name="school_status"><option value="">ทุกสถานะ</option>
   <?php foreach (['no-coordinator'=>'รอผู้ประสานงาน','no-rooms'=>'ยังไม่มีห้องกิจกรรม','active'=>'มีห้องกิจกรรม','disabled'=>'ปิดใช้งาน'] as $key=>$label): ?>
    <option value="<?= $key ?>" <?= $trackingStatus===$key?'selected':'' ?>><?= $label ?></option>
   <?php endforeach; ?>
  </select></label>
  <button class="action primary">กรองรายงาน</button>
 </form>
 <div class="tablewrap"><table class="admin-table"><thead><tr><th scope="col">โรงเรียน</th><th scope="col">สถานะ</th><th scope="col">ห้อง</th><th scope="col">เปิดรับ</th><th scope="col">เข้าร่วม (คน)</th><th scope="col">ทำครบ (คน)</th></tr></thead><tbody>
 <?php foreach ($trackingRows as $entry): ?>
 <tr><th scope="row"><a href="<?= schoolEscape($schoolLocation(['school'=>(int)$entry['id']])) ?>#school-activities"><?= schoolEscape($entry['name']) ?></a></th>
 <td><?= !$entry['enabled']?'ปิดใช้งาน':(!$entry['coordinator']?'รอผู้ประสานงาน':(!$entry['rooms']?'ยังไม่มีห้องกิจกรรม':'มีห้องกิจกรรม')) ?></td>
 <td><?= (int)$entry['rooms'] ?></td><td><?= (int)$entry['accepting'] ?></td><td><?= (int)$entry['members'] ?></td><td><?= (int)$entry['completed'] ?></td></tr>
 <?php endforeach; ?>
 <?php if (!$trackingRows): ?><tr><td colspan="6">ไม่พบโรงเรียนตามตัวกรอง</td></tr><?php endif; ?>
 </tbody></table></div>
</section>
<?php else: ?>
<?php if ($directory['schools']): ?>
<section class="card school-directory" id="school-directory" aria-labelledby="school-directory-title">
 <h2 id="school-directory-title"><?= workspaceIcon('book') ?> โรงเรียนมัธยมปลาย จังหวัดอุบลราชธานี</h2>
 <p><?= count($directory['schools']) ?> แห่งใน <?= count($directory['districts']) ?> อำเภอ · ปีการศึกษา 2569</p>
 <form method="get" action="#school-directory" class="network-form school-directory-filters">
  <?php if (!$teacher): ?><input type="hidden" name="view" value="schools"><?php endif; ?>
  <?php foreach ($areaFilters as $key=>$value): ?><input type="hidden" name="<?= schoolEscape($key) ?>" value="<?= schoolEscape((string)$value) ?>"><?php endforeach; ?>
  <?php if ($school): ?><input type="hidden" name="school" value="<?= (int)$school['id'] ?>"><?php endif; ?>
  <label>อำเภอ<select name="district"><option value="">ทุกอำเภอ</option><?php foreach ($directory['districts'] as $name): ?><option <?= $name===$district?'selected':'' ?>><?= schoolEscape($name) ?></option><?php endforeach; ?></select></label>
  <label>ชื่อหรือรหัสโรงเรียน<input type="search" name="q" maxlength="60" value="<?= schoolEscape($keyword) ?>"></label>
  <button class="action primary"><?= workspaceIcon('filter') ?>ค้นหา</button>
 </form>
 <p role="status">พบ <?= count($listedSchools) ?> แห่ง</p>
 <div class="tablewrap school-directory-table" tabindex="0" aria-label="รายชื่อโรงเรียน">
 <table class="admin-table">
  <thead><tr><th scope="col">โรงเรียน</th><th scope="col">อำเภอ</th><th scope="col">ระดับชั้น</th><th scope="col">จัดการ</th></tr></thead>
  <tbody><?php foreach ($listedSchools as $row): ?><tr>
   <th scope="row"><?= schoolEscape($row['name']) ?><br><small><?= schoolEscape($row['code'].' · '.$row['agency']) ?></small></th>
   <td><?= schoolEscape($row['district']) ?></td>
   <td><?= schoolEscape(implode(', ',str_replace('M','ม.',$row['grades']))) ?></td>
   <td><?php if (isset($registeredUbon[$row['name']])): ?>
    <a class="action" href="<?= schoolEscape($schoolLocation(['school'=>(int)$registeredUbon[$row['name']]])) ?>#school-activities">จัดการโรงเรียน</a>
   <?php elseif ($admin): ?>
    <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>"><?php schoolFields($csrf,'directory-add'); ?><input type="hidden" name="directory_code" value="<?= schoolEscape($row['code']) ?>"><button class="action"><?= workspaceIcon('ready') ?>เพิ่มเข้าทะเบียน</button></form>
   <?php else: ?>ยังไม่เข้าทะเบียน<?php endif; ?></td>
  </tr><?php endforeach; ?>
  <?php if (!$listedSchools): ?><tr><td colspan="4">ไม่พบโรงเรียนตามตัวกรอง</td></tr><?php endif; ?>
  </tbody>
 </table></div>
 <p class="notice">แสดงโรงเรียนที่มีผู้เรียน ม.4–ม.6 อย่างน้อยหนึ่งชั้น ณ 10 มิ.ย. 2569 ไม่นับศูนย์ส่งเสริมการเรียนรู้ระดับอำเภอ <a href="https://www.ubnpeo.go.th/eis/" target="_blank" rel="noopener">สำนักงานศึกษาธิการจังหวัดอุบลราชธานี</a></p>
</section>
<?php endif; ?>
<?php if (!$school): ?><section class="card"><p><?= $teacher?'ยังไม่มีโรงเรียนที่บัญชีนี้ดูแล':($province===''?'เลือกจังหวัดแล้วกดแสดงโรงเรียน':'ยังไม่มีโรงเรียนในทะเบียนของจังหวัดนี้') ?></p></section>
<?php else: ?>
<section class="card" id="school-activities">
 <form method="get" class="network-form school-fields">
  <?php if (!$teacher): ?><input type="hidden" name="view" value="schools"><?php endif; ?>
  <?php foreach ($areaFilters as $key=>$value): ?><input type="hidden" name="<?= schoolEscape($key) ?>" value="<?= schoolEscape((string)$value) ?>"><?php endforeach; ?>
  <label>โรงเรียน<select name="school"><?php foreach($schools as $item): ?><option value="<?= (int)$item['id'] ?>" <?= $item['id']===$school['id']?'selected':'' ?>><?= schoolEscape($item['name'].' · '.$item['province']) ?></option><?php endforeach; ?></select></label>
  <button class="action">แสดงโรงเรียน</button>
 </form>
 <?php require __DIR__.'/school-controls.php'; ?>
</section>
<section class="card"><h2><?= $teacher && !$manage ? 'ห้องของฉัน' : 'ห้องเรียนและรุ่นกิจกรรม' ?></h2>
 <?php if ($roomYears): ?>
 <form method="get" class="network-form school-directory-filters">
  <?php foreach ($schoolRoute+$areaFilters+['school'=>$school['id']] as $key=>$value): ?>
   <input type="hidden" name="<?= schoolEscape($key) ?>" value="<?= schoolEscape((string)$value) ?>">
  <?php endforeach; ?>
  <label>ปีการศึกษา<select name="room_year"><option value="0">ทุกปี</option>
   <?php foreach ($roomYears as $year): ?><option value="<?= $year ?>" <?= $roomYear===$year?'selected':'' ?>><?= $year ?></option><?php endforeach; ?>
  </select></label>
  <?php if ($manage): ?><label>ผู้รับผิดชอบ<select name="unassigned"><option value="0">ทุกห้อง</option><option value="1" <?= $unassigned?'selected':'' ?>>ห้องที่ยังไม่มีครูหลัก</option></select></label><?php endif; ?>
  <button class="action">กรองห้อง</button>
 </form>
 <?php endif; ?>
 <?php if (!$visibleCohorts): ?><p><?= $cohorts?'ไม่พบห้องตามตัวกรอง':'ยังไม่มีรุ่นกิจกรรม' ?></p><?php endif; ?>
 <?php foreach($visibleCohorts as $item): ?>
  <details class="school-cohort" <?= (int)$item['id']===($cohortId??null)?'open':'' ?>>
   <summary><?= schoolEscape(($item['academic_year']?'ปี '.$item['academic_year'].' · ':'').$item['title'].' · '.$item['classroom']) ?> · เข้าร่วม <?= (int)$item['members'] ?> · ทำครบ <?= (int)$item['completed'] ?></summary>
   <p><?= schoolEscape($item['opens_on'].' ถึง '.$item['closes_on']) ?> · <?= $item['accepting'] && $school['enabled']?'เปิดรับตามช่วงวันที่':'ปิดรับ' ?></p>
   <?php if ($manage && $item['academic_year']): ?>
    <p>ครูหลัก <?= schoolEscape($roomTeachers[$item['id']]['primary']['full_name']??'ยังไม่ได้มอบหมาย') ?> · ครูร่วม <?= schoolEscape($roomTeachers[$item['id']]['assistant']['full_name']??'ยังไม่ได้มอบหมาย') ?></p>
   <?php endif; ?>
   <?php if ($admin||$teacher): ?>
    <a class="action" href="<?= schoolEscape($schoolLocation(['school'=>(int)$school['id'],'cohort'=>(int)$item['id']])) ?>">ดูความคืบหน้ารายคน</a>
    <details><summary>ลิงก์และ QR เข้าร่วม</summary><a data-school-qr href="school-join.php?code=<?= schoolEscape($item['invite']) ?>">เปิดลิงก์สำหรับนักเรียน</a><div class="school-qr"></div><button type="button" class="action" data-copy-school-link>คัดลอกลิงก์</button></details>
    <?php if ($manage || !$item['academic_year']): ?>
    <details><summary>แก้ไขรุ่น</summary><form method="post" action="<?= schoolEscape($schoolPostUrl) ?>" class="network-form school-fields">
     <?php schoolFields($csrf,'cohort-edit',(int)$school['id'],(int)$item['id']); schoolCohortFields($item); ?><button class="action primary">บันทึก</button>
    </form></details>
    <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>"><?php schoolFields($csrf,'cohort-toggle',(int)$school['id'],(int)$item['id']); ?><button class="action"><?= $item['accepting']?'ปิดรับเพิ่ม':'เปิดรับเพิ่ม' ?></button></form>
    <?php endif; ?>
    <?php if ((int)$item['id']===($cohortId??null)): ?>
     <?php if ($roomHistory): ?><details><summary>ประวัติเปลี่ยนครูประจำห้อง</summary><ul>
      <?php foreach ($roomHistory as $entry): ?><li><?= schoolEscape($entry['full_name'].' · '.($entry['duty']==='primary'?'ครูหลัก':'ครูร่วม').' · '.$entry['started_at'].' ถึง '.$entry['ended_at'].' · เปลี่ยนโดย '.$entry['ended_by']) ?></li><?php endforeach; ?>
     </ul></details><?php endif; ?>
     <?php if (!$members): ?><p>ยังไม่มีผู้เข้าร่วมในห้องนี้</p><?php else: ?>
     <div class="tablewrap"><table class="admin-table"><thead><tr><th>ผู้เข้าร่วม</th><th>บทที่ผ่าน</th><th>สถานะ</th></tr></thead><tbody>
     <?php foreach($members as $index=>$member): ?><tr><td><?= schoolEscape($member['display_name']?:'ผู้เรียน '.($index+1)) ?></td><td><?= (int)$member['weeks'] ?> / 12</td><td><?= $member['completed']?'ทำครบแล้ว':((int)$member['weeks']>=12?'รอแบบประเมินหลังเรียน':($member['started'] || $member['weeks']?'กำลังเรียน':'ยังไม่เริ่ม')) ?></td></tr><?php endforeach; ?>
     </tbody></table></div><?php endif; ?>
    <?php endif; ?>
   <?php endif; ?>
  </details>
 <?php endforeach; ?>
</section>
<?php if ($audit): ?><details class="card"><summary>ประวัติการจัดการล่าสุด</summary><ul><?php foreach($audit as $entry): ?><li><?= schoolEscape(implode(' · ',$entry)) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
<?php endif; ?>
<?php endif; ?>
</div>
<?php
};
