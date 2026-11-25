<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
if (!$secure && PHP_SAPI !== 'cli-server') { http_response_code(403); exit('เปิดหน้าเจ้าหน้าที่ผ่าน HTTPS'); }
ini_set('session.use_strict_mode', '1');
session_name('anemia_staff_session');
session_set_cookie_params(['secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);
session_start();
function loginRequired(): never
{
    header('Location: login'.(($_GET['view'] ?? '')==='scan-request'?'?next=scan':''), true, 303);
    exit;
}
if (!isset($_SESSION['username'], $_SESSION['until']) || (int)$_SESSION['until'] <= time()) {
    $_SESSION = [];
    loginRequired();
}
define('ANEMIA_STAFF_BOOT', true);
require __DIR__ . '/_private/request-states.php';
function esc(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function zoneLabel(int $region): string
{
    return $region === 13 ? 'เขต 13 · กรุงเทพมหานคร' : 'เขตสุขภาพที่ ' . $region;
}
$view = $_GET['view'] ?? 'overview';
if (!in_array($view, ['overview', 'reports', 'assessment', 'access', 'evaluation', 'audit', 'sample', 'team', 'guide', 'about', 'release', 'activity', 'sample-settings', 'contact-queue', 'scan-request'], true)) $view = 'overview';
date_default_timezone_set('Asia/Bangkok');
$evaluationAudience = $_GET['audience'] ?? '';
$evaluationMonth = $_GET['month'] ?? date('Y-m');
if (!is_string($evaluationAudience)) $evaluationAudience='';
if (!is_string($evaluationMonth) || !preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D',$evaluationMonth)) $evaluationMonth='';
$region = filter_input(INPUT_GET, 'region', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>13]]) ?: 0;
try {
    $config = require __DIR__ . '/_private/config.php';
    require __DIR__ . '/_private/database.php';
    $db = anemiaDatabase($config);
    initializeAnemia($db, $config);
    require __DIR__ . '/_private/access.php';
    $identity = anemiaStaffIdentity($db, (string)$_SESSION['username']);
    if (!$identity) { $_SESSION = []; loginRequired(); }
    $staff = $identity['username'];
    $isAdmin = $identity['role'] === 'account_admin';
    $canChooseRegion = $isAdmin || $identity['role'] === 'central_report';
    $roleLabel = $isAdmin ? 'ผู้ดูแลระบบ' : ANEMIA_REPORT_ROLES[$identity['role']];
    if (in_array($view, ['access','assessment','audit','sample','release','sample-settings','contact-queue','scan-request'], true) && !$isAdmin) { http_response_code(403); exit('บัญชีนี้ไม่มีสิทธิ์เข้าหน้านี้'); }
    $sampleSetting = $db->query("SELECT enabled,changed_by,changed_at FROM anemia_host_setting WHERE setting_key='staff_sample'")->fetch(PDO::FETCH_ASSOC);
    $sampleEnabled = (int)($sampleSetting['enabled'] ?? 0) === 1;
    if ($view === 'sample-settings') {
        $demoReportCount = (int)$db->query('SELECT COUNT(*) FROM anemia_demo_report')->fetchColumn();
        $demoQueueCount = (int)$db->query('SELECT COUNT(*) FROM anemia_contact_demo_queue')->fetchColumn();
    }
    $sampleAllRegions = (int)$db->query("SELECT enabled FROM anemia_host_setting WHERE setting_key='staff_sample_all_regions'")->fetchColumn() === 1;
    if ($view === 'sample') {
        header('Location: dashboard.php?view=overview'.($sampleEnabled?'&preview=sample':''),true,303);
        exit;
    }
    if (!$sampleEnabled) {
        unset($_SESSION['preview_sample']);
    }
    $sampleViews = ['overview','reports','activity','evaluation','assessment','audit','access'];
    if ($sampleEnabled && in_array($_GET['preview'] ?? '', ['sample','live'], true)) {
        $_SESSION['preview_sample'] = $_GET['preview'] === 'sample';
    }
    $sampleMode = $sampleEnabled && ($_SESSION['preview_sample'] ?? true) && in_array($view,$sampleViews,true);
    if ($sampleMode) require __DIR__ . '/_private/sample-reports.php';
    if (!$canChooseRegion) $region = (int)($identity['province_region'] ?? $identity['region']);
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    $token = $_SESSION['csrf'];
    $demoQueue = $view === 'contact-queue' && ($_GET['demo'] ?? '') === '1';
    $contactTable = $demoQueue ? 'anemia_contact_demo_queue' : 'anemia_contact_queue';
    $pendingContactCounts = ['appointment'=>0,'callback'=>0];
    $pendingDemoCounts = $pendingContactCounts;
    if ($isAdmin) {
        $countSql = "SELECT request_kind,COUNT(*) FROM %s WHERE state='pending' GROUP BY request_kind";
        $pendingContactCounts = array_replace($pendingContactCounts,array_map('intval',$db->query(sprintf($countSql,'anemia_contact_queue'))->fetchAll(PDO::FETCH_KEY_PAIR)));
        if ($view === 'contact-queue') {
            $pendingDemoCounts = array_replace($pendingDemoCounts,array_map('intval',$db->query(sprintf($countSql,'anemia_contact_demo_queue'))->fetchAll(PDO::FETCH_KEY_PAIR)));
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['counts'] ?? '') === '1') {
                session_write_close();
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['live'=>$pendingContactCounts,'demo'=>$pendingDemoCounts],JSON_THROW_ON_ERROR);
                exit;
            }
        }
    }
    $scannedRequest = null;
    $scanMessage = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $view === 'scan-request') {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($token,$_POST['csrf'])) { http_response_code(403); exit('คำขอหมดอายุ'); }
        $reference = $_POST['receipt'] ?? null;
        if (is_string($reference)) $reference = strtoupper(trim($reference));
        if (is_string($reference) && preg_match('/^(?:AN|DE)-[0-9A-F]{16}$/D',$reference)) {
            $table = str_starts_with($reference,'DE-') ? 'anemia_contact_demo_queue' : 'anemia_contact_queue';
            if (($_POST['scan_action'] ?? '') === 'check_in') {
                $db->beginTransaction();
                $checkedIn = anemiaApplyRequestAction($db,$table,$reference,'attended',$staff,null);
                $db->commit();
                $scanMessage = $checkedIn ? 'ยืนยันการเข้ารับบริการแล้ว' : 'คำขอนี้ไม่อยู่ในขั้นเช็กอิน';
            }
            $lookup = $db->prepare("SELECT q.*, (SELECT MIN(changed_at) FROM anemia_request_history h WHERE h.receipt=q.receipt AND h.state='attended') AS checked_in_at FROM ".$table.' q WHERE q.receipt=?');
            $lookup->execute([$reference]);
            $scannedRequest = $lookup->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$scannedRequest) $scanMessage = 'ไม่พบคำขอ กรุณาตรวจเลขหรือสแกนใบรับคำขออีกครั้ง';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($view === 'contact-queue') {
            if (!$isAdmin || !is_string($_POST['csrf'] ?? null) || !hash_equals($token,$_POST['csrf'])) { http_response_code(403); exit('คำขอหมดอายุ'); }
            $receipt = $_POST['receipt'] ?? null;
            $state = $_POST['state'] ?? null;
            if (!is_string($receipt) || !preg_match($demoQueue ? '/^DE-[0-9A-F]{16}$/D' : '/^AN-[0-9A-F]{16}$/D',$receipt) || ($state !== null && (!is_string($state) || !isset(ANEMIA_REQUEST_STATES[$state])))) { http_response_code(400); exit('ข้อมูลไม่ถูกต้อง'); }
            $db->beginTransaction();
            $lookup = $db->prepare('SELECT state,submitted_at,appointment_at,next_contact_at FROM '.$contactTable.' WHERE receipt=? FOR UPDATE');
            $lookup->execute([$receipt]);
            $request = $lookup->fetch(PDO::FETCH_ASSOC);
            if (!$request) { $db->rollBack(); http_response_code(404); exit('ไม่พบคำขอ'); }
            if ($state !== null && ($_POST['previous'] ?? '') !== $request['state']) {
                $db->rollBack(); http_response_code(409); exit('สถานะเปลี่ยนแล้ว กรุณาเปิดไทม์ไลน์อีกครั้ง');
            }
            if ($state !== null) {
                $when = null;
                if (in_array($state,['scheduled','retry_contact','follow_up'],true)) {
                    $raw = $_POST['when'] ?? '';
                    $date = is_string($raw) && preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d$/D',$raw) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw) : false;
                    if (!$date || $date->format('Y-m-d\TH:i') !== $raw) { $db->rollBack(); http_response_code(422); exit('กรุณาระบุวันและเวลา'); }
                    $when = $date->format('Y-m-d H:i:s');
                }
                if (!in_array($request['state'],ANEMIA_REQUEST_ACTIONS[$state][1] ?? [],true)) { $db->rollBack(); http_response_code(409); exit('ดำเนินการขั้นนี้ไม่ได้'); }
                anemiaApplyRequestAction($db,$contactTable,$receipt,$state,$staff,$when);
                $lookup->execute([$receipt]);
                $request = $lookup->fetch(PDO::FETCH_ASSOC);
            }
            $request['actions'] = array_filter(ANEMIA_REQUEST_ACTIONS,static fn($action)=>in_array($request['state'],$action[1],true));
            $history = $db->prepare('SELECT state,actor,changed_at FROM anemia_request_history WHERE receipt=? ORDER BY id');
            $history->execute([$receipt]);
            $request['history'] = $history->fetchAll(PDO::FETCH_ASSOC);
            $db->commit();
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($request,JSON_THROW_ON_ERROR);
            exit;
        }
        if ($view === 'sample-settings') {
            $enabled = $_POST['sample_enabled'] ?? null;
            $allRegions = isset($_POST['sample_all_regions']) ? '1' : '0';
            if (!$isAdmin || !is_string($_POST['csrf'] ?? null) || !hash_equals($token,$_POST['csrf'])) { http_response_code(403); exit('คำขอหมดอายุ กรุณาเปิดหน้าใหม่'); }
            $demoAction = $_POST['demo_action'] ?? '';
            if ($demoAction === 'generate' || $demoAction === 'clear') {
                if ($demoAction === 'clear' && ($_POST['confirm_clear'] ?? '') !== '1') { http_response_code(400); exit('กรุณายืนยันการล้างข้อมูลจำลอง'); }
                require_once __DIR__.'/_private/demo-data.php';
                anemiaManageDemoData($db,$demoAction,$staff);
                unset($_SESSION['preview_sample']);
                $_SESSION['sample_setting_saved'] = $demoAction;
                header('Location: dashboard.php?view=sample-settings',true,303);
                exit;
            }
            if (!in_array($enabled,['0','1'],true)) { http_response_code(400); exit('กรุณาเลือกสถานะข้อมูลจำลอง'); }
            $save = $db->prepare("INSERT INTO anemia_host_setting (setting_key,enabled,changed_by,changed_at)
                VALUES ('staff_sample',?,?,?),('staff_sample_all_regions',?,?,?)
                ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),changed_by=VALUES(changed_by),changed_at=VALUES(changed_at)");
            $changedAt = date('Y-m-d H:i:s');
            $save->execute([(int)$enabled,$staff,$changedAt,(int)$allRegions,$staff,$changedAt]);
            unset($_SESSION['preview_sample']);
            $_SESSION['sample_setting_saved'] = true;
            header('Location: dashboard.php?view=sample-settings',true,303);
            exit;
        }
        if ($sampleMode) { http_response_code(403); exit('โหมดข้อมูลจำลองใช้สำหรับดูตัวอย่าง'); }
        if ($view !== 'access' || !$isAdmin) { http_response_code(403); exit('บัญชีนี้ไม่มีสิทธิ์แก้ข้อมูล'); }
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($token, $_POST['csrf'])) { http_response_code(403); exit('คำขอหมดอายุ กรุณาเปิดหน้าใหม่'); }
        $action = $_POST['action'] ?? '';
        $data = anemiaAccessInput($_POST);
        try {
            if (!$data || !in_array($action, ['create','update'], true)) throw new DomainException('ตรวจชื่อผู้ใช้ บทบาท และพื้นที่อีกครั้ง');
            $_SESSION['access_notice'] = saveAnemiaAccess($db, $staff, $action, $data);
        } catch (DomainException $error) {
            $_SESSION['access_notice'] = ['error'=>$error->getMessage()];
        }
        header('Location: dashboard.php?view=access', true, 303);
        exit;
    }
    [$where,$params] = anemiaProvinceScope($identity, in_array($view, ['reports','overview','activity','evaluation'], true) ? $region : 0);
    $filter = $where ? ' WHERE ' . $where : '';
    $query = $db->prepare('SELECT COUNT(*) AS provinces, COUNT(DISTINCT region) AS regions, COALESCE(SUM(enabled=1),0) AS province_enabled FROM anemia_province' . $filter);
    $query->execute($params);
    $stats = $query->fetch(PDO::FETCH_ASSOC);
    $query = $db->prepare('SELECT code,name,region,enabled FROM anemia_province' . $filter . ' ORDER BY region,code');
    $query->execute($params);
    $provinces = $query->fetchAll(PDO::FETCH_ASSOC);
    $demoScope = $identity['role'] === 'provincial_report' ? 'p:'.$identity['province_code'] : ($region ? 'r:'.$region : 'all');
    if ($sampleMode) {
        $provinces = anemiaSampleProvinces($provinces,$sampleAllRegions);
        $stats['province_enabled'] = array_sum(array_column($provinces,'enabled'));
    }
    $attentionOnly = ($_GET['attention'] ?? '') === '1';
    $queueNow = $db->quote(date('Y-m-d H:i:s'));
    $overdue = "(state='scheduled' AND appointment_at<$queueNow)";
    $attention = "($overdue OR state IN ('pending','accepted') OR (state IN ('retry_contact','follow_up') AND next_contact_at<=$queueNow))";
    $contactQueue = $view === 'contact-queue' ? $db->query('SELECT receipt,facility,request_kind,contact_name,phone,preferred_date,submitted_at,state,'.$overdue.' AS overdue FROM '.$contactTable.($attentionOnly?' WHERE '.$attention:'').' ORDER BY submitted_at DESC,receipt DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC) : [];
    $forms = $view === 'assessment' ? $db->query('SELECT version_key,state FROM anemia_form_version ORDER BY version_key')->fetchAll(PDO::FETCH_ASSOC) : [];
    $accessAccounts = $view === 'access' ? $db->query('SELECT s.username,s.role,s.enabled,a.region,a.province_code FROM anemia_staff s LEFT JOIN anemia_staff_access a ON a.username=s.username ORDER BY s.username')->fetchAll(PDO::FETCH_ASSOC) : [];
    $auditRows = $view === 'audit' ? $db->query('SELECT actor,target_username,action_name,role_name,region,province_code,enabled,changed_at FROM anemia_access_audit ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC) : [];
    if ($sampleMode && $view === 'assessment') {
        $forms = [['version_key'=>'sample-10q-v1','state'=>'draft'],['version_key'=>'sample-10q-v2','state'=>'เปิดใช้ในตัวอย่าง']];
    }
    if ($sampleMode && $view === 'access') {
        $accessAccounts = [['username'=>'sample-admin','role'=>'account_admin','enabled'=>1,'region'=>null,'province_code'=>null]];
        for ($zone=1;$zone<=13;$zone++) {
            $accessAccounts[] = ['username'=>sprintf('sample-zone-%02d',$zone),'role'=>'regional_report',
                'region'=>$zone,'province_code'=>null,'enabled'=>(int)($zone%4 !== 0)];
        }
    }
    if ($sampleMode && $view === 'audit') {
        $auditRows = [];
        for ($zone=1;$zone<=13;$zone++) {
            $auditRows[] = ['actor'=>'sample-admin','target_username'=>sprintf('sample-zone-%02d',$zone),
                'action_name'=>$zone%2 ? 'create' : 'update','role_name'=>'regional_report','region'=>$zone,
                'province_code'=>null,'enabled'=>(int)($zone%4 !== 0),'changed_at'=>date('Y-m-01 09:00:00')];
        }
    }
    $regionTotals = [];
    if ($view === 'overview') {
        foreach ($provinces as $province) {
            $zone = (int)$province['region'];
            $regionTotals[$zone] ??= ['all'=>0,'enabled'=>0];
            $regionTotals[$zone]['all']++;
            $regionTotals[$zone]['enabled'] += (int)$province['enabled'];
        }
    }
    if (in_array($view, ['overview','evaluation'], true)) {
        require_once __DIR__ . '/_private/evaluation.php';
        if (!isset(EVALUATION_AUDIENCES[$evaluationAudience])) $evaluationAudience='';
        $selectedVersion = ($_GET['version'] ?? '') === EVALUATION_LEGACY_VERSION && $view === 'evaluation' ? EVALUATION_LEGACY_VERSION : EVALUATION_VERSION;
        $selectedMonth = $view === 'overview' ? date('Y-m') : $evaluationMonth;
        $selectedAudience = $view === 'overview' ? '' : $evaluationAudience;
        if ($sampleMode && $selectedMonth === '') $evaluationMonth = $selectedMonth = date('Y-m');
        if ($sampleMode) require_once __DIR__ . '/_private/demo-data.php';
        $evaluation = $sampleMode
            ? (anemiaDemoReport($db,$demoScope,$selectedMonth)['evaluation'][$selectedAudience] ?? anemiaSampleEvaluation([],$selectedMonth,$selectedAudience))
            : evaluationReport($db,$identity,$region,$selectedAudience,$selectedMonth,$view === 'evaluation' && ($_GET['download'] ?? '') !== 'csv',$selectedVersion);
    }
    if (in_array($view, ['overview','activity'], true)) {
        require_once __DIR__ . '/_private/activity.php';
        $activityMonth = $view === 'activity' && $evaluationMonth !== '' ? $evaluationMonth : date('Y-m');
        [$activityWhere,$activityParams] = anemiaProvinceScope($identity,$region);
        if ($sampleMode) require_once __DIR__ . '/_private/demo-data.php';
        $activity = $sampleMode ? (anemiaDemoReport($db,$demoScope,$activityMonth)['activity'] ?? anemiaSampleActivity([],$activityMonth)) : anemiaActivitySummary($db,$activityMonth,$activityWhere,$activityParams);
    }
    $accessNotice = $sampleMode ? null : ($_SESSION['access_notice'] ?? null);
    if ($view === 'access' && !$sampleMode) unset($_SESSION['access_notice']);
    $sampleSettingSaved = $view === 'sample-settings' ? ($_SESSION['sample_setting_saved'] ?? false) : false;
    if ($view === 'sample-settings') unset($_SESSION['sample_setting_saved']);
    session_write_close();
    require __DIR__ . '/_private/release.php';
    $localRelease = anemiaRelease((string)@file_get_contents(__DIR__ . '/release.json'));
    if ($view === 'release') {
        $releaseSource = getenv('ANEMIA_RELEASE_MANIFEST') ?: '';
        $releaseChecked = $releaseSource !== '' && ($_GET['check'] ?? '') === '1';
        $centralRelease = $releaseChecked ? anemiaReadCentralRelease($releaseSource) : null;
    }
    if ($sampleMode && isset($_GET['download'])) { http_response_code(403); exit('ปิดการดาวน์โหลดในโหมดข้อมูลจำลอง'); }
    if ($view === 'evaluation' && ($_GET['download'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="anemia-satisfaction-summary.csv"');
        $out=fopen('php://output','w');
        fwrite($out,chr(239).chr(187).chr(191));
        $scopeLabel=$identity['role']==='provincial_report'?'จังหวัดรหัส '.$identity['province_code']:($region?zoneLabel($region):'ทุกเขตและไม่ระบุจังหวัด');
        $n=(int)$evaluation['overall']['n'];
        $meta=[$selectedVersion,$scopeLabel,$evaluationMonth?:'ทุกเดือน',EVALUATION_AUDIENCES[$evaluationAudience]??'ทุกกลุ่ม'];
        $write=static function(array $cells) use ($out,$meta): void { fputcsv($out,[...$meta,...$cells],',','"',''); };
        fputcsv($out,['รุ่นแบบประเมิน','พื้นที่','เดือน','กลุ่มผู้ตอบ','รายการ','จำนวนผู้ตอบ','จำนวนคำตอบ','ค่าเฉลี่ยเต็ม 5','ส่วนเบี่ยงเบนมาตรฐาน','ร้อยละระดับมากขึ้นไป'],',','"','');
        $write(['ความพึงพอใจรวม',$n,$n,$n?round((float)$evaluation['overall']['average'],2):'',isset($evaluation['overall']['sd'])?round((float)$evaluation['overall']['sd'],2):'',$n?round((int)$evaluation['overall']['satisfied']*100/$n,1):'']);
        foreach(EVALUATION_DOMAINS as $key=>$label){
            $data=$evaluation['domains'][$key]??null;
            $write([$label,(int)($data['respondents']??0),(int)($data['n']??0),$data?round((float)$data['average'],2):'',isset($data['sd'])?round((float)$data['sd'],2):'','']);
        }
        foreach(EVALUATION_TOPICS as $key=>$label)$write(['ข้อเสนอแนะ: '.$label,'',(int)($evaluation['topics'][$key]??0),'','','']);
        exit;
    }
    if ($view === 'reports' && ($_GET['download'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="anemia-province-readiness.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, chr(239).chr(187).chr(191));
        fputcsv($out, ['รหัสจังหวัด','จังหวัด','เขตสุขภาพ','สถานะจังหวัด'], ',', '"', '');
        foreach ($provinces as $p) fputcsv($out, [$p['code'], $p['name'], (int)$p['region'], (int)$p['enabled'] ? 'ตั้งสถานะเปิด' : 'ปิด'], ',', '"', '');
        exit;
    }
} catch (Throwable $error) {
    http_response_code(503);
    error_log('Anemia dashboard failed (' . get_class($error) . ')');
    exit('ไม่สามารถโหลดข้อมูลหลังบ้านได้ กรุณาลองใหม่อีกครั้ง');
}
$tabs = ['overview'=>'ภาพรวม','reports'=>'รายงานจังหวัด','evaluation'=>'ประเมินโครงการ','activity'=>'สถิติการทดลองแบบประเมิน','team'=>'ทีมวิจัยและพัฒนา','guide'=>'คู่มือการใช้งาน','about'=>'เกี่ยวกับระบบ'];
if ($isAdmin) $tabs += ['assessment'=>'แบบประเมิน','access'=>'จัดการสิทธิ์','audit'=>'ประวัติสิทธิ์','release'=>'เวอร์ชันและอัปเดต','sample-settings'=>'จัดการข้อมูล','contact-queue'=>'คำขอนัดและติดต่อกลับ','scan-request'=>'สแกน QR คำขอ'];
$showSample = $isAdmin && $view === 'overview' && $sampleMode;
$tabIcons = ['overview'=>'chart','reports'=>'map','evaluation'=>'ready','activity'=>'chart','assessment'=>'report','access'=>'users','audit'=>'history','team'=>'users','guide'=>'book','about'=>'info','release'=>'history','sample-settings'=>'chart','contact-queue'=>'phone','scan-request'=>'scan'];
$scopeName = $identity['role'] === 'provincial_report' ? ($provinces[0]['name'] ?? 'จังหวัดที่รับผิดชอบ') : ($region ? zoneLabel($region) : 'ทุกเขตสุขภาพ');
function accessFields(array $record, array $provinces): void
{
    ?>
    <label>บทบาท<select name="role" required>
     <?php foreach (ANEMIA_REPORT_ROLES as $key=>$label): ?><option value="<?= esc($key) ?>" <?= ($record['role'] ?? '') === $key ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?>
    </select></label>
    <label>เขตสำหรับบัญชีระดับเขต<select name="region"><option value="">เลือกเขต</option>
     <?php for ($i=1;$i<=13;$i++): ?><option value="<?= $i ?>" <?= (int)($record['region'] ?? 0) === $i ? 'selected' : '' ?>><?= esc(zoneLabel($i)) ?></option><?php endfor; ?>
    </select></label>
    <label>จังหวัดสำหรับบัญชีระดับจังหวัด<select name="province"><option value="">เลือกจังหวัด</option>
     <?php foreach ($provinces as $province): ?><option value="<?= esc($province['code']) ?>" <?= ($record['province_code'] ?? '') === $province['code'] ? 'selected' : '' ?>><?= esc($province['name']) ?></option><?php endforeach; ?>
    </select></label>
    <label class="admin-check"><input type="checkbox" name="enabled" value="1" <?= (int)($record['enabled'] ?? 1) ? 'checked' : '' ?>> เปิดให้เข้าใช้งาน</label>
    <?php
}
function workspaceThaiMonth(string $month): string
{
    $months = [1=>'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    [$year,$number] = array_map('intval',explode('-',$month));
    return $months[$number] . ' ' . ($year + 543);
}

function workspaceMonthOptions(string $selected, bool $allowAll): void
{
    $current = new DateTimeImmutable('first day of this month');
    $months = [];
    for ($offset=0;$offset<36;$offset++) $months[] = $current->modify('-' . $offset . ' months')->format('Y-m');
    if ($selected !== '' && !in_array($selected,$months,true)) { $months[] = $selected; rsort($months); }
    if ($allowAll) echo '<option value="">ทุกเดือน</option>';
    foreach ($months as $month) {
        echo '<option value="' . esc($month) . '"' . ($month === $selected ? ' selected' : '') . '>' . esc(workspaceThaiMonth($month)) . '</option>';
    }
}

require __DIR__ . '/_private/icons.php';

function workspaceInfo(string $page, ?array $release = null): void
{
    if ($page === 'about'): ?>
   <h2>Anemia Portal</h2>
   <?php if ($release): ?>
   <p class="status"><?= workspaceIcon('history') ?> <strong>เวอร์ชัน <?= esc($release['version']) ?></strong><?php if (isset($release['build'])): ?> · build <?= esc($release['build']) ?><?php endif; ?></p>
   <?php endif; ?>
   <p>เว็บแอปโครงการประเมินตนเองเกี่ยวกับภาวะโลหิตจางสำหรับหญิงวัยเจริญพันธุ์ มีคลังความรู้และแบบประเมินความพึงพอใจ</p>
   <p>หลังบ้านแสดงทะเบียนพื้นที่ครบ 13 เขตสุขภาพ และสรุปผลประเมินความพึงพอใจตามสิทธิ์ของบัญชี</p>
   <p>แบบประเมินสุขภาพและคำขอติดต่อกลับอยู่ในขั้นต้นแบบ</p>
   <a class="action" href="index.html"><?= workspaceIcon('home') ?>เปิดหน้าบ้าน</a>
    <?php else: ?>
   <h2>ศูนย์อนามัยที่ ๑๐ จังหวัดอุบลราชธานี</h2><p>กรมอนามัย กระทรวงสาธารณสุข</p>
   <h3>ทีมพัฒนา</h3><p>ดูแลเว็บแอป แบบประเมินออนไลน์ และระบบรายงานสำหรับเจ้าหน้าที่</p>
   <h3>ทีมวิจัย</h3><p>ดูแลการออกแบบแบบประเมิน การเก็บข้อมูล และการวิเคราะห์ผลโครงการ</p>
    <?php endif;
}

?>
<!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= esc($tabs[$view]) ?> · Anemia Portal</title><script src="theme.js?v=1"></script><link rel="stylesheet" href="style.css?v=staff-workspace-v33-logout">
<?php if ($showSample): ?>
<script defer src="health-region-map.js"></script>
<script defer src="national-registry.js"></script>
<script defer src="admin.js?v=hosted-sample-v9"></script>
<script defer src="hosted-dashboard.js?v=scroll-v3"></script>
<?php endif; ?>
<?php if ($view === 'scan-request'): ?><script defer src="jsQR.js?v=1.4.0"></script><script defer src="request-scanner.js?v=3"></script><?php endif; ?>
</head><body class="staff-dashboard-page">
<main style="max-width:1220px">
<?php workspaceBack($view === 'overview' ? 'index.html' : 'dashboard.php?view=overview'); ?>
<section class="panel wide" style="padding:0;overflow:hidden">
<div class="admin-shell hosted-workspace">
 <header class="admin-top no-print">
  <div class="workspace-header-art" aria-hidden="true"></div>
  <div class="admin-top-main">
   <div class="admin-brand"><span class="workspace-brand-icon"><?= workspaceIcon('drop') ?></span><div><strong>Anemia Portal</strong><span class="workspace-brand-caption">ศูนย์จัดการสำหรับเจ้าหน้าที่</span></div></div>
   <div class="workspace-account">
    <div class="workspace-identity"><span class="workspace-account-icon"><?= workspaceIcon('users') ?></span><div><strong><?= esc($staff) ?></strong><small><?= esc($roleLabel) ?></small></div></div>
    <form class="hosted-logout" method="post" action="login"><input type="hidden" name="csrf" value="<?= esc($token) ?>"><button class="action hosted-logout-button" name="action" value="logout" aria-label="ออกจากระบบ" title="ออกจากระบบ"><?= workspaceIcon('logout') ?></button></form>
   </div>
  </div>
  <nav class="admin-top-tabs" aria-label="เมนูหลังบ้าน">
   <?php
    $groups = [
        ['ภาพรวม','chart',['overview']],
        ['รายงาน','map',['reports','activity','evaluation']],
        ['แบบประเมิน','ready',['assessment']],
        ['นัดหมาย','calendar',['contact-queue','scan-request']],
        ['ระบบ','users',['access','audit','sample-settings']],
        ['ช่วยเหลือ','book',['guide','about','team','release']],
    ];
    foreach ($groups as [$groupLabel,$groupIcon,$keys]):
        $items = array_intersect_key(array_flip($keys), $tabs);
        if (!$items) continue;
        $keys = array_keys($items);
        $hasCurrent = in_array($view,$keys,true);
        if (count($keys) === 1): $key = $keys[0];
   ?>
    <a class="admin-tab" href="dashboard.php?view=<?= esc($key) ?>" <?= $hasCurrent ? 'aria-current="page"' : '' ?>><?= workspaceIcon($groupIcon) ?><?= esc($groupLabel) ?></a>
   <?php else: ?>
    <details class="hosted-menu-group<?= in_array($keys[0],['contact-queue','access'],true)?' hosted-menu-align-end':'' ?>" name="hosted-navigation" <?= $hasCurrent ? 'data-current' : '' ?>>
     <summary class="admin-tab">
      <?= workspaceIcon($groupIcon) ?><?= esc($groupLabel) ?>
      <?php if ($keys[0] === 'contact-queue'): $pendingAppointments = $pendingContactCounts['appointment']; ?>
       <span class="contact-pending-badge" data-pending-count="live.appointment" aria-label="คำขอนัดรอประสาน <?= $pendingAppointments ?> รายการ" <?= $pendingAppointments < 1 ? 'hidden' : '' ?>><?= $pendingAppointments > 99 ? '99+' : $pendingAppointments ?></span>
      <?php endif; ?>
      <span class="hosted-menu-caret" aria-hidden="true"></span>
     </summary>
     <div class="hosted-menu-links">
      <?php foreach ($keys as $key): ?>
       <a href="dashboard.php?view=<?= esc($key) ?>" <?= $view === $key ? 'aria-current="page"' : '' ?> <?php if (in_array($key,['about','team'],true)): ?>data-workspace-dialog="<?= esc($key) ?>" aria-haspopup="dialog"<?php endif; ?>><?= workspaceIcon($tabIcons[$key]) ?><?= esc($tabs[$key]) ?></a>
      <?php endforeach; ?>
     </div>
    </details>
   <?php endif; endforeach; ?>
  </nav>
 </header>
 <div class="admin-content">
  <div class="admin-heading"><h1 class="title"><?= esc($view === 'overview' ? 'ภาพรวมการคัดกรองและติดตาม' : $tabs[$view]) ?></h1>
   <p><?= esc($scopeName) ?></p></div>
  <?php if ($sampleEnabled && in_array($view,$sampleViews,true)): ?>
   <div class="hosted-sample-caption"><strong><?= $sampleMode ? 'ข้อมูลจำลอง' : 'ข้อมูลจากระบบ' ?></strong>
    <a class="action no-print" href="dashboard.php?<?= esc(http_build_query(['view'=>$view,'region'=>$region,'month'=>$evaluationMonth,'audience'=>$evaluationAudience,'preview'=>$sampleMode?'live':'sample'])) ?>"><?= workspaceIcon($sampleMode ? 'history' : 'chart') ?><?= $sampleMode ? 'กลับไปดูข้อมูลจริง' : 'ดูข้อมูลจำลอง' ?></a>
   </div>
  <?php endif; ?>
  <?php if ($showSample): ?>
  <section class="hosted-sample" aria-label="แดชบอร์ดข้อมูลจำลอง">
   <div id="hosted-sample-dashboard"><noscript>เปิด JavaScript เพื่อดูกราฟ แผนที่ และตัวกรอง</noscript></div>
   <script>
   (() => {
    const report = document.getElementById('hosted-sample-dashboard');
    report.setAttribute('aria-busy','true');
    report.innerHTML = '<div class="report-loading-card sample-report-loading" role="status"><span class="report-spinner" aria-hidden="true"></span>กำลังเตรียมกราฟและรายงาน</div>';
    document.addEventListener('DOMContentLoaded', () => {
     if (report.getAttribute('aria-busy') !== 'true') return;
     report.setAttribute('aria-busy','false');
     report.textContent = 'ยังเปิดกราฟและรายงานไม่ได้ กรุณาโหลดหน้าใหม่';
    },{once:true});
   })();
   </script>
  </section>
  <?php endif; ?>
  <?php if ($view === 'overview'): ?>
  <?php if ($canChooseRegion): ?>
  <form class="admin-filters hosted-scope-filter hosted-filter-row" method="get"><input type="hidden" name="view" value="overview">
   <label>เขตสุขภาพ<select name="region"><option value="0">ทุกเขตสุขภาพ</option>
   <?php for ($i=1;$i<=13;$i++): ?><option value="<?= $i ?>" <?= $region === $i ? 'selected' : '' ?>><?= esc(zoneLabel($i)) ?></option><?php endfor; ?>
   </select></label><button class="action" type="submit"><?= workspaceIcon('filter') ?>แสดงข้อมูล</button>
  </form><?php endif; ?>
  <?php $responseCount=(int)$evaluation['overall']['n']; ?>
  <section class="card admin-report hosted-live-summary">
   <div class="hosted-registry-heading"><span class="iconbox"><?= workspaceIcon('ready') ?></span><div><h2>ประเมินการใช้งาน</h2><p><?= $sampleMode ? 'ตัวอย่างเดือน' : 'ข้อมูลที่ส่งจริงในเดือน' ?> <?= esc(date('m/Y')) ?></p></div></div>
   <dl><div><dt>แบบประเมินที่ได้รับ</dt><dd><?= number_format($responseCount) ?></dd></div>
   <div><dt>ความพึงพอใจเฉลี่ย เต็ม 5</dt><dd><?= $responseCount ? number_format((float)$evaluation['overall']['average'],2) : '—' ?></dd></div></dl>
   <a class="action" href="dashboard.php?view=evaluation&amp;region=<?= $region ?>"><?= workspaceIcon('report') ?>เปิดรายงานประเมินโครงการ</a>
  </section>
  <section class="card admin-report hosted-live-summary">
   <div><h2>การใช้งานแบบประเมินต้นแบบ</h2><p>จำนวนครั้งในเดือน <?= esc(workspaceThaiMonth($activityMonth)) ?></p></div>
   <dl><div><dt>เริ่มทดลอง</dt><dd><?= number_format($activity['counts']['start']) ?></dd></div>
   <div><dt>ทำรอบแรกครบ</dt><dd><?= number_format($activity['counts']['first']) ?></dd></div></dl>
   <a class="action" href="dashboard.php?view=activity&amp;region=<?= $region ?>"><?= workspaceIcon('chart') ?>ดูสถิติแต่ละขั้นตอน</a>
  </section>
  <section class="hosted-registry-section" aria-label="ทะเบียนพื้นที่จากฐานข้อมูลจริง">
  <h2><?= $sampleMode ? 'ทะเบียนพื้นที่ตัวอย่าง' : 'ทะเบียนจากฐานข้อมูลบนโฮสต์' ?></h2>
  <div class="admin-kpis hosted-kpis">
    <?php foreach ([
      ['map','เขตสุขภาพ','regions'],
      ['province','จังหวัดในทะเบียน','provinces'],
      ['ready','จังหวัดที่ตั้งสถานะเปิด','province_enabled'],
    ] as [$symbol,$label,$key]): ?>
      <section class="card"><span class="iconbox"><?= workspaceIcon($symbol) ?></span><div class="admin-kpi-copy"><span><?= esc($label) ?></span><strong><?= number_format((int)$stats[$key]) ?></strong></div></section>
    <?php endforeach; ?>
  </div>
  <section class="card admin-report hosted-registry">
   <div class="hosted-registry-heading"><span class="iconbox"><?= workspaceIcon('report') ?></span><div><h2>ทะเบียนพื้นที่</h2><p>ดูรายชื่อจังหวัด เขตสุขภาพ และสถานะการเตรียมเปิดบริการ</p></div></div>
   <a class="action primary" href="dashboard.php?view=reports&amp;region=<?= $region ?>"><?= workspaceIcon('map') ?>ดูรายงานจังหวัด</a>
  </section>
  <section class="card admin-report hosted-region-progress"><h2>สถานะทะเบียนรายเขต</h2>
   <?php foreach ($regionTotals as $zone=>$totals): ?>
   <a href="dashboard.php?view=reports&amp;region=<?= $zone ?>"><span><?= esc(zoneLabel($zone)) ?></span><meter min="0" max="<?= $totals['all'] ?>" value="<?= $totals['enabled'] ?>" aria-label="จังหวัดที่ตั้งสถานะเปิด"></meter><strong><?= $totals['enabled'] ?> / <?= $totals['all'] ?></strong></a>
   <?php endforeach; ?>
  </section></section>
  <?php elseif ($view === 'activity'): ?>
  <form class="admin-filters hosted-scope-filter hosted-filter-row" method="get"><input type="hidden" name="view" value="activity">
   <?php if ($canChooseRegion): ?><label>เขตสุขภาพ<select name="region"><option value="0">ทุกเขต</option>
    <?php for ($i=1;$i<=13;$i++): ?><option value="<?= $i ?>" <?= $region === $i ? 'selected' : '' ?>><?= esc(zoneLabel($i)) ?></option><?php endfor; ?>
   </select></label><?php endif; ?>
   <label>เดือนที่เริ่มประเมิน<select name="month"><?php workspaceMonthOptions($activityMonth,false); ?></select></label>
   <button class="action" type="submit"><?= workspaceIcon('filter') ?>แสดงข้อมูล</button>
  </form>
  <?php
   $activityCounts = $activity['counts'];
   $peak = max(1,$activityCounts['start'],...array_values($activityCounts));
   $riskLabels = ['low'=>'ต่ำ','medium'=>'ปานกลาง','high'=>'สูง','unknown'=>'ยังสรุปไม่ได้'];
   $riskTotals = array_map('array_sum',$activity['outcomes']);
  ?>
  <section class="knowledge-hero page-heading staff-data-hero">
   <span class="iconbox"><?= workspaceIcon('chart') ?></span>
   <div><h2>ภาพรวมการประเมิน</h2><p><?= esc($scopeName) ?> · <?= esc(workspaceThaiMonth($activityMonth)) ?> · <?= $sampleMode?'ข้อมูลจำลอง':'ข้อมูลจากระบบ' ?></p></div>
  </section>
  <div class="public-live-metrics staff-data-metrics">
   <?php foreach (['start'=>'เริ่มประเมิน','first'=>'ทำรอบแรกครบ','review_done'=>'ทบทวนแล้ว','directory'=>'เปิดค้นหาสถานบริการ'] as $key=>$label): ?>
    <div><span class="iconbox"><?= workspaceIcon($key==='directory'?'province':'chart') ?></span><div><span><?= esc($label) ?></span><strong><?= number_format($activityCounts[$key]) ?></strong></div></div>
   <?php endforeach; ?>
  </div>
  <div class="staff-data-charts">
   <section class="card admin-chart-card"><h2>ทำแบบประเมินถึงข้อไหน</h2><p>จำนวนรอบที่ตอบถึงแต่ละข้อ เทียบกับยอดสูงสุดในช่วงที่เลือก</p>
    <?php for ($step=1;$step<=10;$step++): $count=$activityCounts['step_'.$step]; ?>
     <div class="staff-data-bar"><span>ข้อ <?= $step ?></span><meter min="0" max="<?= $peak ?>" value="<?= $count ?>" aria-label="จำนวนรอบที่ตอบถึงข้อ <?= $step ?>"></meter><strong><?= number_format($count) ?></strong></div>
    <?php endfor; ?>
   </section>
   <section class="card admin-chart-card"><h2>ระดับผลก่อนและหลังทบทวน</h2><p>ร้อยละคิดจากยอดของแต่ละรอบ ใช้ดูการกระจายของผล</p>
    <?php foreach ($riskLabels as $levelKey=>$label): ?>
     <div class="staff-data-risk"><h3><?= esc($label) ?></h3>
      <?php foreach (['first'=>'ครั้งแรก','review_done'=>'หลังทบทวน'] as $roundKey=>$roundLabel):
       $count=$activity['outcomes'][$roundKey][$levelKey] ?? 0; $total=$riskTotals[$roundKey];
      ?>
       <div class="staff-data-bar"><span><?= esc($roundLabel) ?></span><meter min="0" max="<?= max(1,$total) ?>" value="<?= $count ?>" aria-label="<?= esc($label.' '.$roundLabel) ?>"></meter><strong><?= number_format($count) ?> · <?= $total?number_format(100*$count/$total,1).'%':'—' ?></strong></div>
      <?php endforeach; ?>
     </div>
    <?php endforeach; ?>
   </section>
  </div>
  <section class="card admin-report"><h2>การใช้งานแต่ละขั้นตอน</h2>
   <p>นับครั้งต่อรอบทดลอง ผู้ที่ไม่ระบุจังหวัดรวมอยู่ในรายงานทุกเขตของส่วนกลาง</p>
   <div class="tablewrap"><table class="admin-table"><thead><tr><th>ขั้นตอน</th><th>จำนวนครั้ง</th></tr></thead><tbody>
    <?php foreach (ANEMIA_ACTIVITY_LABELS as $key=>$label): ?><tr><th scope="row"><?= esc($label) ?></th><td><?= number_format($activity['counts'][$key]) ?></td></tr><?php endforeach; ?>
   </tbody></table></div>
  </section>
  <section class="card admin-report"><h2>ระดับผลตัวอย่าง</h2>
   <div class="tablewrap"><table class="admin-table"><thead><tr><th>ระดับ</th><th>รอบแรก</th><th>หลังทบทวน</th></tr></thead><tbody>
    <?php foreach (['low'=>'ต่ำ','medium'=>'ปานกลาง','high'=>'สูง','unknown'=>'ยังสรุปไม่ได้'] as $key=>$label): ?>
     <tr><th scope="row"><?= esc($label) ?></th><td><?= number_format($activity['outcomes']['first'][$key] ?? 0) ?></td><td><?= number_format($activity['outcomes']['review_done'][$key] ?? 0) ?></td></tr>
    <?php endforeach; ?>
   </tbody></table></div>
  </section>
  <?php elseif ($view === 'reports'): ?>
  <div class="admin-report-toolbar no-print">
   <?php if ($canChooseRegion): ?>
   <form class="admin-filters hosted-filter-row" method="get"><input type="hidden" name="view" value="reports">
    <label>เขตสุขภาพ<select name="region"><option value="0">ทุกเขต</option>
     <?php for ($i=1;$i<=13;$i++): ?><option value="<?= $i ?>" <?= $region === $i ? 'selected' : '' ?>><?= esc(zoneLabel($i)) ?></option><?php endfor; ?>
    </select></label><button class="action" type="submit"><?= workspaceIcon('filter') ?>แสดง</button>
   </form>
   <?php endif; ?>
   <div class="actions"><?php if (!$sampleMode): ?><a class="action" href="dashboard.php?view=reports&amp;region=<?= $region ?>&amp;download=csv"><?= workspaceIcon('download') ?>ดาวน์โหลด CSV</a><?php endif; ?><button type="button" class="action primary" onclick="window.print()"><?= workspaceIcon('print') ?>พิมพ์รายงาน</button></div>
  </div>
  <section class="card admin-report"><h2><?= $identity['role'] === 'provincial_report' ? esc($provinces[0]['name'] ?? 'จังหวัดที่รับผิดชอบ') : ($region ? esc(zoneLabel($region)) : 'ทุกเขตสุขภาพ') ?></h2>
  <div class="tablewrap"><table class="admin-table"><thead><tr><th>รหัส</th><th>จังหวัด</th><th>เขตสุขภาพ</th><th>สถานะจังหวัด</th></tr></thead><tbody>
  <?php foreach ($provinces as $p): ?><tr><td><?= esc($p['code']) ?></td><th scope="row"><?= esc($p['name']) ?></th><td><?= esc(zoneLabel((int)$p['region'])) ?></td><td><?= (int)$p['enabled'] ? 'ตั้งสถานะเปิด' : 'ปิด' ?></td></tr><?php endforeach; ?>
  </tbody></table></div></section>
  <?php elseif ($view === 'evaluation'):
     $total=(int)$evaluation['overall']['n'];
     $average=$total?number_format((float)$evaluation['overall']['average'],2):'—';
     $satisfied=$total?number_format((int)$evaluation['overall']['satisfied']*100/$total,1).'%':'—';
     $exportLink='dashboard.php?'.http_build_query(['view'=>'evaluation','region'=>$region,'audience'=>$evaluationAudience,'month'=>$evaluationMonth,'version'=>$selectedVersion,'download'=>'csv']);
  ?>
  <div class="admin-report-toolbar no-print">
  <form class="admin-filters evaluation-filters hosted-filter-row" method="get"><input type="hidden" name="view" value="evaluation">
   <?php if($canChooseRegion): ?><label>พื้นที่<select name="region"><option value="0">ทุกเขต</option>
    <?php for($i=1;$i<=13;$i++): ?><option value="<?= $i ?>" <?= $region===$i?'selected':'' ?>><?= esc(zoneLabel($i)) ?></option><?php endfor; ?>
   </select></label><?php endif; ?>
   <label>กลุ่มผู้ตอบ<select name="audience"><option value="">ทุกกลุ่ม</option>
    <?php foreach(EVALUATION_AUDIENCES as $key=>$label): ?><option value="<?= esc($key) ?>" <?= $evaluationAudience===$key?'selected':'' ?>><?= esc($label) ?></option><?php endforeach; ?>
   </select></label>
   <label>รุ่นแบบประเมิน<select name="version"><?php foreach ([EVALUATION_VERSION,EVALUATION_LEGACY_VERSION] as $version): ?><option <?= $version === $selectedVersion ? 'selected' : '' ?>><?= esc($version) ?></option><?php endforeach; ?></select></label>
   <label>เดือน <select name="month"><?php workspaceMonthOptions($evaluationMonth,true); ?></select></label>
   <button class="action" type="submit"><?= workspaceIcon('filter') ?>แสดงผล</button>
  </form>
  <div class="actions">
   <a class="action" href="feedback.php"><?= workspaceIcon('ready') ?>เปิดแบบประเมิน</a>
   <?php if (!$sampleMode): ?><a class="action primary" href="<?= esc($exportLink) ?>"><?= workspaceIcon('download') ?>ดาวน์โหลด CSV</a><?php endif; ?>
   <button type="button" class="action" onclick="window.print()"><?= workspaceIcon('print') ?>พิมพ์</button>
  </div></div>
  <p class="notice"><?= esc($identity['role']==='provincial_report'?($provinces[0]['name']??'จังหวัดที่รับผิดชอบ'):($region?zoneLabel($region):'ทุกเขตและผู้ไม่ระบุจังหวัด')) ?> · <?= esc(EVALUATION_AUDIENCES[$evaluationAudience]??'ทุกกลุ่ม') ?> · <?= esc($evaluationMonth ? workspaceThaiMonth($evaluationMonth) : 'ทุกเดือน') ?> · <?= esc($selectedVersion) ?></p>
  <div class="admin-kpis hosted-kpis">
    <section class="card"><span>แบบประเมินที่ส่งแล้ว</span><strong><?= number_format($total) ?></strong></section>
    <section class="card"><span>ความพึงพอใจเฉลี่ย (เต็ม 5)</span><strong><?= esc($average) ?></strong></section>
    <section class="card"><span>ส่วนเบี่ยงเบนมาตรฐาน (S.D.)</span><strong><?= isset($evaluation['overall']['sd']) ? number_format((float)$evaluation['overall']['sd'],2) : '—' ?></strong></section>
    <section class="card"><span>ให้คะแนนรวมระดับมากขึ้นไป</span><strong><?= esc($satisfied) ?></strong></section>
  </div>
  <p class="notice">สำหรับตัวชี้วัดความพึงพอใจของงานวิจัย เลือกกลุ่ม “เจ้าหน้าที่” เพื่อดูผลตามประเด็นในร่างโครงการ</p>
  <p class="notice">นับจำนวนแบบประเมินที่ส่ง ผู้ตอบเลือกกลุ่มและจังหวัดเอง คะแนนรายด้านเฉลี่ยจากคะแนนของแต่ละแบบประเมิน โดยเว้นข้อที่เลือกว่ายังไม่ได้ใช้ หากมีคะแนนน้อยกว่า 2 แบบ จะเว้นค่า S.D.</p>
  <section class="card admin-report"><h2>คะแนนแยกตามด้าน</h2>
   <div class="tablewrap"><table class="admin-table"><thead><tr><th>ด้าน</th><th>แบบประเมินที่ให้คะแนน</th><th>จำนวนข้อที่ให้คะแนน</th><th>เฉลี่ย / 5</th><th>S.D.</th></tr></thead><tbody>
   <?php foreach(EVALUATION_DOMAINS as $key=>$label): $item=$evaluation['domains'][$key]??null; ?>
    <tr><th scope="row"><?= esc($label) ?></th><td><?= (int)($item['respondents']??0) ?></td><td><?= (int)($item['n']??0) ?></td><td><?= $item?number_format((float)$item['average'],2):'—' ?></td><td><?= isset($item['sd']) ? number_format((float)$item['sd'],2) : '—' ?></td></tr>
   <?php endforeach; ?>
   </tbody></table></div>
  </section>
  <section class="card admin-report evaluation-topics"><h2>เรื่องที่อยากให้ปรับ</h2><p>ผู้ตอบเลือกได้มากกว่าหนึ่งเรื่อง</p>
   <div class="tablewrap"><table class="admin-table"><thead><tr><th>เรื่อง</th><th>จำนวนแบบประเมินที่เลือก</th></tr></thead><tbody>
   <?php foreach(EVALUATION_TOPICS as $key=>$label): ?><tr><th scope="row"><?= esc($label) ?></th><td><?= (int)($evaluation['topics'][$key]??0) ?></td></tr><?php endforeach; ?>
   </tbody></table></div>
  </section>
  <section class="card admin-report"><h2>ข้อเสนอแนะเพิ่มเติม</h2>
   <?php $comments = $sampleMode ? ['อยากให้หน้าผลประเมินสรุปขั้นตอนถัดไปให้เห็นทันที','อยากให้ค้นหาบทความตามเรื่องที่สนใจได้ง่ายขึ้น'] : ($evaluation['comments'] ?? []); ?>
   <?php if ($comments): ?><p>แสดงสูงสุด 30 ข้อความล่าสุดตามตัวกรอง</p><ul>
    <?php foreach ($comments as $comment): ?><li><?= nl2br(esc($comment)) ?></li><?php endforeach; ?>
   </ul><?php else: ?><p>ยังไม่มีข้อเสนอแนะเพิ่มเติมในตัวกรองนี้</p><?php endif; ?>
  </section>
  <?php elseif ($view === 'access'): ?>
  <p>บัญชีผู้ดูแลหลักจัดการบัญชีรายงานได้ การปิดบัญชีจะกันการเข้าถึงตั้งแต่คำขอครั้งถัดไป</p>
  <?php if (isset($accessNotice['error'])): ?><p class="status high" role="alert"><?= esc($accessNotice['error']) ?></p>
  <?php elseif (!empty($accessNotice['password'])): ?>
   <div class="status" role="status"><div><strong>สร้างบัญชี <?= esc($accessNotice['username']) ?> แล้ว</strong><p>รหัสผ่านครั้งแรก <code><?= esc($accessNotice['password']) ?></code></p><p>รหัสนี้แสดงครั้งเดียว ให้มอบแก่เจ้าของบัญชีผ่านช่องทางที่เหมาะสม</p></div></div>
  <?php elseif ($accessNotice): ?><p class="status" role="status">บันทึกสิทธิ์ของ <?= esc($accessNotice['username']) ?> แล้ว</p><?php endif; ?>
  <?php if (!$sampleMode): ?><details class="card admin-report">
   <summary>สร้างบัญชีเจ้าหน้าที่</summary>
   <form method="post" class="admin-access-form">
    <input type="hidden" name="csrf" value="<?= esc($token) ?>">
    <label>ชื่อผู้ใช้<input name="username" required maxlength="40" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,39}" autocomplete="off"></label>
    <?php accessFields([], $provinces); ?>
    <button class="action primary" name="action" value="create"><?= workspaceIcon('users') ?>สร้างบัญชีและรหัสผ่าน</button>
   </form>
  </details><?php else: ?><p>บัญชีตัวอย่างสำหรับดูรูปแบบการจัดสิทธิ์</p><?php endif; ?>
  <div class="admin-accounts hosted-access-accounts">
  <?php foreach ($accessAccounts as $account): ?>
    <?php if ($account['role'] === 'account_admin'): ?>
     <section class="card admin-account"><h2><?= esc($account['username']) ?></h2><p>ผู้ดูแลระบบหลัก</p></section>
    <?php else: ?>
     <details class="card admin-account hosted-access-item"><summary>
       <strong><?= esc($account['username']) ?></strong>
       <span><?= esc(ANEMIA_REPORT_ROLES[$account['role']] ?? $account['role']) ?><?= $account['region'] ? ' · ' . esc(zoneLabel((int)$account['region'])) : '' ?></span>
       <small><?= (int)$account['enabled'] ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?></small>
     </summary>
     <?php if (!$sampleMode): ?><form method="post" class="admin-access-form">
      <input type="hidden" name="csrf" value="<?= esc($token) ?>">
      <input type="hidden" name="username" value="<?= esc($account['username']) ?>">
      <?php accessFields($account, $provinces); ?>
      <button class="action primary" name="action" value="update"><?= workspaceIcon('save') ?>บันทึกสิทธิ์</button>
     </form><?php else: ?><p>สิทธิ์เข้าดูรายงานของเขตที่กำหนด</p><?php endif; ?></details>
    <?php endif; ?>
  <?php endforeach; ?>
  </div>
  <?php elseif ($view === 'audit'): ?>
  <section class="card admin-report"><h2>การเปลี่ยนสิทธิ์ล่าสุด</h2><p>แสดงสูงสุด 100 รายการ</p>
   <?php if (!$auditRows): ?><p>ยังไม่มีประวัติการเปลี่ยนสิทธิ์</p><?php else: ?>
   <div class="tablewrap"><table class="admin-table"><thead><tr><th>วันเวลา</th><th>ผู้ดำเนินการ</th><th>บัญชี</th><th>รายการ</th><th>บทบาทและพื้นที่</th><th>สถานะ</th></tr></thead><tbody>
   <?php foreach ($auditRows as $row): ?><tr>
    <td><?= esc($row['changed_at']) ?></td><td><?= esc($row['actor']) ?></td><th scope="row"><?= esc($row['target_username']) ?></th>
    <td><?= esc(match($row['action_name']) {'create'=>'สร้างบัญชี','update'=>'แก้ไขสิทธิ์','seed'=>'เตรียมบัญชี',default=>$row['action_name']}) ?></td>
    <td><?= esc(ANEMIA_REPORT_ROLES[$row['role_name']] ?? $row['role_name']) ?><?= $row['region'] ? ' · '.esc(zoneLabel((int)$row['region'])) : ($row['province_code'] ? ' · จังหวัดรหัส '.esc($row['province_code']) : '') ?></td>
    <td><?= (int)$row['enabled'] ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?></td>
   </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  </section>
  <?php elseif ($view === 'guide'): ?>
  <section class="card admin-report">
   <h2>การใช้งานสำหรับเจ้าหน้าที่</h2>
   <ol>
    <li>เปิด <a href="dashboard.php?view=overview">ภาพรวม</a> เพื่อดูทะเบียนพื้นที่และผลประเมินการใช้งานของเดือนปัจจุบัน</li>
    <li>เปิด <a href="dashboard.php?view=reports">รายงานจังหวัด</a> เพื่อดูพื้นที่ตามสิทธิ์ ดาวน์โหลด CSV หรือพิมพ์รายงาน</li>
    <li>เปิด <a href="dashboard.php?view=evaluation">ผลประเมินโครงการ</a> เพื่อกรองตามเดือนและกลุ่มผู้ตอบ</li>
    <?php if ($isAdmin): ?><li>เปิด <a href="dashboard.php?view=access">จัดการสิทธิ์</a> เพื่อสร้างบัญชี กำหนดพื้นที่ และเปิดหรือปิดการใช้งาน</li><?php endif; ?>
   </ol>
   <p>ประชาชนเข้าทำ <a href="feedback.php">แบบประเมินความพึงพอใจ</a> ได้โดยไม่ต้องเข้าสู่ระบบ</p>
  </section>
  <?php elseif ($view === 'about'): ?>
  <section class="card admin-report"><?php workspaceInfo($view, $localRelease); ?></section>
  <?php elseif ($view === 'scan-request'): ?>
  <div class="request-scanner-layout">
   <?php if ($scannedRequest):
     $demoScan=str_starts_with($scannedRequest['receipt'],'DE-');
     $states=ANEMIA_REQUEST_STATES;
   ?>
   <section class="card admin-report request-scan-result" aria-labelledby="scan-result-heading">
    <div class="sample-section-heading"><span class="iconbox"><?= workspaceIcon('ready') ?></span><div><h2 id="scan-result-heading">พบคำขอ</h2><p><?= $demoScan?'คิวทดสอบ':'คิวจริง' ?> · <?= esc($scannedRequest['receipt']) ?></p></div></div>
    <div class="request-scan-state" data-state="<?= esc($scannedRequest['state']) ?>"><span><?= workspaceIcon('ready') ?><strong>สถานะล่าสุด · <?= esc($states[$scannedRequest['state']] ?? 'ยังไม่ทราบสถานะ') ?></strong></span><small>ตรวจเมื่อ <?= esc(date('d/m/Y H:i:s')) ?></small></div>
    <dl>
     <div><dt>หน่วยบริการ</dt><dd><?= esc($scannedRequest['facility']) ?></dd></div>
     <div><dt>ความประสงค์</dt><dd><?= $scannedRequest['request_kind']==='appointment'?'ขอนัดเข้าตรวจ':'ขอให้ติดต่อกลับ' ?></dd></div>
     <div><dt>ชื่อที่ใช้ติดต่อ</dt><dd><?= esc($scannedRequest['contact_name']) ?></dd></div>
     <div><dt>เบอร์โทร</dt><dd><a href="tel:<?= esc($scannedRequest['phone']) ?>"><?= esc($scannedRequest['phone']) ?></a></dd></div>
     <div><dt>วันที่สะดวก</dt><dd><?= esc($scannedRequest['preferred_date'] ?? 'ไม่ระบุ') ?></dd></div>
     <div><dt>เวลาที่ส่งคำขอ</dt><dd><?= esc($scannedRequest['submitted_at']) ?></dd></div>
     <?php if ($scannedRequest['appointment_at']): ?><div><dt>วันเวลานัด</dt><dd><?= esc($scannedRequest['appointment_at']) ?></dd></div><?php endif; ?>
     <?php if ($scannedRequest['checked_in_at']): ?><div><dt>เวลาเช็กอิน</dt><dd><?= esc($scannedRequest['checked_in_at']) ?></dd></div><?php endif; ?>
    </dl>
    <div class="actions">
     <?php if (in_array($scannedRequest['state'],ANEMIA_REQUEST_ACTIONS['attended'][1],true)): ?>
      <form method="post">
       <input type="hidden" name="csrf" value="<?= esc($token) ?>"><input type="hidden" name="receipt" value="<?= esc($scannedRequest['receipt']) ?>">
       <button class="action primary" name="scan_action" value="check_in"><?= workspaceIcon('ready') ?>ยืนยันเช็กอิน</button>
      </form>
     <?php endif; ?>
     <a class="action" href="dashboard.php?view=contact-queue<?= $demoScan?'&amp;demo=1':'' ?>"><?= workspaceIcon('next') ?>เปิดคิวคำขอ</a>
    </div>
   </section>
   <?php endif; ?>
   <section class="card admin-report">
    <div class="sample-section-heading"><span class="iconbox"><?= workspaceIcon('scan') ?></span><div><h2>ตรวจใบรับคำขอ</h2><p>สแกน QR จากหน้าจอหรือจากใบที่บันทึกไว้</p></div></div>
    <?php if ($scanMessage !== ''): ?><p class="status" role="alert"><?= esc($scanMessage) ?></p><?php endif; ?>
    <form id="request-scanner-form" method="post" class="request-scanner-form">
     <input type="hidden" name="csrf" value="<?= esc($token) ?>">
     <label>เลขคำขอหรือลิงก์จากใบรับคำขอ<input name="receipt" type="text" required maxlength="512" autocomplete="off" placeholder="เช่น AN-…"></label>
     <button type="submit" class="action primary"><?= workspaceIcon('ready') ?>ตรวจคำขอ</button>
    </form>
    <div class="actions request-scan-controls">
     <button type="button" id="request-scan-start" class="action"><?= workspaceIcon('scan') ?>เปิดกล้อง</button>
     <button type="button" id="request-scan-stop" class="action" hidden><?= workspaceIcon('close') ?>ปิดกล้อง</button>

    </div>
    <label id="request-scan-drop" class="request-scan-drop">
     <span class="iconbox"><?= workspaceIcon('scan') ?></span><span><strong>ใส่ภาพใบรับคำขอ</strong><small>แตะเลือกไฟล์ ลากภาพมาวาง หรือวางภาพที่คัดลอกไว้</small></span>
     <input type="file" id="request-scan-file" accept="image/*">
    </label>
    <p id="request-scan-message" role="status"></p>
    <video id="request-scan-video" muted playsinline hidden aria-label="ภาพจากกล้องสำหรับสแกน QR"></video>
   </section>
  </div>
  <?php elseif ($view === 'contact-queue'): ?>
  <section class="card admin-report">
  <div class="actions"><a class="action <?= !$demoQueue?'primary':'' ?>" href="dashboard.php?view=contact-queue"><?= workspaceIcon('phone') ?>คิวจริง</a>
   <a class="action <?= $demoQueue?'primary':'' ?>" href="dashboard.php?view=contact-queue&amp;demo=1"><?= workspaceIcon('chart') ?>คิวทดสอบ</a>
   <a class="action" href="dashboard.php?view=scan-request"><?= workspaceIcon('scan') ?>สแกน QR คำขอ</a>
   <a class="action" href="index.html?test-otp=1"><?= workspaceIcon('next') ?>เริ่มทดสอบ OTP</a></div>
  <h2><?= $demoQueue?'คำขอที่บันทึกในโหมดทดสอบ':'คำขอที่ยืนยันเบอร์แล้ว' ?></h2>
  <a class="action" href="dashboard.php?view=contact-queue<?= $demoQueue?'&amp;demo=1':'' ?><?= $attentionOnly?'':'&amp;attention=1' ?>"><?= workspaceIcon('history') ?><?= $attentionOnly?'ดูคำขอทั้งหมด':'งานที่ต้องติดตาม' ?></a>
  <?php $pendingInQueue = $demoQueue ? $pendingDemoCounts : $pendingContactCounts; $counterScope = $demoQueue ? 'demo' : 'live'; ?>
  <div class="contact-pending-summary">
   <span><?= workspaceIcon('calendar') ?>ขอนัดรอประสาน <strong data-pending-count="<?= $counterScope ?>.appointment"><?= $pendingInQueue['appointment'] ?></strong></span>
   <span><?= workspaceIcon('phone') ?>ขอติดต่อกลับรอประสาน <strong data-pending-count="<?= $counterScope ?>.callback"><?= $pendingInQueue['callback'] ?></strong></span>
  </div>
  <p><?= $demoQueue?'แสดงข้อมูลติดต่อที่กรอกในโหมดทดสอบ คิวนี้แยกจากคิวจริงและไม่มีการส่ง SMS':'แสดง 100 คำขอล่าสุด ผู้ดูแลประสานกับหน่วยบริการที่ผู้ขอเลือก' ?></p>
  <?php if (!$contactQueue): ?><p>ยังไม่มีคำขอ</p><?php else: ?>
  <div class="tablewrap"><table class="admin-table contact-queue-table"><thead><tr><th>เลขคำขอ / เวลาส่ง</th><th>หน่วยบริการ / ความประสงค์</th><th>ข้อมูลติดต่อ</th><th>สถานะ</th></tr></thead><tbody>
  <?php foreach ($contactQueue as $item): ?><tr>
   <th scope="row"><?= esc($item['receipt']) ?><br><small><?= esc($item['submitted_at']) ?></small></th>
   <td><?= esc($item['facility']) ?><br><small><?= $item['request_kind']==='appointment'?'ขอนัดเข้าตรวจ':'ให้ติดต่อกลับ' ?><?= $item['preferred_date']?' · '.esc($item['preferred_date']):'' ?></small></td>
   <td><?= esc($item['contact_name']) ?><br><a href="tel:<?= esc($item['phone']) ?>"><?= esc($item['phone']) ?></a></td>
   <td><button type="button" class="action queue-timeline-trigger" data-queue-timeline="<?= esc($item['receipt']) ?>" aria-haspopup="dialog"><?= workspaceIcon('history') ?><span><?= $item['overdue']?'เลยวันนัด · รอตรวจสอบ':esc(ANEMIA_REQUEST_STATES[$item['state']] ?? 'ดูสถานะ') ?></span><?= workspaceIcon('next') ?></button></td>
  </tr><?php endforeach; ?></tbody></table></div>
  <dialog class="signal-dialog workspace-dialog queue-timeline-dialog" id="queue-timeline" aria-labelledby="queue-timeline-title">
   <form method="dialog"><button class="action" aria-label="ปิดไทม์ไลน์"><?= workspaceIcon('close') ?>ปิด</button></form>
   <h2 id="queue-timeline-title">ไทม์ไลน์คำขอ</h2>
   <p data-timeline-receipt></p>
   <ol class="queue-timeline-events"></ol>
   <p data-timeline-message role="status"></p>
   <form method="post" id="queue-workflow-form" class="contact-queue-state" hidden>
    <input type="hidden" name="csrf" value="<?= esc($token) ?>"><input type="hidden" name="receipt"><input type="hidden" name="previous">
    <select name="state" aria-label="เปลี่ยนสถานะคำขอ">
     <option value="">เลือกงานที่จะทำ</option>
     <?php foreach (ANEMIA_REQUEST_STATES as $key=>$label): ?>
      <option value="<?= $key ?>"><?= esc($label) ?></option>
     <?php endforeach; ?>
    </select><button type="submit" class="action"><?= workspaceIcon('save') ?>บันทึก</button>
   </form>
   <label data-workflow-date hidden>วันและเวลา (เวลาไทย)<input form="queue-workflow-form" name="when" type="datetime-local"></label>
  </dialog>
  <script defer src="queue-timeline.js?v=2"></script>
  <?php endif; ?>
  </section>
  <?php elseif ($view === 'sample-settings'): ?>
  <div class="sample-settings-layout">
   <section class="card admin-report sample-setting-card">
    <div class="sample-section-heading">
     <span class="iconbox"><?= workspaceIcon('power') ?></span>
     <div><h2>ข้อมูลจำลองบนโฮสต์นี้</h2><p>ควบคุมการแสดงข้อมูลจำลองทั้งในหลังบ้านและ Open Data</p></div>
     <span class="sample-state<?= $sampleEnabled ? ' is-enabled' : '' ?>"><?= workspaceIcon($sampleEnabled ? 'ready' : 'power') ?><?= $sampleEnabled ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?></span>
    </div>
    <?php if ($sampleSettingSaved): ?><p class="sample-saved" role="status"><?= workspaceIcon('ready') ?><?= match ($sampleSettingSaved) {'generate'=>'สร้างข้อมูลและเปิดโหมดจำลองแล้ว','clear'=>'ล้างข้อมูลจำลองและปิดโหมดจำลองแล้ว',default=>'บันทึกการตั้งค่าแล้ว'} ?></p><?php endif; ?>
    <p>รายงานจำลอง <strong><?= number_format($demoReportCount) ?></strong> ชุด · คำขอในคิวทดสอบ <strong><?= number_format($demoQueueCount) ?></strong> รายการ</p>
    <div class="demo-data-actions">
    <form method="post">
     <input type="hidden" name="csrf" value="<?= esc($token) ?>">
     <button class="action primary" name="demo_action" value="generate" type="submit"><?= workspaceIcon('chart') ?>สร้างข้อมูลจำลอง</button>
    </form>
    <details>
     <summary class="action"><?= workspaceIcon('history') ?>เคลียร์ข้อมูลจำลอง</summary>
     <form method="post" class="demo-clear-form">
      <input type="hidden" name="csrf" value="<?= esc($token) ?>">
      <label><input type="checkbox" name="confirm_clear" value="1" required> ยืนยันล้างรายงานและคิวทดสอบทั้งหมด ใบรับคำขอทดสอบเดิมจะใช้ตรวจสถานะไม่ได้</label>
      <button class="action" name="demo_action" value="clear" type="submit"><?= workspaceIcon('close') ?>ยืนยันเคลียร์ข้อมูลจำลอง</button>
     </form>
    </details>
    </div>
    <form method="post" class="sample-setting-form">
     <input type="hidden" name="csrf" value="<?= esc($token) ?>">
     <label>สถานะข้อมูลจำลอง<select name="sample_enabled"><option value="1" <?= $sampleEnabled?'selected':'' ?>>เปิดให้ดูข้อมูลจำลอง</option><option value="0" <?= !$sampleEnabled?'selected':'' ?>>ปิดข้อมูลจำลอง</option></select></label>
     <button class="action primary" type="submit"><?= workspaceIcon('save') ?>บันทึก</button>
     <label class="sample-all-regions"><input type="checkbox" name="sample_all_regions" value="1" <?= $sampleAllRegions ? 'checked' : '' ?>>
      <span><strong>จำลองเปิดใช้งานครบ 13 เขตสุขภาพ</strong><small>แสดงสถานะเปิดครบ 77 จังหวัดในทะเบียนตัวอย่าง</small></span>
     </label>
    </form>
    <?php if (!empty($sampleSetting['changed_at'])): ?><p class="sample-changed"><?= workspaceIcon('history') ?>แก้ไขล่าสุด <?= esc($sampleSetting['changed_at']) ?> โดย <?= esc($sampleSetting['changed_by'] ?? '') ?></p><?php endif; ?>
   </section>
   <?php if ($sampleEnabled): ?>
   <section class="card admin-report sample-setting-card">
    <div class="sample-section-heading"><span class="iconbox"><?= workspaceIcon('chart') ?></span><div><h2>เปิดดูข้อมูลตัวอย่าง</h2><p>เลือกหน้าที่ต้องการดูในโหมดข้อมูลจำลอง</p></div></div>
    <?php if ($sampleAllRegions): ?><p class="status"><?= workspaceIcon('map') ?>ข้อมูลจำลองเปิดครบ 13 เขตสุขภาพ · 77 จังหวัด</p><?php endif; ?>
    <div class="sample-shortcuts">
     <a href="dashboard.php?view=contact-queue&amp;demo=1"><span class="iconbox"><?= workspaceIcon('phone') ?></span><strong>คิวทดสอบ</strong><span class="sample-shortcut-arrow"><?= workspaceIcon('next') ?></span></a>
     <?php foreach ($sampleViews as $key): ?>
      <a href="dashboard.php?view=<?= esc($key) ?>&amp;preview=sample"><span class="iconbox"><?= workspaceIcon($tabIcons[$key]) ?></span><strong><?= esc($tabs[$key]) ?></strong><span class="sample-shortcut-arrow"><?= workspaceIcon('next') ?></span></a>
     <?php endforeach; ?>
    </div>
   </section>
   <?php endif; ?>
  </div>
  <?php elseif ($view === 'release'): ?>
  <section class="card admin-report">
   <h2>เวอร์ชันชุดติดตั้ง</h2>
   <p>โฮสต์นี้ใช้รุ่น <?= esc($localRelease['version'] ?? 'อ่านข้อมูลรุ่นไม่ได้') ?>
    <?php if (isset($localRelease['build'])): ?> · build <?= esc($localRelease['build']) ?><?php endif; ?>
    <?php if ($centralRelease): ?> · รุ่นส่วนกลาง <?= esc($centralRelease['version']) ?><?php if (isset($centralRelease['build'])): ?> · build <?= esc($centralRelease['build']) ?><?php endif; ?><?php endif; ?></p>
   <?php if (!$releaseSource): ?><p>ยังไม่ได้ตั้งค่าแหล่งตรวจรุ่นส่วนกลาง</p>
   <?php else: ?><a class="action" href="dashboard.php?view=release&amp;check=1"><?= workspaceIcon('history') ?>ตรวจรุ่นส่วนกลาง</a><?php endif; ?>
   <?php if ($releaseChecked && !$centralRelease): ?><p role="status">อ่านข้อมูลรุ่นส่วนกลางไม่ได้ กรุณาลองอีกครั้ง</p><?php endif; ?>
   <div class="tablewrap"><table class="admin-table"><thead><tr><th>ส่วนของระบบ</th><th>รุ่นของโฮสต์นี้</th><th>รุ่นส่วนกลาง</th><th>สถานะ</th></tr></thead><tbody>
   <?php foreach (ANEMIA_RELEASE_COMPONENTS as $key=>$label):
     $installed=$localRelease['components'][$key] ?? null;
     $available=$centralRelease['components'][$key] ?? null;
     $status=$installed && $available ? match(version_compare($installed,$available)) {-1=>'มีรุ่นใหม่',0=>'ตรงกับส่วนกลาง',1=>'โฮสต์นี้ใหม่กว่า'} : 'รอข้อมูลเทียบรุ่น';
   ?>
    <tr><th scope="row"><?= esc($label) ?></th><td><?= esc($installed ?? '—') ?></td><td><?= esc($available ?? '—') ?></td><td><?= esc($status) ?></td></tr>
   <?php endforeach; ?>
   </tbody></table></div>
   <p>หน้านี้ใช้ตรวจรุ่น ผู้ดูแลติดตั้งแต่ละรุ่นผ่านชุดไฟล์สำหรับโฮสต์</p>
  </section>
  <?php elseif ($view === 'team'): ?>
  <section class="card admin-report"><?php workspaceInfo($view); ?></section>
  <?php elseif ($view === 'assessment'): ?>
  <section class="card admin-report"><h2>รุ่นแบบประเมิน</h2><p>ชุดอัปโหลดนี้ยังปิดการรับคำตอบสุขภาพจริง</p>
  <div class="tablewrap"><table class="admin-table"><thead><tr><th>รุ่น</th><th>สถานะ</th></tr></thead><tbody>
  <?php foreach ($forms as $form): ?><tr><td><?= esc($form['version_key']) ?></td><td><?= $form['state'] === 'draft' ? 'ฉบับร่าง' : esc($form['state']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div><p><a class="action" href="index.html"><?= workspaceIcon('ready') ?>เปิดหน้าประเมิน</a></p></section>
  <?php endif; ?>
 </div>
</div>
</section>
</main>
<footer class="site-footer no-print">
  <div class="footer-inner">
    <div class="footer-identity">
      <span class="mark"><?= workspaceIcon('province') ?></span>
      <div><strong>ศูนย์อนามัยที่ ๑๐ จังหวัดอุบลราชธานี</strong>
        <p>กรมอนามัย กระทรวงสาธารณสุข</p></div>
    </div>
    <address>
      <p>45 หมู่ 4 ถนนสถลมาร์ค ตำบลธาตุ อำเภอวารินชำราบ จังหวัดอุบลราชธานี 34190</p>
      <div class="footer-links">
        <a href="tel:+6645288586"><span><?= workspaceIcon('phone') ?></span>045-288-586 ถึง 588</a>
        <a href="https://hpc10.anamai.moph.go.th/"><span><?= workspaceIcon('next') ?></span>เว็บไซต์ศูนย์อนามัยที่ ๑๐</a>
      </div>
    </address>
  </div>
</footer>
<?php foreach (['about','team'] as $infoPage): ?>
<dialog id="workspace-<?= esc($infoPage) ?>" class="workspace-dialog no-print" aria-labelledby="workspace-title-<?= esc($infoPage) ?>">
 <header class="workspace-dialog-heading">
  <h2 id="workspace-title-<?= esc($infoPage) ?>"><?= esc($tabs[$infoPage]) ?></h2>
  <form method="dialog"><button class="action" autofocus><?= workspaceIcon('close') ?>ปิด</button></form>
 </header>
 <?php workspaceInfo($infoPage, $localRelease); ?>
</dialog>
<?php endforeach; ?>
<script>
document.querySelectorAll('[data-workspace-dialog]').forEach(link => {
 const dialog = document.getElementById('workspace-' + link.dataset.workspaceDialog);
 if (typeof dialog?.showModal !== 'function') return;
 link.addEventListener('click', event => {
  if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
  event.preventDefault();
  dialog.showModal();
 });
 dialog.addEventListener('close', () => link.focus());
});
</script>
<div id="report-loading" class="report-loading-overlay no-print" hidden>
 <div class="report-loading-card" role="status"><span class="report-spinner" aria-hidden="true"></span>กำลังโหลดรายงาน</div>
</div>
<script>
(() => {
 const overlay = document.getElementById('report-loading');
 const reports = new Set(['overview','reports','activity','evaluation','sample']);
 let timer;
 const start = () => {
  if (timer !== undefined) return;
  timer = setTimeout(() => { overlay.hidden = false; },140);
 };
 addEventListener('pageshow', () => { clearTimeout(timer); timer = undefined; overlay.hidden = true; });
 document.addEventListener('submit', event => {
  if (!event.defaultPrevented && event.target.matches('form.hosted-filter-row') &&
      reports.has(event.target.elements.view?.value)) start();
 });
 document.addEventListener('click', event => {
  const link = event.target.closest('a[href]');
  if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey || link.target || link.hasAttribute('download')) return;
  const url = new URL(link.href);
  if (url.origin !== location.origin || url.pathname !== location.pathname || url.searchParams.has('download')) return;
  if (reports.has(url.searchParams.get('view'))) start();
 });
})();
</script>
<?php if ($isAdmin): ?>
<script>
(() => {
 let running=false;
 async function refreshPendingContacts(){
  if(running||document.hidden)return;
  running=true;
  try{
   const response=await fetch('dashboard.php?view=contact-queue&counts=1',{cache:'no-store'});
   if(!response.ok||!response.headers.get('Content-Type')?.includes('application/json'))return;
   const counts=await response.json();
   document.querySelectorAll('[data-pending-count]').forEach(node=>{
    const [scope,kind]=node.dataset.pendingCount.split('.');
    const value=counts[scope]?.[kind];
    if(!Number.isInteger(value)||value<0)return;
    node.textContent=value.toLocaleString('th-TH');
    if(node.classList.contains('contact-pending-badge')){
     node.textContent=value>99?'99+':node.textContent;
     node.hidden=value===0;
     node.setAttribute('aria-label','คำขอนัดรอประสาน '+value+' รายการ');
    }
   });
  }catch{}finally{running=false;}
 }
 setInterval(refreshPendingContacts,30000);
 document.addEventListener('visibilitychange',refreshPendingContacts);
 window.addEventListener('pageshow',event=>{if(event.persisted)refreshPendingContacts();});
})();
</script>
<?php endif; ?>
</body></html>
