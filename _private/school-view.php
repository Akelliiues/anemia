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

function schoolDisclosureHeading(string $label,string $icon): void
{
    ?>
    <summary class="school-disclosure-heading">
     <span class="school-heading-icon" aria-hidden="true"><?= workspaceIcon($icon) ?></span>
     <span><?= schoolEscape($label) ?></span>
     <span class="school-heading-chevron" aria-hidden="true"><?= workspaceIcon('next') ?></span>
    </summary>
    <?php
}

function schoolParticipationButton(int $id,string $name,bool $enabled,array $summary,bool $inDirectory=false): void
{
    $action=$enabled?($summary['hasData']?'school-archive':'school-withdraw'):'school-reopen';
    $actionLabel=$enabled?($summary['hasData']?'ยุติการเข้าร่วม':'ถอนออกจากโครงการ'):($summary['hasData']?'เปิดใช้งานอีกครั้ง':'เพิ่มเข้าร่วม');
    $label=$inDirectory && $enabled?'เข้าร่วมแล้ว':$actionLabel;
    $icon=$enabled?($inDirectory?'check':'close'):($summary['hasData']?'history':'plus');
    ?>
    <button type="button" class="action<?= !$enabled && !$summary['hasData']?' primary':'' ?><?= $inDirectory?' school-row-toggle':'' ?>"
      aria-label="<?= schoolEscape($label.' · '.$name) ?>" title="<?= schoolEscape($actionLabel) ?>"
      data-school-participation="<?= schoolEscape($action) ?>" data-school-id="<?= $id ?>"
      data-school-name="<?= schoolEscape($name) ?>" data-school-has-data="<?= $summary['hasData']?'1':'0' ?>"
      data-school-years="<?= schoolEscape(json_encode($summary['years'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)) ?>"
      aria-haspopup="dialog"><?= workspaceIcon($icon) ?><?= schoolEscape($label) ?></button>
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
            $_SESSION['school_notice']=match ($_POST['school_action']??'') {
                'school-withdraw'=>'ถอนโรงเรียนออกจากโครงการแล้ว',
                'school-archive'=>'ยุติการเข้าร่วมแล้ว ประวัติของโรงเรียนยังเปิดดูได้',
                'school-reopen'=>'เปิดใช้งานโรงเรียนแล้ว เลือกห้องที่จะเปิดรับได้จากหน้าจัดการ',
                default=>'บันทึกการเปลี่ยนแปลงแล้ว',
            };
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
        if ($schoolId && in_array($_POST['school_action']??'', ['cohort-edit','cohort-toggle','room-target'],true)) {
            $cohort=filter_input(INPUT_POST,'cohort',FILTER_VALIDATE_INT);
            if ($cohort) $redirect['cohort']=$cohort;
        }
        if (($_POST['school_action']??'')==='directory-add' && isset($savedSchool)) {
            $redirect['province']=$_POST['directory_province']??'34';
        }
        if (in_array($_POST['school_action']??'',['directory-add','school-withdraw','school-archive','school-reopen'],true)) {
            if (($_SERVER['HTTP_X_SCHOOL_UPDATE']??'')==='1') {
                $returnSchool=filter_input(INPUT_GET,'return_school',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                if ($returnSchool) $redirect['school']=$returnSchool;
            }
            foreach (['district','q','directory_page'] as $key) {
                if (is_string($_GET[$key]??null) && strlen($_GET[$key])<=180) $redirect[$key]=$_GET[$key];
            }
        }
        $redirect=$schoolRoute+$redirect;
        $anchor=($_POST['school_action']??'')==='room-target' && isset($redirect['cohort'])?'#school-room-'.$redirect['cohort']:'';
        header('Location: '.$schoolUrl.($redirect?'?'.http_build_query($redirect):'').$anchor,true,303); exit;
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
        $query=$db->prepare("SELECT c.*,r.academic_year,r.grade,r.room_no,t.participants AS participation_target,COUNT(m.account_id) AS members, COUNT(a.account_id) AS completed
            FROM anemia_school_cohort c LEFT JOIN anemia_school_room r ON r.cohort_id=c.id
            LEFT JOIN anemia_school_room_target t ON t.cohort_id=c.id LEFT JOIN anemia_school_member m ON m.cohort_id=c.id
            LEFT JOIN anemia_participant_programme_assessment a ON a.account_id=m.account_id AND a.programme_version=c.programme_version
            AND a.instrument_version=? AND a.phase='after'
            WHERE c.school_id=?$ownerFilter GROUP BY c.id,r.academic_year,r.grade,r.room_no,t.participants ORDER BY r.academic_year DESC,r.grade,r.room_no,c.id DESC");
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
    $staffingIssues=[];
    $waitingCoordinators=[];
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
        $waitingCoordinators=array_filter($tracking,static fn(array $row): bool => $row['enabled'] && !$row['coordinator']);
        $staffingIssues=schoolStaffingIssues($db,$province,$trackingYear,date('Y-m-d'));
    }
    $trackingRows=array_filter($tracking,static fn(array $row): bool => match ($trackingStatus) {
        'no-coordinator'=> (bool)$row['enabled'] && !$row['coordinator'],
        'no-rooms'=> (bool)$row['enabled'] && !$row['rooms'],
        'active'=> (bool)$row['enabled'] && (bool)$row['rooms'],
        'disabled'=> !(bool)$row['enabled'],
        default=>true,
    });
    $provinces=$admin?$db->query('SELECT code,name FROM anemia_province ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR):[];
    $directory=$schoolTab==='registry' && $province!==''?schoolDirectory($province):['districts'=>[],'schools'=>[]];
    $districts=$directory['districts'];
    $district=$_GET['district']??'';
    if (!in_array($district,$districts,true)) $district='';
    $keyword=$_GET['q']??'';
    $keyword=is_string($keyword)&&strlen($keyword)<=180?trim($keyword):'';
    $listedSchools=array_values(array_filter($directory['schools'],static fn(array $row): bool =>
        ($district==='' || $row['district']===$district) &&
        ($keyword==='' || stripos($row['name'].' '.$row['code'].' '.($row['smis']??'').' '.($row['alternate_code']??'').' '.($row['legacy_name']??''),$keyword)!==false)));
    $directoryPageSize=30;
    $directoryTotal=count($listedSchools);
    $directoryPages=max(1,(int)ceil($directoryTotal/$directoryPageSize));
    $directoryPage=max(1,min($directoryPages,filter_input(INPUT_GET,'directory_page',FILTER_VALIDATE_INT)?:1));
    $directoryFilters=['district'=>$district,'q'=>$keyword];
    $windowStart=max(2,min($directoryPage-1,$directoryPages-3));
    $visiblePages=$directoryPages<=5?range(1,$directoryPages):[1,...range($windowStart,$windowStart+2),$directoryPages];
    $directoryPageUrl=static fn(int $page): string => $schoolLocation($directoryFilters+
        ($school?['school'=>(int)$school['id']]:[])+['directory_page'=>$page]).'#school-directory';
    $registeredSchools=array_column($schools,'id','name');
    $registeredCodes=[];
    if ($registeredSchools && $directory['schools']) {
        $query=$db->prepare('SELECT d.code,d.school_id FROM anemia_school_directory_link d JOIN anemia_school s ON s.id=d.school_id WHERE s.province_code=?');
        $query->execute([$province]);
        $registeredCodes=$query->fetchAll(PDO::FETCH_KEY_PAIR);
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
    $pageRows=array_slice($listedSchools,($directoryPage-1)*$directoryPageSize,$directoryPageSize);
    $directorySchool=static fn(array $row)=>$registeredCodes[$row['code']]??$registeredSchools[$row['name']]??$registeredSchools[$row['legacy_name']??'']??null;
    $participation=[];
    if ($admin && $schoolTab==='registry') {
        $ids=array_filter(array_map($directorySchool,$pageRows));
        if ($school) $ids[]=$school['id'];
        $participation=schoolParticipation($db,array_values(array_unique($ids)));
    }
    $postFilters=$areaFilters+array_filter($directoryFilters+['directory_page'=>$directoryPage>1?$directoryPage:null,'return_school'=>$school['id']??null]);
    $schoolPostUrl='school-workspace.php'.($postFilters?'?'.http_build_query($postFilters):'');
?>
<div class="school-workspace" data-school-workspace>
<p class="notice" role="alert" data-school-update-error hidden></p>
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
<section class="card"><h2><?= workspaceIcon('chart') ?> ติดตามกิจกรรมรายโรงเรียน</h2>
 <div class="admin-kpis hosted-kpis">
  <div class="card"><span>โรงเรียนในทะเบียน</span><strong><?= count($tracking) ?></strong></div>
  <div class="card"><span>โรงเรียนที่มีห้องกิจกรรม</span><strong><?= count(array_filter(array_column($tracking,'rooms'))) ?></strong></div>
  <div class="card"><span>ห้องที่เปิดรับ</span><strong><?= array_sum(array_column($tracking,'accepting')) ?></strong></div>
  <div class="card"><span>โรงเรียนที่รอผู้ประสานงาน</span><strong><?= count($waitingCoordinators) ?></strong></div>
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
 <details class="school-disclosure" id="school-staffing-issues">
  <?php schoolDisclosureHeading('งานที่ต้องจัดการ · '.number_format(count($waitingCoordinators)+count($staffingIssues)).' รายการ','bell'); ?>
  <?php if (!$waitingCoordinators && !$staffingIssues): ?><p class="notice">ไม่มีงานรอจัดการในพื้นที่และปีที่เลือก</p><?php else: ?>
  <div class="tablewrap school-staffing-table"><table class="admin-table">
   <thead><tr><th scope="col">โรงเรียน</th><th scope="col">ห้อง / ปีการศึกษา</th><th scope="col">สิ่งที่ต้องจัดการ</th></tr></thead>
   <tbody>
   <?php foreach ($waitingCoordinators as $entry): ?>
    <tr><th scope="row"><a href="<?= schoolEscape($schoolLocation(['school'=>(int)$entry['id']])) ?>#school-activities"><?= schoolEscape($entry['name']) ?></a></th><td>—</td><td>ยังไม่มีผู้ประสานงานที่ใช้งานได้</td></tr>
   <?php endforeach; ?>
   <?php foreach ($staffingIssues as $issue): ?>
    <tr><th scope="row"><a href="<?= schoolEscape($schoolLocation(['school'=>(int)$issue['school_id'],'cohort'=>(int)$issue['cohort_id']])) ?>#school-room-<?= (int)$issue['cohort_id'] ?>"><?= schoolEscape($issue['school_name']) ?></a></th>
    <td><?= schoolEscape($issue['classroom'].' · '.$issue['academic_year']) ?></td>
    <td><?= $issue['issue']==='inactive'?'บัญชีครูหลักใช้งานไม่ได้':'ถึงกำหนดเปิดหรือจะเปิดใน 14 วัน แต่ยังไม่มีครูหลัก' ?></td></tr>
   <?php endforeach; ?>
   </tbody>
  </table></div>
  <?php endif; ?>
 </details>
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
<?php if ($province!==''): ?>
<section class="card school-directory" id="school-directory" aria-labelledby="school-directory-title">
 <h2 id="school-directory-title"><?= workspaceIcon('book') ?> โรงเรียนมัธยมปลาย · <?= schoolEscape($areaProvinces[$province]) ?></h2>
 <p><?= count($directory['schools']) ?> แห่ง · ตัวกรอง <?= count($districts) ?> <?= $province==='10'?'เขต':'อำเภอ' ?></p>
 <form method="get" action="#school-directory" class="network-form school-directory-filters">
  <?php if (!$teacher): ?><input type="hidden" name="view" value="schools"><?php endif; ?>
  <?php foreach ($areaFilters as $key=>$value): ?><input type="hidden" name="<?= schoolEscape($key) ?>" value="<?= schoolEscape((string)$value) ?>"><?php endforeach; ?>
  <?php if ($school): ?><input type="hidden" name="school" value="<?= (int)$school['id'] ?>"><?php endif; ?>
  <label><?= $province==='10'?'เขต':'อำเภอ' ?><select name="district"><option value="">ทุก<?= $province==='10'?'เขต':'อำเภอ' ?></option><?php foreach ($districts as $name): ?><option <?= $name===$district?'selected':'' ?>><?= schoolEscape($name) ?></option><?php endforeach; ?></select></label>
  <label>ชื่อหรือรหัสโรงเรียน<input type="search" name="q" maxlength="60" value="<?= schoolEscape($keyword) ?>"></label>
  <button class="action primary"><?= workspaceIcon('filter') ?>ค้นหา</button>
 </form>
 <div class="school-directory-grid">
 <div class="tablewrap school-directory-table" tabindex="0" aria-label="รายชื่อโรงเรียน">
 <table class="admin-table">
  <thead><tr><th scope="col">โรงเรียน</th><th scope="col"><?= $province==='10'?'เขต':'อำเภอ' ?></th><th scope="col">ระดับชั้น</th><th scope="col">จัดการ</th></tr></thead>
  <tbody><?php foreach ($pageRows as $row): ?>
   <?php $registeredId=$directorySchool($row); $record=$schools[$registeredId]??null; ?>
   <tr data-school-code="<?= schoolEscape($row['code']) ?>">
   <th scope="row">
    <?php if ($record): ?>
     <a class="school-directory-name" href="<?= schoolEscape($schoolLocation(array_filter($directoryFilters+['directory_page'=>$directoryPage>1?$directoryPage:null,'school'=>(int)$registeredId]))) ?>#school-activities"
      title="<?= $record['enabled']?'จัดการโรงเรียน':'ดูประวัติ' ?>"><?= schoolEscape($row['name']) ?></a>
    <?php else: ?><?= schoolEscape($row['name']) ?><?php endif; ?>
    <br><small><?= schoolEscape(($row['source']==='dla'?'อ้างอิง ':'').$row['code'].' · '.$row['agency']) ?></small>
   </th>
   <td><?= schoolEscape($row['district']) ?></td>
   <td><?= schoolEscape($row['grades']?implode(', ',str_replace('M','ม.',$row['grades'])):'มัธยมปลาย (ไม่ระบุชั้น)') ?></td>
   <td><?php if ($record && $admin): ?>
    <?php schoolParticipationButton((int)$registeredId,$record['name'],(bool)$record['enabled'],$participation[$registeredId],true); ?>
   <?php elseif ($record): ?><?= $record['enabled']?'เข้าร่วมแล้ว':'หยุดเข้าร่วม' ?>
   <?php elseif ($admin): ?>
    <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>" data-school-update>
     <?php schoolFields($csrf,'directory-add'); ?>
     <input type="hidden" name="directory_province" value="<?= schoolEscape($province) ?>">
     <input type="hidden" name="directory_code" value="<?= schoolEscape($row['code']) ?>">
     <button class="action primary school-row-toggle" aria-label="<?= schoolEscape('เพิ่มเข้าร่วม · '.$row['name']) ?>"><?= workspaceIcon('plus') ?>เพิ่มเข้าร่วม</button>
    </form>
   <?php else: ?>ยังไม่เข้าทะเบียน<?php endif; ?></td>
  </tr><?php endforeach; ?>
  <?php if (!$listedSchools): ?><tr><td colspan="4">ไม่พบโรงเรียนในข้อมูลอ้างอิงตามตัวกรอง</td></tr><?php endif; ?>
  </tbody>
 </table></div>
 <?php if ($directoryTotal): ?>
 <div class="school-directory-footer">
  <p class="school-directory-range">แสดง <strong><?= number_format(($directoryPage-1)*$directoryPageSize+1) ?>–<?= number_format(min($directoryPage*$directoryPageSize,$directoryTotal)) ?></strong> จาก <strong><?= number_format($directoryTotal) ?></strong> โรงเรียน</p>
  <?php if ($directoryPages>1): ?>
  <nav class="school-pagination" aria-label="แบ่งหน้ารายชื่อโรงเรียน">
   <?php if ($directoryPage>1): ?>
    <a class="school-page-control school-page-previous" rel="prev" href="<?= schoolEscape($directoryPageUrl($directoryPage-1)) ?>" aria-label="หน้าก่อนหน้า" title="หน้าก่อนหน้า"><?= workspaceIcon('next') ?></a>
   <?php else: ?>
    <span class="school-page-control school-page-previous" aria-disabled="true" aria-label="หน้าก่อนหน้า"><?= workspaceIcon('next') ?></span>
   <?php endif; ?>
   <?php $previousPage=0; foreach ($visiblePages as $page): ?>
    <?php if ($page>$previousPage+1): ?><span class="school-page-gap" aria-hidden="true">…</span><?php endif; ?>
    <?php if ($page===$directoryPage): ?>
     <span class="school-page-control" aria-current="page" aria-label="หน้าที่ <?= $page ?>"><?= $page ?></span>
    <?php else: ?>
     <a class="school-page-control" href="<?= schoolEscape($directoryPageUrl($page)) ?>" aria-label="หน้าที่ <?= $page ?>"><?= $page ?></a>
    <?php endif; $previousPage=$page; ?>
   <?php endforeach; ?>
   <?php if ($directoryPage<$directoryPages): ?>
    <a class="school-page-control" rel="next" href="<?= schoolEscape($directoryPageUrl($directoryPage+1)) ?>" aria-label="หน้าถัดไป" title="หน้าถัดไป"><?= workspaceIcon('next') ?></a>
   <?php else: ?>
    <span class="school-page-control" aria-disabled="true" aria-label="หน้าถัดไป"><?= workspaceIcon('next') ?></span>
   <?php endif; ?>
  </nav>
  <?php endif; ?>
 </div>
 <?php endif; ?>
 </div>
 <p class="notice">เกณฑ์คือพบผู้เรียน ม.4–ม.6 อย่างน้อยหนึ่งชั้น หรือมีข้อมูลว่าเปิดสอนมัธยมปลาย
  <a href="https://web2.bopp.go.th/?page_id=878" target="_blank" rel="noopener">สพฐ. 10 มิ.ย. 2569</a>
  · <a href="https://school.opec.go.th/" target="_blank" rel="noopener">ทะเบียนโรงเรียนเอกชน สช.</a>
  · <a href="https://opendata.dla.go.th/dataset/school" target="_blank" rel="noopener">ทะเบียนโรงเรียนท้องถิ่น ปี 2569</a>
  · <a href="https://www.dla.go.th/download/upload/document/type2/2567/9/32235_2_1726126055127.pdf?notDownload=Y" target="_blank" rel="noopener">หลักฐานระดับมัธยมปลายของท้องถิ่น ปี 2567</a>
  <?php if ($province==='10'): ?> · <a href="https://data.go.th/th/dataset/student2568" target="_blank" rel="noopener">ข้อมูลโรงเรียนสังกัดกรุงเทพมหานคร ปี 2568</a><?php endif; ?>
  <?php if ($province==='34'): ?> · <a href="https://www.ubnpeo.go.th/eis/" target="_blank" rel="noopener">ข้อมูลเพิ่มเติมจากศึกษาธิการจังหวัดอุบลราชธานี</a><?php endif; ?>
 </p>
</section>
<?php endif; ?>
<?php if (!$school): ?><section class="card"><p><?= $teacher?'ยังไม่มีโรงเรียนที่บัญชีนี้ดูแล':($province===''?'เลือกจังหวัดแล้วกดแสดงโรงเรียน':'ยังไม่มีโรงเรียนในทะเบียนของจังหวัดนี้') ?></p></section>
<?php else: ?>
<section class="card" id="school-activities" aria-labelledby="school-activities-title">
 <header class="school-selected-heading">
  <div>
   <h2 id="school-activities-title"><?= workspaceIcon('school') ?> <?= schoolEscape($school['name']) ?></h2>
   <p><?= schoolEscape($school['province']) ?> · <?= $school['enabled']?'เข้าร่วมโครงการ':'หยุดเข้าร่วมโครงการ' ?></p>
  </div>
  <?php if ($admin): ?>
   <?php schoolParticipationButton((int)$school['id'],$school['name'],(bool)$school['enabled'],$participation[$school['id']]); ?>
  <?php endif; ?>
 </header>
 <?php if (!$school['enabled']): ?><p class="notice">โรงเรียนหยุดรับผู้เข้าร่วมใหม่ ประวัติกิจกรรมยังเปิดดูได้</p><?php endif; ?>
 <form method="get" action="#school-activities" class="network-form school-fields">
  <?php if (!$teacher): ?><input type="hidden" name="view" value="schools"><?php endif; ?>
  <?php foreach ($areaFilters+array_filter($directoryFilters+['directory_page'=>$directoryPage>1?$directoryPage:null]) as $key=>$value): ?><input type="hidden" name="<?= schoolEscape($key) ?>" value="<?= schoolEscape((string)$value) ?>"><?php endforeach; ?>
  <label><span><?= workspaceIcon('school') ?> โรงเรียน</span><select name="school"><?php foreach($schools as $item): ?><option value="<?= (int)$item['id'] ?>" <?= $item['id']===$school['id']?'selected':'' ?>><?= schoolEscape($item['name'].' · '.$item['province']) ?></option><?php endforeach; ?></select></label>
  <button class="action">แสดงโรงเรียน</button>
 </form>
 <?php require __DIR__.'/school-controls.php'; ?>
</section>
<section class="card"><h2><?= workspaceIcon('book') ?> <?= $teacher && !$manage ? 'ห้องของฉัน' : 'ห้องเรียนและรุ่นกิจกรรม' ?></h2>
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
  <details class="school-cohort school-disclosure" id="school-room-<?= (int)$item['id'] ?>" <?= (int)$item['id']===($cohortId??null)?'open':'' ?>>
   <?php schoolDisclosureHeading(($item['academic_year']?'ปี '.$item['academic_year'].' · ':'').$item['title'].' · '.$item['classroom']
       .' · เป้าหมาย '.($item['participation_target']===null?'ยังไม่ตั้ง':number_format((int)$item['participation_target']))
    .' · เข้าร่วม '.number_format((int)$item['members']).' · ทำครบ '.number_format((int)$item['completed']),'book'); ?>
   <p><?= schoolEscape($item['opens_on'].' ถึง '.$item['closes_on']) ?> · <?= $item['accepting'] && $school['enabled']?'เปิดรับตามช่วงวันที่':'ปิดรับ' ?></p>
   <?php if ($manage && $item['academic_year']): ?>
    <p>ครูหลัก <?= schoolEscape($roomTeachers[$item['id']]['primary']['full_name']??'ยังไม่ได้มอบหมาย') ?> · ครูร่วม <?= schoolEscape($roomTeachers[$item['id']]['assistant']['full_name']??'ยังไม่ได้มอบหมาย') ?></p>
   <?php endif; ?>
   <?php if (($admin||$teacher) && $school['enabled']): ?>
   <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>" class="network-form school-fields">
    <?php schoolFields($csrf,'room-target',(int)$school['id'],(int)$item['id']); ?>
    <label>จำนวนเป้าหมาย (คน)<input type="number" name="target" min="1" max="65535" step="1" value="<?= $item['participation_target']===null?'':(int)$item['participation_target'] ?>" aria-describedby="school-target-help-<?= (int)$item['id'] ?>"></label>
    <button class="action" type="submit"><?= workspaceIcon('save') ?>บันทึกเป้าหมาย</button>
    <small id="school-target-help-<?= (int)$item['id'] ?>">เว้นว่างได้หากยังไม่มีจำนวนเป้าหมาย</small>
   </form>
   <?php endif; ?>
   <?php if ($admin||$teacher): ?>
    <a class="action" href="<?= schoolEscape($schoolLocation(['school'=>(int)$school['id'],'cohort'=>(int)$item['id']])) ?>">ดูความคืบหน้ารายคน</a>
    <details class="school-disclosure"><?php schoolDisclosureHeading('ลิงก์และ QR เข้าร่วม','scan'); ?><a data-school-qr href="school-join.php?code=<?= schoolEscape($item['invite']) ?>">เปิดลิงก์สำหรับนักเรียน</a><div class="school-qr"></div><button type="button" class="action" data-copy-school-link>คัดลอกลิงก์</button></details>
    <?php if ($school['enabled'] && ($manage || !$item['academic_year'])): ?>
    <details class="school-disclosure"><?php schoolDisclosureHeading('แก้ไขรุ่น','edit'); ?><form method="post" action="<?= schoolEscape($schoolPostUrl) ?>" class="network-form school-fields">
     <?php schoolFields($csrf,'cohort-edit',(int)$school['id'],(int)$item['id']); schoolCohortFields($item); ?><button class="action primary">บันทึก</button>
    </form></details>
    <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>"><?php schoolFields($csrf,'cohort-toggle',(int)$school['id'],(int)$item['id']); ?><button class="action"><?= $item['accepting']?'ปิดรับเพิ่ม':'เปิดรับเพิ่ม' ?></button></form>
    <?php endif; ?>
    <?php if ((int)$item['id']===($cohortId??null)): ?>
     <?php if ($roomHistory): ?><details class="school-disclosure"><?php schoolDisclosureHeading('ประวัติเปลี่ยนครูประจำห้อง','history'); ?><ul>
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
<?php if ($audit): ?><details class="card school-disclosure"><?php schoolDisclosureHeading('ประวัติการจัดการล่าสุด','history'); ?><ul><?php foreach($audit as $entry): ?><li><?= schoolEscape(implode(' · ',$entry)) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php if ($admin && $participation): ?>
<dialog class="workspace-dialog" id="school-participation-dialog" aria-labelledby="school-participation-title">
 <h2 id="school-participation-title"></h2>
 <p data-school-dialog-name></p>
 <p data-school-dialog-description></p>
 <p data-school-dialog-staff hidden>โรงเรียนมีข้อมูลการมอบหมายครูหรือผู้ประสานงาน</p>
 <ul data-school-dialog-years hidden></ul>
 <p class="notice" role="alert" data-school-update-error hidden></p>
 <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>" class="actions" data-school-update>
  <?php schoolFields($csrf,''); ?>
  <input type="hidden" name="school">
  <button type="button" class="action" data-school-dialog-cancel autofocus>ยกเลิก</button>
  <button type="submit" class="action primary" data-school-dialog-confirm></button>
 </form>
</dialog>
<?php endif; ?>
</div>
<?php
};
