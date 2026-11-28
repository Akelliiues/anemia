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
    $next = match ($_GET['view'] ?? '') {
        'scan-request'=>($_GET['app'] ?? '')==='unit' ? (($_GET['launch']??'')==='scanner'?'?next=unit-launch':'?next=unit-scan') : '?next=scan',
        'scanner-install'=>'?next=unit-install','mobile'=>'?next=mobile',
        'contact-queue'=>($_GET['app']??'')==='staff'?'?next=mobile':'',default=>'',
    };
    header('Location: login'.$next, true, 303);
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
    return $region === 13 ? 'เขตสุขภาพที่ 13 กรุงเทพมหานคร' : 'เขตสุขภาพที่ ' . $region;
}
$view = $_GET['view'] ?? 'overview';
if (!in_array($view, ['overview', 'reports', 'assessment', 'access', 'evaluation', 'audit', 'sample', 'team', 'guide', 'about', 'release', 'activity', 'sample-settings', 'services', 'contact-queue', 'scan-request', 'scanner-install', 'mobile', 'network', 'members', 'programme-report', 'schools'], true)) $view = 'overview';
$unitScanner = $view==='scan-request' && ($_GET['app'] ?? '')==='unit';
$scannerApp = $unitScanner || $view==='scanner-install';
$mobileQueue = $view==='contact-queue' && ($_GET['app']??'')==='staff';
$staffAppView = $scannerApp || $mobileQueue || $view==='mobile';
$staffWorkDock = $mobileQueue || $unitScanner;
date_default_timezone_set('Asia/Bangkok');
$callbackTimes = ['08:30','09:30','10:30','11:30','13:00','14:00','15:00','16:00'];
$callbackClosedDays = preg_split('/[\s,]+/',trim(getenv('ANEMIA_CONTACT_CLOSED_DATES') ?: ''),-1,PREG_SPLIT_NO_EMPTY);
$evaluationAudience = $_GET['audience'] ?? '';
$evaluationMonth = $_GET['month'] ?? date('Y-m');
if (in_array($view,['reports','activity','evaluation'],true) && array_key_exists('month_number',$_GET)) {
    $monthYear=$_GET['month_year']??null;
    $monthNumber=$_GET['month_number'];
    $evaluationMonth=is_string($monthYear) && is_string($monthNumber)
        ? $monthYear.'-'.$monthNumber : '';
}
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
    if ($identity['role']==='school_teacher') {
        header('Location: school-workspace.php',true,303);
        exit;
    }
    $staff = $identity['username'];
    $isAdmin = $identity['role'] === 'account_admin';
    $canChooseRegion = $isAdmin || $identity['role'] === 'central_report';
    $roleLabel = $isAdmin ? 'ผู้ดูแลระบบ' : ANEMIA_REPORT_ROLES[$identity['role']];
    require __DIR__.'/_private/network.php';
    $grantQuery=$db->prepare('SELECT scope_kind,scope_code FROM anemia_network_grant WHERE username=?');
    $grantQuery->execute([$staff]);
    $grant=$grantQuery->fetch(PDO::FETCH_ASSOC) ?: null;
    $canQueue=$isAdmin || ($grant['scope_kind']??'')==='facility';
    $canNetwork=$isAdmin || in_array($grant['scope_kind']??'',['province','district'],true);
    [$queueScope,$queueParams]=anemiaQueueNetworkScope($isAdmin,$grant);
    if ((in_array($view,['access','assessment','audit','sample','release','sample-settings','services','members','programme-report'],true) && !$isAdmin) ||
        (in_array($view,['contact-queue','scan-request','scanner-install'],true) && !$canQueue) || ($view==='network' && !$canNetwork)) {
        http_response_code(403); exit('บัญชีนี้ไม่มีสิทธิ์เข้าหน้านี้');
    }
    $scanUnit = $isAdmin ? 'ตามสิทธิ์ผู้ดูแลระบบ' : '';
    if (!$isAdmin && $canQueue && $staffAppView) {
        $unitQuery=$db->prepare('SELECT name FROM anemia_network_facility WHERE code=?');
        $unitQuery->execute([$grant['scope_code']]);
        $scanUnit=$unitQuery->fetchColumn() ?: 'หน่วยบริการตามสิทธิ์บัญชี';
    }
    if ($view==='mobile' && $canQueue) {
        header('Location: dashboard.php?view=contact-queue&app=staff',true,303);
        exit;
    }
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
    $memberNotice='';
    if ($view==='members') {
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            if (!is_string($_POST['csrf']??null) || !hash_equals($token,$_POST['csrf'])) { http_response_code(403); exit('คำขอหมดอายุ'); }
            require __DIR__.'/_private/members.php';
            $id=$_POST['member']??'';
            $action=$_POST['member_action']??'';
            $reason=$_POST['reason']??'';
            if (!is_string($id)||!is_string($action)||!is_string($reason)) { http_response_code(400); exit('ข้อมูลไม่ถูกต้อง'); }
            try {
                if (in_array($action,['demo-create','demo-clear'],true)) {
                    $changed=anemiaSetDemoMemberNetwork($db,$id,$staff,$action==='demo-create');
                    $_SESSION['member_notice']=$changed?($action==='demo-create'?'สร้างลูกทีมจำลองแล้ว':'ล้างลูกทีมจำลองแล้ว'):'ข้อมูลจำลองไม่มีการเปลี่ยนแปลง';
                } elseif ($action==='rename') {
                    $username=$_POST['new_username']??null;
                    if (!is_string($username)) throw new DomainException('กรุณาระบุชื่อบัญชีใหม่');
                    $changed=anemiaRenameMember($db,$id,$username,$reason,$staff);
                } else {
                    $changed=anemiaSetMemberStatus($db,$id,$action,$reason,$staff);
                }
                if (!in_array($action,['demo-create','demo-clear'],true)) $_SESSION['member_notice']=$changed?'บันทึกการเปลี่ยนแปลงแล้ว':'ข้อมูลไม่มีการเปลี่ยนแปลง';
            } catch (DomainException $error) {
                $_SESSION['member_notice']=$error->getMessage();
            }
            $return=$_GET;
            $return['view']='members';
            header('Location: dashboard.php?'.http_build_query($return),true,303); exit;
        }
        $memberNotice=$_SESSION['member_notice']??'';
        unset($_SESSION['member_notice']);
    }
    $networkNotice='';
    if ($view==='network') {
        if ($isAdmin && ($_GET['directory']??'')==='1') {
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="anemia-directory-index.json"');
            $directory=anemiaFacilityDirectory();
            echo json_encode(array_map(static fn($row,$index)=>['index'=>$index,'name'=>$row[0],'address'=>$row[1]],$directory,array_keys($directory)),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['network_action'])) {
            if (!is_string($_POST['csrf']??null) || !hash_equals($token,$_POST['csrf'])) { http_response_code(403); exit('คำขอหมดอายุ'); }
            $db->beginTransaction();
            try {
                $_SESSION['network_notice']=anemiaSaveNetwork($db,$isAdmin,$grant,$staff,$_POST);
                $db->commit();
            } catch (DomainException | PDOException $error) {
                $db->rollBack();
                $_SESSION['network_notice']=$error instanceof DomainException ? $error->getMessage() : 'บันทึกไม่ได้ กรุณาตรวจรหัสหน่วยหรือรายการที่ซ้ำ';
            }
            header('Location: dashboard.php?view=network',true,303); exit;
        }
        $networkNotice=$_SESSION['network_notice']??'';
        unset($_SESSION['network_notice']);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['account_action'] ?? '') === 'change_password') {
        header('Content-Type: application/json; charset=UTF-8');
        $reply = static function (int $status,string $message): never {
            http_response_code($status);
            echo json_encode(['message'=>$message],JSON_THROW_ON_ERROR);
            exit;
        };
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($token,$_POST['csrf'])) $reply(403,'กรุณาเปิดหน้าใหม่แล้วลองอีกครั้ง');
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!is_string($current) || $current === '' || strlen($current)>128 ||
            !is_string($new) || strlen($new)>128 || preg_match('/^.{12,}$/usD',$new)!==1) {
            $reply(422,'กรอกรหัสผ่านปัจจุบัน และตั้งรหัสใหม่อย่างน้อย 12 ตัวอักษร โดยยาวไม่เกิน 128 ไบต์');
        }
        if ($new !== $confirm) $reply(422,'รหัสผ่านใหม่ทั้งสองช่องไม่ตรงกัน');
        if ($new === $current) $reply(422,'กรุณาตั้งรหัสผ่านใหม่ที่ต่างจากรหัสปัจจุบัน');
        if (!consumeAnemiaAttempt($db,'change-password:'.$staff)) $reply(429,'ลองหลายครั้งเกินไป กรุณารอสักครู่');
        $lookup = $db->prepare('SELECT salt,password_hash FROM anemia_staff WHERE username=? AND enabled=1');
        $lookup->execute([$staff]);
        $account = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$account || !hash_equals($account['password_hash'],hash_pbkdf2('sha256',$current,$account['salt'],600000,64))) {
            $reply(422,'รหัสผ่านปัจจุบันไม่ถูกต้อง');
        }
        $salt = bin2hex(random_bytes(24));
        $hash = hash_pbkdf2('sha256',$new,$salt,600000,64);
        $save = $db->prepare('UPDATE anemia_staff SET salt=?,password_hash=? WHERE username=? AND enabled=1 AND password_hash=?');
        $save->execute([$salt,$hash,$staff,$account['password_hash']]);
        if ($save->rowCount()!==1) $reply(409,'ข้อมูลบัญชีเปลี่ยนแล้ว กรุณาลองอีกครั้ง');
        session_regenerate_id(true);
        $reply(200,'เปลี่ยนรหัสผ่านแล้ว ครั้งถัดไปให้เข้าสู่ระบบด้วยรหัสใหม่');
    }
    $demoQueue = $view === 'contact-queue' && ($_GET['demo'] ?? '') === '1';
    $contactTable = $demoQueue ? 'anemia_contact_demo_queue' : 'anemia_contact_queue';
    $pendingContactCounts = ['appointment'=>0,'callback'=>0];
    $pendingDemoCounts = $pendingContactCounts;
    if ($canQueue) {
        $countPending=static function(string $table) use ($db,$queueScope,$queueParams): array {
            $query=$db->prepare("SELECT request_kind,COUNT(*) FROM $table q WHERE state='pending' AND $queueScope GROUP BY request_kind");
            $query->execute($queueParams);
            return array_map('intval',$query->fetchAll(PDO::FETCH_KEY_PAIR));
        };
        $pendingContactCounts = array_replace($pendingContactCounts,$countPending('anemia_contact_queue'));
        if ($view === 'contact-queue') {
            $pendingDemoCounts = array_replace($pendingDemoCounts,$countPending('anemia_contact_demo_queue'));
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
                $allowed=$db->prepare("SELECT q.receipt FROM $table q WHERE q.receipt=? AND $queueScope FOR UPDATE");
                $allowed->execute([$reference,...$queueParams]);
                $checkedIn = $allowed->fetchColumn() && anemiaApplyRequestAction($db,$table,$reference,'attended',$staff,null);
                $db->commit();
                $scanMessage = $checkedIn ? 'ยืนยันการเข้ารับบริการแล้ว' : 'คำขอนี้ไม่อยู่ในขั้นเช็กอิน';
            }
            $lookup = $db->prepare("SELECT q.*, (SELECT MIN(changed_at) FROM anemia_request_history h WHERE h.receipt=q.receipt AND h.state='attended') AS checked_in_at FROM $table q WHERE q.receipt=? AND $queueScope");
            $lookup->execute([$reference,...$queueParams]);
            $scannedRequest = $lookup->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$scannedRequest) $scanMessage = 'ไม่พบคำขอ กรุณาตรวจเลขหรือสแกนใบรับคำขออีกครั้ง';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($view === 'contact-queue') {
            if (!$canQueue || !is_string($_POST['csrf'] ?? null) || !hash_equals($token,$_POST['csrf'])) { http_response_code(403); exit('คำขอหมดอายุ'); }
            $deleteRequest = ($_POST['queue_action'] ?? '') === 'delete';
            if ($deleteRequest && !$isAdmin) { http_response_code(403); exit('เฉพาะผู้ดูแลระบบ'); }
            $receipt = $_POST['receipt'] ?? null;
            $state = $_POST['state'] ?? null;
            if (!is_string($receipt) || !preg_match($demoQueue ? '/^DE-[0-9A-F]{16}$/D' : '/^AN-[0-9A-F]{16}$/D',$receipt) || ($state !== null && (!is_string($state) || !isset(ANEMIA_REQUEST_STATES[$state])))) { http_response_code(400); exit('ข้อมูลไม่ถูกต้อง'); }
            $db->beginTransaction();
            $contactFields=$mobileQueue?',contact_name,phone':'';
            $lookup = $db->prepare("SELECT state,facility,facility_key,submitted_at,preferred_date,appointment_at,appointment_slot,next_contact_at$contactFields FROM $contactTable q WHERE receipt=? AND $queueScope FOR UPDATE");
            $lookup->execute([$receipt,...$queueParams]);
            $request = $lookup->fetch(PDO::FETCH_ASSOC);
            if (!$request) { $db->rollBack(); http_response_code(404); exit('ไม่พบคำขอ'); }
            if ($deleteRequest) {
                foreach (['anemia_request_confirmation','anemia_request_history',$contactTable] as $table) {
                    $delete=$db->prepare("DELETE FROM $table WHERE receipt=?");
                    $delete->execute([$receipt]);
                }
                $db->commit();
                error_log(sprintf('Anemia queue deleted receipt=%s actor=%s',$receipt,$staff));
                http_response_code(204);
                exit;
            }
            if ($state === null && isset($_POST['round_day'])) {
                $day = $_POST['round_day'];
                if (!is_string($day) || !preg_match('/^\d{4}-\d\d-\d\d$/D',$day) || !checkdate((int)substr($day,5,2),(int)substr($day,8,2),(int)substr($day,0,4))) {
                    $db->rollBack(); http_response_code(422); exit;
                }
                $counts = $db->prepare("SELECT COALESCE(q.appointment_slot,IF(HOUR(q.appointment_at)<12,'morning','afternoon')) AS slot,
                    SUM(q.state IN ('scheduled','attended','test_completed','follow_up') OR h.receipt IS NOT NULL) AS booked,
                    SUM(h.receipt IS NOT NULL) AS checked_in
                    FROM $contactTable q LEFT JOIN (SELECT DISTINCT receipt FROM anemia_request_history WHERE state='attended') h ON h.receipt=q.receipt
                    WHERE q.facility=? AND q.facility_key<=>? AND $queueScope AND q.appointment_at>=? AND q.appointment_at<DATE_ADD(?,INTERVAL 1 DAY) GROUP BY slot");
                $counts->execute([$request['facility'],$request['facility_key'],...$queueParams,$day,$day]);
                $rounds = array_fill_keys(array_keys(ANEMIA_APPOINTMENT_SLOTS),['booked'=>0,'checkedIn'=>0]);
                foreach ($counts as $count) $rounds[$count['slot']] = ['booked'=>(int)$count['booked'],'checkedIn'=>(int)$count['checked_in']];
                $db->commit();
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode($rounds,JSON_THROW_ON_ERROR);
                exit;
            }
            if ($state !== null && ($_POST['previous'] ?? '') !== $request['state']) {
                $db->rollBack(); http_response_code(409); exit('สถานะเปลี่ยนแล้ว กรุณาเปิดไทม์ไลน์อีกครั้ง');
            }
            if ($state !== null) {
                if (!in_array($request['state'],ANEMIA_REQUEST_ACTIONS[$state][1] ?? [],true)) { $db->rollBack(); http_response_code(409); exit('ดำเนินการขั้นนี้ไม่ได้'); }
                $when = null;
                $slot = null;
                if ($state==='scheduled') {
                    $raw = $_POST['appointment_day'] ?? '';
                    $slot = $_POST['appointment_slot'] ?? '';
                    $date = is_string($raw) && preg_match('/^\d{4}-\d\d-\d\d$/D',$raw) ? DateTimeImmutable::createFromFormat('!Y-m-d',$raw) : false;
                    if (!$date || $date->format('Y-m-d')!==$raw || $raw<date('Y-m-d') || !is_string($slot) || !isset(ANEMIA_APPOINTMENT_SLOTS[$slot])) {
                        $db->rollBack(); http_response_code(422); exit('กรุณาเลือกวันและรอบนัด');
                    }
                    $when = $date->format('Y-m-d H:i:s');
                } elseif (in_array($state,['retry_contact','follow_up'],true)) {
                    $day = $_POST['appointment_day'] ?? '';
                    $time = $_POST['callback_time'] ?? '';
                    if ($state==='follow_up') {
                        $hour = $_POST['contact_hour'] ?? '';
                        $minute = $_POST['contact_minute'] ?? '';
                        $time = is_string($hour) && is_string($minute) ? $hour.':'.$minute : '';
                    }
                    $raw = is_string($day) && is_string($time) ? $day.'T'.$time : '';
                    $date = is_string($raw) && preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d$/D',$raw) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw) : false;
                    if (!$date || $date->format('Y-m-d\TH:i') !== $raw || $day<date('Y-m-d')) { $db->rollBack(); http_response_code(422); exit('กรุณาระบุวันและเวลา'); }
                    if ($state==='retry_contact' && (!in_array($time,$callbackTimes,true) || (int)$date->format('N')>5 || in_array($day,$callbackClosedDays,true) || $raw<=date('Y-m-d\TH:i'))) {
                        $db->rollBack(); http_response_code(422); exit('กรุณาเลือกวันทำการและเวลาติดต่อที่ยังไม่ผ่าน');
                    }
                    $when = $date->format('Y-m-d H:i:s');
                }
                if ($state==='test_completed') {
                    $result = $_POST['confirmation_result'] ?? '';
                    $day = $_POST['examined_on'] ?? '';
                    $date = is_string($day) && preg_match('/^\d{4}-\d\d-\d\d$/D',$day) ? DateTimeImmutable::createFromFormat('!Y-m-d',$day) : false;
                    if (!is_string($result) || !isset(ANEMIA_CONFIRMATION_RESULTS[$result]) || !$date || $date->format('Y-m-d')!==$day || $day>date('Y-m-d') || $day<substr($request['submitted_at'],0,10)) {
                        $db->rollBack(); http_response_code(422); exit('กรุณาระบุผลตรวจและวันที่ตรวจจริง');
                    }
                    $db->prepare('INSERT INTO anemia_request_confirmation (receipt,result_code,examined_on) VALUES (?,?,?)')->execute([$receipt,$result,$day]);
                }
                anemiaApplyRequestAction($db,$contactTable,$receipt,$state,$staff,$when,$slot);
                $lookup->execute([$receipt,...$queueParams]);
                $request = $lookup->fetch(PDO::FETCH_ASSOC);
                if (!$request) { $db->rollBack(); http_response_code(409); exit('สิทธิ์เข้าถึงคำขอเปลี่ยนแล้ว'); }
            }
            $request['actions'] = array_filter(ANEMIA_REQUEST_ACTIONS,static fn($action)=>in_array($request['state'],$action[1],true));
            $request['appointment_label'] = $request['appointment_at'] && in_array($request['state'],['scheduled','attended','test_completed','follow_up'],true) ? anemiaAppointmentLabel($request['appointment_at'],$request['appointment_slot']) : null;
            $request['next_contact_label'] = $request['next_contact_at'] ? anemiaAppointmentLabel($request['next_contact_at'],null) : null;
            $history = $db->prepare('SELECT state,actor,changed_at FROM anemia_request_history WHERE receipt=? ORDER BY id');
            $history->execute([$receipt]);
            $request['history'] = $history->fetchAll(PDO::FETCH_ASSOC);
            $confirmation = $db->prepare('SELECT result_code,examined_on FROM anemia_request_confirmation WHERE receipt=?');
            $confirmation->execute([$receipt]);
            $exam = $confirmation->fetch(PDO::FETCH_ASSOC);
            $request['confirmation_label'] = $exam ? ANEMIA_CONFIRMATION_RESULTS[$exam['result_code']].' · '.workspaceThaiShortDate($exam['examined_on']) : null;
            $db->commit();
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($request,JSON_THROW_ON_ERROR);
            exit;
        }
        if ($view === 'services') {
            if (!$isAdmin || !is_string($_POST['csrf']??null) || !hash_equals($token,$_POST['csrf'])) {
                http_response_code(403); exit('คำขอหมดอายุ');
            }
            require_once __DIR__.'/_private/service-settings.php';
            $service=$_POST['service']??'';
            if (!is_string($service) || !isset(anemiaServiceDefinitions()[$service])) {
                http_response_code(400); exit('ไม่พบบริการ');
            }
            try {
                $action=$_POST['service_action']??'';
                if ($action==='save') {
                    anemiaSaveService($service,$_POST,$staff);
                    if ($service==='otp') unset($_SESSION['service_otp_test'],$_SESSION['service_otp_verified_at']);
                    $_SESSION['service_notice']='บันทึกค่าบริการแล้ว';
                } elseif ($service==='otp' && in_array($action,['otp-send','otp-check'],true)) {
                    $_SESSION['service_notice']=anemiaTestServiceOtp($action,$_POST);
                } else {
                    throw new DomainException('คำขอไม่ถูกต้อง');
                }
            } catch (DomainException $error) {
                $_SESSION['service_notice']=$error->getMessage();
            }
            header('Location: dashboard.php?view=services&service='.$service,true,303);
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
    if ($view==='reports') {
        require_once __DIR__.'/_private/province-report.php';
        $reportMonth=$evaluationMonth ?: date('Y-m');
        $provinceReport=anemiaProvinceReport($db,$provinces,$reportMonth,$sampleMode);
    }
    $attentionOnly = ($_GET['attention'] ?? '') === '1';
    $queueNow = $db->quote(date('Y-m-d H:i:s'));
    $overdue = "(state='scheduled' AND CASE WHEN appointment_slot IS NULL THEN appointment_at<$queueNow ELSE DATE(appointment_at)<DATE($queueNow) END)";
    $attention = "($overdue OR state IN ('pending','accepted') OR (state IN ('retry_contact','follow_up') AND next_contact_at<=$queueNow))";
    $todayStart=$db->quote(date('Y-m-d').' 00:00:00');
    $tomorrowStart=$db->quote(date('Y-m-d',strtotime('tomorrow')).' 00:00:00');
    $mobileFilters=[
        'pending'=>['รอรับเรื่อง',"q.state='pending'"],
        'today'=>['นัดวันนี้',"q.state='scheduled' AND q.appointment_at >= $todayStart AND q.appointment_at < $tomorrowStart"],
        'follow'=>['ต้องติดตาม',"($overdue OR q.state IN ('accepted','contacted','retry_contact','follow_up'))"],
        'history'=>['ประวัติ',"q.state IN ('attended','test_completed','closed')"],
    ];
    $queueTab = $_GET['tab'] ?? ($attentionOnly?'follow':'pending');
    if (!is_string($queueTab) || !isset($mobileFilters[$queueTab])) $queueTab='pending';
    $contactQueue=[];
    if ($view==='contact-queue') {
        if ($mobileQueue) {
            $metrics=array_map(static fn($entry)=>'COALESCE(SUM('.$entry[1].'),0)',$mobileFilters);
            $counts=$db->prepare('SELECT '.implode(', ',$metrics)." FROM $contactTable q WHERE $queueScope");
            $counts->execute($queueParams);
            $mobileTotals=array_combine(array_keys($mobileFilters),$counts->fetch(PDO::FETCH_NUM));
        } else {
        $report=$db->prepare("SELECT COUNT(*) AS requested,
            SUM(q.state IN ('attended','test_completed','follow_up') OR EXISTS
                (SELECT 1 FROM anemia_request_history h WHERE h.receipt=q.receipt AND h.state='attended')) AS attended,
            SUM(c.receipt IS NOT NULL) AS recorded,
            SUM(c.result_code='confirmed') AS confirmed,
            SUM(c.result_code='not_confirmed') AS not_confirmed,
            SUM(c.result_code='inconclusive') AS inconclusive
            FROM $contactTable q LEFT JOIN anemia_request_confirmation c ON c.receipt=q.receipt WHERE $queueScope");
        $report->execute($queueParams);
        $referralStats=$report->fetch(PDO::FETCH_ASSOC);
        $stateCounts=$db->prepare("SELECT q.state,COUNT(*) FROM $contactTable q WHERE $queueScope".
            ($attentionOnly?' AND '.$attention:'').' GROUP BY q.state');
        $stateCounts->execute($queueParams);
        $queueStateTotals=$stateCounts->fetchAll(PDO::FETCH_KEY_PAIR);

        }
        $focusReceipt=$_GET['request'] ?? '';
        if (!is_string($focusReceipt) || !preg_match($demoQueue ? '/^DE-[0-9A-F]{16}$/D' : '/^AN-[0-9A-F]{16}$/D',$focusReceipt)) $focusReceipt='';
        $queuePage = filter_input(INPUT_GET,'page',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]) ?: 1;
        $queueState = $_GET['status'] ?? '';
        if (!is_string($queueState) || !isset(ANEMIA_REQUEST_STATES[$queueState]) || $mobileQueue) $queueState='';
        $queueKind = $_GET['kind'] ?? '';
        if ($queueState!=='pending' || !in_array($queueKind,['appointment','callback'],true)) $queueKind='';
        $pageSize = $mobileQueue ? 3 : 15;
        $offset = $focusReceipt!=='' ? 0 : ($queuePage-1)*$pageSize;
        $queueLimit = ($pageSize+1).' OFFSET '.$offset;
        $listParams=$queueParams;
        $queueCondition=$focusReceipt!==''?' AND q.receipt=?':($mobileQueue?' AND ('.$mobileFilters[$queueTab][1].')':($attentionOnly?' AND '.$attention:''));
        if ($focusReceipt!=='') {
            $listParams[]=$focusReceipt;
        } elseif ($queueState!=='') {
            $queueCondition.=' AND q.state=?';
            $listParams[]=$queueState;
            if ($queueKind!=='') {
                $queueCondition.=' AND q.request_kind=?';
                $listParams[]=$queueKind;
            }
        }
        $queue=$db->prepare("SELECT receipt,facility,request_kind,contact_name,phone,preferred_date,submitted_at,state,appointment_slot,$overdue AS overdue FROM $contactTable q WHERE $queueScope".$queueCondition.' ORDER BY submitted_at DESC,receipt DESC LIMIT '.$queueLimit);
        $queue->execute($listParams);
        $contactQueue=$queue->fetchAll(PDO::FETCH_ASSOC);
        $queueNext=count($contactQueue)>$pageSize;
        if ($queueNext) array_pop($contactQueue);
    }
    $forms = $view === 'assessment' ? $db->query('SELECT version_key,state FROM anemia_form_version ORDER BY version_key')->fetchAll(PDO::FETCH_ASSOC) : [];
    if ($view==='programme-report') {
        require_once __DIR__.'/_private/participant-programme.php';
        $query=$db->prepare("SELECT COUNT(*) AS started, COUNT(a.account_id) AS paired,
            AVG(CASE WHEN a.account_id IS NOT NULL THEN 100.0*b.score/NULLIF(b.max_score,0) END) AS before_mean,
            AVG(100.0*a.score/NULLIF(a.max_score,0)) AS after_mean
            FROM anemia_participant_programme_assessment b
            LEFT JOIN anemia_participant_programme_assessment a ON a.account_id=b.account_id AND a.programme_version=b.programme_version
                AND a.instrument_version=b.instrument_version AND a.phase='after'
            WHERE b.programme_version=? AND b.instrument_version=? AND b.phase='before'");
        $query->execute([ANEMIA_PROGRAMME_VERSION,ANEMIA_PROGRAMME_INSTRUMENT_VERSION]);
        $programmeStats=$query->fetch(PDO::FETCH_ASSOC);
        $query=$db->prepare('SELECT week_no,COUNT(*) AS completed FROM anemia_participant_programme_progress WHERE programme_version=? GROUP BY week_no');
        $query->execute([ANEMIA_PROGRAMME_VERSION]);
        $programmeProgress=$query->fetchAll(PDO::FETCH_KEY_PAIR);
    }
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
        header('Content-Disposition: attachment; filename="anemia-province-'.$reportMonth.'.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, chr(239).chr(187).chr(191));
        fputcsv($out, ['เดือน','รหัสจังหวัด','จังหวัด','เขตสุขภาพ',...array_values(ANEMIA_PROVINCE_METRICS)], ',', '"', '');
        foreach ($provinceReport as $row) {
            $metrics=array_intersect_key($row,ANEMIA_PROVINCE_METRICS);
            fputcsv($out, [$reportMonth,$row['code'],$row['name'],zoneLabel((int)$row['region']),...array_values($metrics)], ',', '"', '');
        }
        exit;
    }
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(503);
    error_log('Anemia dashboard failed (' . get_class($error) . ')');
    exit('ไม่สามารถโหลดข้อมูลหลังบ้านได้ กรุณาลองใหม่อีกครั้ง');
}
$tabs = ['overview'=>'ภาพรวม','reports'=>'รายงานจังหวัด','evaluation'=>'ประเมินโครงการ','activity'=>'สถิติการทดลองแบบประเมิน','team'=>'ทีมวิจัยและพัฒนา','guide'=>'คู่มือการใช้งาน','about'=>'เกี่ยวกับระบบ'];
$tabs['mobile'] = 'Anemia Staff';
$tabs['scanner-install'] = 'ติดตั้งแอปสแกน QR';
if ($isAdmin) $tabs += ['assessment'=>'แบบประเมิน','access'=>'จัดการสิทธิ์','audit'=>'ประวัติสิทธิ์','release'=>'เวอร์ชันและอัปเดต','sample-settings'=>'จัดการข้อมูล','services'=>'การเชื่อมต่อบริการ','contact-queue'=>'คำขอนัดและติดต่อกลับ','scan-request'=>'สแกน QR คำขอ'];
if ($canQueue) $tabs += ['contact-queue'=>'คำขอนัดและติดต่อกลับ','scan-request'=>'สแกน QR คำขอ'];
if ($isAdmin) $tabs['programme-report']='ผลการเรียนรู้';
if ($canNetwork) $tabs['network']='เครือข่ายหน่วยบริการ';
if ($isAdmin) $tabs['members']='สมาชิก';
$showSample = $isAdmin && $view === 'overview' && $sampleMode;
$tabIcons = ['overview'=>'chart','reports'=>'map','evaluation'=>'ready','activity'=>'chart','assessment'=>'report','access'=>'users','audit'=>'history','team'=>'users','guide'=>'book','about'=>'info','release'=>'history','sample-settings'=>'chart','services'=>'spark','contact-queue'=>'phone','scan-request'=>'scan'];
$tabIcons['network']='hospital';
$tabIcons['members']='users';
$tabIcons['programme-report']='book';
$tabs['schools']='กิจกรรมโรงเรียน';
$tabIcons['schools']='users';
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
function workspaceThaiShortDate(string $value): string
{
    $date = new DateTimeImmutable($value,new DateTimeZone('Asia/Bangkok'));
    $days = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
    $months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    return $days[(int)$date->format('w')].'ที่ '.$date->format('j').' '.$months[(int)$date->format('n')-1].' '.((int)$date->format('Y')+543);
}

function workspaceThaiMonth(string $month): string
{
    $months = [1=>'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    [$year,$number] = array_map('intval',explode('-',$month));
    return $months[$number] . ' ' . ($year + 543);
}

function workspaceMonthFilters(string $selected, bool $allowAll, string $monthLabel='เดือน'): void
{
    $currentYear=(int)date('Y');
    $year=$selected!==''?(int)substr($selected,0,4):$currentYear;
    $years=array_unique([...range($currentYear,$currentYear-3),$year]);
    rsort($years);
    ?>
    <label>ปี พ.ศ.<select name="month_year">
     <?php foreach ($years as $option): ?><option value="<?= $option ?>" <?= $option===$year?'selected':'' ?>><?= $option+543 ?></option><?php endforeach; ?>
    </select></label>
    <label><?= esc($monthLabel) ?><select name="month_number">
     <?php if ($allowAll): ?><option value="" <?= $selected===''?'selected':'' ?>>ทุกเดือนทุกปี</option><?php endif; ?>
     <?php for ($i=1;$i<=12;$i++):
       $number=sprintf('%02d',$i);
       $label=explode(' ',workspaceThaiMonth('2000-'.$number))[0]; ?>
      <option value="<?= $number ?>" <?= $number===substr($selected,5)?'selected':'' ?>><?= esc($label) ?></option>
     <?php endfor; ?>
    </select></label>
    <?php
}

require __DIR__ . '/_private/icons.php';
if ($view==='schools') {
    ob_start();
    try {
        $renderSchools=require __DIR__.'/_private/school-view.php';
        $renderSchools($db,$identity);
        $schoolContent=ob_get_clean();
    } catch (Throwable $error) {
        ob_end_clean();
        http_response_code(503);
        error_log('Anemia schools unavailable: '.get_class($error));
        $schoolContent='<p class="notice" role="alert">ยังเปิดกิจกรรมโรงเรียนไม่ได้ กรุณาลองอีกครั้ง</p>';
    }
}


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
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= esc($unitScanner?'สแกน QR ประจำหน่วย':$tabs[$view]) ?> · Anemia Portal</title><script src="theme.js?v=5"></script><script defer src="installed-update.js"></script><link rel="stylesheet" href="style.css?v=portal-ui-v148">
<link rel="manifest" href="<?= $scannerApp?'staff-scanner.webmanifest':'staff.webmanifest' ?>"><link rel="apple-touch-icon" href="<?= $scannerApp?'staff-scanner-192.png':'staff-icon-192.png' ?>"><meta name="theme-color" content="#392656">
<script defer src="staff-install.js?v=7"></script>
<?php if ($showSample): ?>
<script defer src="health-region-map.js"></script>
<script defer src="national-registry.js"></script>
<script defer src="admin.js?v=staff-details-v16"></script>
<script defer src="hosted-dashboard.js?v=scroll-v3"></script>
<?php endif; ?>
<?php if ($view === 'scan-request'): ?><script defer src="jsQR.js?v=1.4.0"></script><script defer src="request-scanner.js?v=3"></script><?php endif; ?>
<?php if ($view==='schools'): ?><script defer src="qrcode.js?v=2.0.4"></script><script defer src="school-qr.js?v=7"></script><?php endif; ?>
</head><body class="staff-dashboard-page<?= $staffAppView?' staff-app-page':'' ?><?= $view==='mobile'?' staff-app-home':'' ?><?= $scannerApp?' staff-scanner-page':'' ?>">
<main style="max-width:1220px">
<?php if (!$staffAppView) workspaceBack($view === 'overview' ? 'index.html' : 'dashboard.php?view=overview'); ?>
<section class="panel wide" style="padding:0;overflow:hidden">
<div class="admin-shell hosted-workspace">
 <?php if ($staffAppView): ?>
 <header class="staff-app-bar">
  <span class="staff-mobile-mark"><?= workspaceIcon($scannerApp?'scan':'drop') ?></span>
  <div><strong><?= $scannerApp?'สแกน QR ประจำหน่วย':'Anemia Staff' ?></strong><small><?= esc($canQueue?$scanUnit:$scopeName) ?></small></div>
  <?php if ($mobileQueue): $newCount=array_sum($pendingContactCounts); ?>
   <a class="staff-app-bell" href="dashboard.php?view=contact-queue&amp;app=staff&amp;tab=pending" aria-label="คำขอใหม่ <?= $newCount ?> รายการ" data-staff-bell><?= workspaceIcon('bell') ?><span class="contact-pending-badge" data-pending-count="live.total" <?= $newCount===0?'hidden':'' ?>><?= $newCount>99?'99+':$newCount ?></span></a>
  <?php elseif (!$unitScanner): ?>
  <form method="post" action="<?= $scannerApp?'login?next=unit-scan':'login?next=mobile' ?>"><input type="hidden" name="csrf" value="<?= esc($token) ?>"><button type="submit" name="action" value="logout" aria-label="ออกจากระบบ" title="ออกจากระบบ"><?= workspaceIcon('logout') ?></button></form>
  <?php endif; ?>
 </header>
 <?php else: ?>
 <header class="admin-top no-print">
  <div class="workspace-header-art" aria-hidden="true"></div>
  <div class="admin-top-main">
   <div class="admin-brand"><span class="workspace-brand-icon"><?= workspaceIcon('drop') ?></span><div><strong>Anemia Portal</strong><span class="workspace-brand-caption">ศูนย์จัดการสำหรับเจ้าหน้าที่</span></div></div>
   <div class="workspace-account">
    <button type="button" class="workspace-identity" data-workspace-dialog="account" aria-haspopup="dialog" aria-label="จัดการบัญชี <?= esc($staff) ?>" title="เปลี่ยนรหัสผ่าน"><span class="workspace-account-icon"><?= workspaceIcon('users') ?></span><span><strong><?= esc($staff) ?></strong><small><?= esc($roleLabel) ?></small></span><span class="hosted-menu-caret" aria-hidden="true"></span></button>
    <form class="hosted-logout" method="post" action="login"><input type="hidden" name="csrf" value="<?= esc($token) ?>"><button class="action hosted-logout-button" name="action" value="logout" aria-label="ออกจากระบบ" title="ออกจากระบบ"><?= workspaceIcon('logout') ?></button></form>
   </div>
  </div>
  <nav class="admin-top-tabs" aria-label="เมนูหลังบ้าน">
   <?php
    $groups = [
        ['ภาพรวม','chart',['overview']],
        ['รายงาน','map',['reports','activity','evaluation','programme-report','schools']],
        ['นัดหมาย','calendar',['contact-queue','scan-request']],
        ['ระบบ','users',['members','access','audit','sample-settings','network','services']],
        ['แบบประเมิน','ready',['assessment']],
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
      <?php if ($keys[0]==='guide'): ?>
       <a class="staff-install-action" href="staff-install.html" data-staff-install><?= workspaceIcon('download') ?>ติดตั้ง Anemia Staff</a>
      <?php endif; ?>
     </div>
    </details>
   <?php endif; endforeach; ?>
  </nav>
 </header>
 <?php endif; ?>
 <div class="admin-content" id="staff-report-content">
  <?php if (!$staffAppView): ?><div class="admin-heading"><h1 class="title"><?= esc($view === 'overview' ? 'ภาพรวมการคัดกรองและติดตาม' : $tabs[$view]) ?></h1>
   <p><?= esc($scopeName) ?></p>
   <?php if ($view==='scan-request'): ?><a class="action no-print" href="dashboard.php?view=mobile"><?= workspaceIcon('back') ?>งานเจ้าหน้าที่</a><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php if ($sampleEnabled && in_array($view,$sampleViews,true)): ?>
   <div class="hosted-sample-caption<?= $view==='reports'?' province-report-source':'' ?>"><strong><?= $sampleMode ? 'ข้อมูลจำลอง' : 'ข้อมูลจากระบบ' ?></strong>
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
  <section class="card admin-report hosted-live-summary staff-detail-card">
   <div class="hosted-registry-heading"><span class="iconbox"><?= workspaceIcon('ready') ?></span><div><h2>ประเมินการใช้งาน</h2><p><?= $sampleMode ? 'ตัวอย่างเดือน' : 'ข้อมูลที่ส่งจริงในเดือน' ?> <?= esc(date('m/Y')) ?></p></div></div>
   <dl><div><dt>แบบประเมินที่ได้รับ</dt><dd><?= number_format($responseCount) ?></dd></div>
   <div><dt>ความพึงพอใจเฉลี่ย เต็ม 5</dt><dd><?= $responseCount ? number_format((float)$evaluation['overall']['average'],2) : '—' ?></dd></div></dl>
   <a class="action staff-detail-link" href="dashboard.php?view=evaluation&amp;region=<?= $region ?>"><?= workspaceIcon('report') ?>เปิดรายงานประเมินโครงการ</a>
  </section>
  <section class="card admin-report hosted-live-summary staff-detail-card">
   <div><h2>การใช้งานแบบประเมินต้นแบบ</h2><p>จำนวนครั้งในเดือน <?= esc(workspaceThaiMonth($activityMonth)) ?></p></div>
   <dl><div><dt>เริ่มทดลอง</dt><dd><?= number_format($activity['counts']['start']) ?></dd></div>
   <div><dt>ทำรอบแรกครบ</dt><dd><?= number_format($activity['counts']['first']) ?></dd></div></dl>
   <a class="action staff-detail-link" href="dashboard.php?view=activity&amp;region=<?= $region ?>"><?= workspaceIcon('chart') ?>ดูสถิติแต่ละขั้นตอน</a>
  </section>
  <section class="hosted-registry-section" aria-label="ทะเบียนพื้นที่จากฐานข้อมูลจริง">
  <h2><?= $sampleMode ? 'ทะเบียนพื้นที่ตัวอย่าง' : 'ทะเบียนจากฐานข้อมูลบนโฮสต์' ?></h2>
  <div class="admin-kpis hosted-kpis">
    <?php foreach ([
      ['map','เขตสุขภาพ','regions'],
      ['province','จังหวัดในทะเบียน','provinces'],
      ['ready','จังหวัดที่ตั้งสถานะเปิด','province_enabled'],
    ] as [$symbol,$label,$key]): ?>
      <a class="card staff-detail-card staff-kpi-link" href="dashboard.php?view=reports&amp;region=<?= $region ?>#province-register"><span class="iconbox"><?= workspaceIcon($symbol) ?></span><div class="admin-kpi-copy"><span><?= esc($label) ?></span><strong><?= number_format((int)$stats[$key]) ?></strong></div><?= workspaceIcon('next') ?></a>
    <?php endforeach; ?>
  </div>
  <section class="card admin-report hosted-registry staff-detail-card">
   <div class="hosted-registry-heading"><span class="iconbox"><?= workspaceIcon('report') ?></span><div><h2>ทะเบียนพื้นที่</h2><p>ดูรายชื่อจังหวัด เขตสุขภาพ และสถานะการเตรียมเปิดบริการ</p></div></div>
   <a class="action primary staff-detail-link" href="dashboard.php?view=reports&amp;region=<?= $region ?>#province-register"><?= workspaceIcon('map') ?>ดูรายงานจังหวัด</a>
  </section>
  <details class="card admin-report hosted-region-progress">
   <summary class="region-progress-summary">
    <span class="iconbox" aria-hidden="true"><?= workspaceIcon('map') ?></span>
    <strong>สถานะทะเบียนรายเขต</strong>
    <span class="region-progress-summary-count">เปิด <?= array_sum(array_column($regionTotals,'enabled')) ?> / <?= array_sum(array_column($regionTotals,'all')) ?> จังหวัด</span>
    <span class="region-progress-chevron" aria-hidden="true"></span>
   </summary>
   <div class="region-progress-grid">
   <?php foreach ($regionTotals as $zone=>$totals): ?>
    <a href="dashboard.php?view=reports&amp;region=<?= $zone ?>"<?= $totals['enabled']===$totals['all'] ? ' data-complete' : '' ?>>
     <span class="region-progress-icon" aria-hidden="true"><?= workspaceIcon('map') ?></span>
     <span class="region-progress-label"><?= esc(zoneLabel($zone)) ?></span>
     <strong class="region-progress-total"><?= $totals['enabled'] ?> / <?= $totals['all'] ?></strong>
     <span class="region-progress-arrow" aria-hidden="true"><?= workspaceIcon('next') ?></span>
     <meter min="0" max="<?= $totals['all'] ?>" value="<?= $totals['enabled'] ?>" aria-label="จังหวัดที่ตั้งสถานะเปิด"></meter>
    </a>
   <?php endforeach; ?>
   </div>
  </details></section>
  <?php elseif ($view === 'schools'): ?>
   <?= $schoolContent ?>
  <?php elseif ($view === 'activity'): ?>
  <form class="admin-filters hosted-scope-filter hosted-filter-row" method="get"><input type="hidden" name="view" value="activity">
   <?php if ($canChooseRegion): ?><label>เขตสุขภาพ<select name="region"><option value="0">ทุกเขต</option>
    <?php for ($i=1;$i<=13;$i++): ?><option value="<?= $i ?>" <?= $region === $i ? 'selected' : '' ?>><?= esc(zoneLabel($i)) ?></option><?php endfor; ?>
   </select></label><?php endif; ?>
   <?php workspaceMonthFilters($activityMonth,false,'เดือนที่เริ่มประเมิน'); ?>
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
  <div class="admin-report-toolbar province-report-toolbar no-print">
   <form class="admin-filters hosted-filter-row" method="get"><input type="hidden" name="view" value="reports">
    <?php if ($canChooseRegion): ?><label>เขตสุขภาพ<select name="region"><option value="0">ทุกเขต</option>
     <?php for ($i=1;$i<=13;$i++): ?><option value="<?= $i ?>" <?= $region === $i ? 'selected' : '' ?>><?= esc(zoneLabel($i)) ?></option><?php endfor; ?>
    </select></label><?php endif; ?>
    <?php workspaceMonthFilters($reportMonth,false); ?>
    <button class="action" type="submit"><?= workspaceIcon('filter') ?>แสดง</button>
   </form>
   <div class="actions"><?php if (!$sampleMode): ?><a class="action" href="dashboard.php?view=reports&amp;region=<?= $region ?>&amp;month=<?= esc($reportMonth) ?>&amp;download=csv"><?= workspaceIcon('download') ?>ดาวน์โหลด CSV</a><?php endif; ?><button type="button" class="action primary" onclick="window.print()"><?= workspaceIcon('print') ?>พิมพ์รายงาน</button></div>
  </div>
  <section class="card admin-report province-performance" aria-labelledby="province-performance-title">
   <h2 id="province-performance-title">ผลคัดกรองและการส่งต่อรายจังหวัด</h2>
   <p><?= esc(workspaceThaiMonth($reportMonth)) ?> · <?= $sampleMode?'ข้อมูลจำลอง':'ข้อมูลจากระบบ' ?></p>
   <?php $shortMetrics=['started'=>'เริ่ม','completed'=>'ทำครบ','low'=>'ต่ำ','medium'=>'ปานกลาง',
       'high'=>'สูง','unknown'=>'ไม่สรุป','requested'=>'ส่งคำขอ','attended'=>'มารับบริการ',
       'tested'=>'ตรวจแล้ว','confirmed'=>'ผลยืนยัน']; ?>
   <div class="tablewrap"><table class="admin-table">
    <thead><tr><th scope="col" rowspan="2">จังหวัด</th><th scope="colgroup" colspan="6">การคัดกรอง (ครั้ง)</th><th scope="colgroup" colspan="4">การส่งต่อ (คำขอ)</th></tr>
    <tr><?php foreach (ANEMIA_PROVINCE_METRICS as $key=>$label): ?><th scope="col" title="<?= esc($label) ?>" aria-label="<?= esc($label) ?>"><?= esc($shortMetrics[$key] ?? $label) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($provinceReport as $row): ?>
     <tr><th scope="row"><?= esc($row['name']) ?></th><?php foreach (ANEMIA_PROVINCE_METRICS as $key=>$_): ?><td><?= number_format($row[$key]) ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    <?php if (!$provinceReport): ?><tr><td colspan="11">ไม่มีจังหวัดในขอบเขตที่เลือก</td></tr><?php endif; ?>
    </tbody>
   </table></div>
   <details class="province-report-notes">
    <summary>วิธีนับและขอบเขตข้อมูล</summary>
   <p class="notice">ผลคัดกรองนับตามเดือนที่เริ่มและจังหวัดที่ผู้ใช้ระบุ ผลส่งต่อนับตามเดือนที่ส่งคำขอและจังหวัดของหน่วยบริการในทะเบียน โดยแสดงผลติดตามล่าสุดของคำขอในเดือนนั้น</p>
   <p class="notice">ยอดคัดกรองและยอดคำขอมาจากคนละชุดข้อมูล จึงยังคำนวณอัตราจากการคัดกรองไปถึงการตรวจรายคนไม่ได้ ข้อมูลการเรียนยังไม่แยกตามจังหวัด</p>
   </details>
  </section>
  <details class="card admin-report" id="province-register"><summary>ทะเบียนจังหวัด</summary>
  <div class="tablewrap"><table class="admin-table"><thead><tr><th>รหัส</th><th>จังหวัด</th><th>เขตสุขภาพ</th><th>สถานะจังหวัด</th></tr></thead><tbody>
  <?php foreach ($provinces as $p): ?><tr><td><?= esc($p['code']) ?></td><th scope="row"><?= esc($p['name']) ?></th><td><?= esc(zoneLabel((int)$p['region'])) ?></td><td><?= (int)$p['enabled'] ? 'ตั้งสถานะเปิด' : 'ปิด' ?></td></tr><?php endforeach; ?>
  </tbody></table></div></details>
  <?php elseif ($view === 'programme-report'): ?>
  <section class="card admin-report">
   <h2>กิจกรรม 12 สัปดาห์</h2>
   <p>ข้อมูลจากกิจกรรมฉบับร่าง เปรียบเทียบคะแนนของผู้ที่ตอบครบทั้งก่อนและหลังในแบบประเมินรุ่นเดียวกัน</p>
   <div class="tablewrap"><table class="admin-table"><thead><tr><th>เริ่มทำแบบประเมิน</th><th>ตอบครบทั้งสองครั้ง</th><th>เฉลี่ยก่อนเรียน</th><th>เฉลี่ยหลังเรียน</th></tr></thead><tbody><tr>
    <td><?= number_format((int)$programmeStats['started']) ?> คน</td><td><?= number_format((int)$programmeStats['paired']) ?> คน</td>
    <td><?= $programmeStats['before_mean']===null?'—':number_format((float)$programmeStats['before_mean'],1).'%' ?></td>
    <td><?= $programmeStats['after_mean']===null?'—':number_format((float)$programmeStats['after_mean'],1).'%' ?></td>
   </tr></tbody></table></div>
   <h3>ความคืบหน้ารายสัปดาห์</h3>
   <div class="tablewrap"><table class="admin-table"><thead><tr><th>สัปดาห์</th><th>กิจกรรม</th><th>ทำเสร็จแล้ว</th></tr></thead><tbody>
    <?php foreach (anemiaProgrammeWeeks() as $week=>[$title]): ?><tr><th scope="row"><?= $week ?></th><td><?= esc($title) ?></td><td><?= number_format((int)($programmeProgress[$week] ?? 0)) ?> คน</td></tr><?php endforeach; ?>
   </tbody></table></div>
  </section>
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
   <?php workspaceMonthFilters($evaluationMonth,true); ?>
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
  <?php elseif ($view === 'guide'): require __DIR__.'/_private/guide-view.php'; ?>
  <?php elseif ($view === 'about'): ?>
  <section class="card admin-report"><?php workspaceInfo($view, $localRelease); ?></section>
  <?php elseif ($view === 'members'): require __DIR__.'/_private/members-view.php'; ?>
  <?php elseif ($view === 'network'): require __DIR__.'/_private/network-view.php'; ?>
  <?php elseif ($view === 'mobile'): require __DIR__.'/_private/staff-mobile-view.php'; ?>
  <?php elseif ($view === 'scanner-install'): ?>
  <section class="card admin-report staff-scanner-install" data-install-other-app>
   <h1>ติดตั้งแอปสแกน QR</h1><p>เปิดจากหน้าจอหลักเพื่อสแกนใบรับคำขอของผู้มารับบริการ ระบบใช้หน่วยบริการตามสิทธิ์บัญชีของคุณ</p>
   <div class="actions">
    <a class="action primary" href="#scanner-install-help" data-staff-install><?= workspaceIcon('download') ?>ติดตั้งแอปสแกน QR</a>
    <a class="action" href="dashboard.php?view=scan-request&amp;app=unit"><?= workspaceIcon('scan') ?>เปิดหน้าสแกน</a>
    <a class="action" href="dashboard.php?view=mobile"><?= workspaceIcon('back') ?>งานเจ้าหน้าที่</a>
   </div>
   <p data-install-result data-app-name="สแกน QR ประจำหน่วย" role="status" hidden></p>
   <div id="scanner-install-help" data-install-help>
    <h2>ติดตั้งผ่านเมนูเบราว์เซอร์</h2>
    <p>หากเปิดจากแอป Anemia Staff อยู่ ให้เปิด<a href="dashboard.php?view=scan-request&amp;app=unit">หน้าสแกน QR ประจำหน่วย</a>ใน Safari หรือ Chrome ก่อนเพิ่มไปยังหน้าจอหลัก</p>
    <p><strong>iPhone / iPad</strong> ใน Safari เลือก แชร์ → เพิ่มไปยังหน้าจอโฮม</p>
    <p><strong>Android</strong> ใน Chrome เปิดเมนู ⋮ แล้วเลือก ติดตั้งแอป หรือ เพิ่มลงในหน้าจอหลัก</p>
   </div>
  </section>
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
     <?php if (!$unitScanner): ?><div><dt>วันที่สะดวก</dt><dd><?= esc($scannedRequest['preferred_date'] ?? 'ไม่ระบุ') ?></dd></div>
     <div><dt>เวลาที่ส่งคำขอ</dt><dd><?= esc(workspaceThaiShortDate($scannedRequest['submitted_at']).' เวลา '.substr($scannedRequest['submitted_at'],11,5).' น.') ?></dd></div><?php endif; ?>
     <?php if ($scannedRequest['appointment_at']): ?><div><dt>วันนัด</dt><dd><?= esc(anemiaAppointmentLabel($scannedRequest['appointment_at'],$scannedRequest['appointment_slot'])) ?></dd></div><?php endif; ?>
     <?php if ($scannedRequest['checked_in_at']): ?><div><dt>เวลาเช็กอิน</dt><dd><?= esc($scannedRequest['checked_in_at']) ?></dd></div><?php endif; ?>
    </dl>
    <div class="actions">
     <?php if (in_array($scannedRequest['state'],ANEMIA_REQUEST_ACTIONS['attended'][1],true)): ?>
      <form method="post">
       <input type="hidden" name="csrf" value="<?= esc($token) ?>"><input type="hidden" name="receipt" value="<?= esc($scannedRequest['receipt']) ?>">
       <button class="action primary" name="scan_action" value="check_in"><?= workspaceIcon('ready') ?>ยืนยันเข้ารับบริการ</button>
      </form>
     <?php endif; ?>
     <a class="action" href="dashboard.php?view=contact-queue<?= $unitScanner?'&amp;app=staff':'' ?><?= $demoScan?'&amp;demo=1':'' ?>&amp;request=<?= esc($scannedRequest['receipt']) ?>"><?= workspaceIcon('next') ?>เปิดไทม์ไลน์</a>
     <?php if ($unitScanner): ?><a class="action" href="dashboard.php?view=scan-request&amp;app=unit"><?= workspaceIcon('scan') ?>สแกนรายการถัดไป</a><?php endif; ?>
    </div>
   </section>
   <?php endif; ?>
   <?php if (!$unitScanner || !$scannedRequest): ?>
   <section class="card admin-report<?= $unitScanner?' staff-app-capture':'' ?>">
    <?php if (!$unitScanner): ?><div class="sample-section-heading"><span class="iconbox"><?= workspaceIcon('scan') ?></span><div><h2>ตรวจใบรับคำขอ</h2><p>สแกน QR จากหน้าจอหรือจากใบที่บันทึกไว้</p></div></div><?php endif; ?>
    <?php if ($scanMessage !== ''): ?><p class="status" role="alert"><?= esc($scanMessage) ?></p><?php endif; ?>
    <?php if ($unitScanner): ?><div class="staff-scan-frame"><span aria-hidden="true"></span><?php endif; ?>
    <video id="request-scan-video" muted playsinline hidden aria-label="ภาพจากกล้องสำหรับสแกน QR"></video>
    <?php if ($unitScanner): ?></div><?php endif; ?>
    <div class="actions request-scan-controls">
     <button type="button" id="request-scan-start" class="action"><?= workspaceIcon('scan') ?>เปิดกล้อง</button>
     <button type="button" id="request-scan-stop" class="action" hidden><?= workspaceIcon('close') ?>ปิดกล้อง</button>
     <label id="request-scan-drop" class="request-scan-drop">
      <?php if ($unitScanner): ?><?= workspaceIcon('scan') ?>เลือกภาพ QR<?php else: ?><span class="iconbox"><?= workspaceIcon('scan') ?></span><span><strong>ใส่ภาพใบรับคำขอ</strong><small>แตะเลือกไฟล์ ลากภาพมาวาง หรือวางภาพที่คัดลอกไว้</small></span><?php endif; ?>
      <input type="file" id="request-scan-file" accept="image/*" aria-label="เลือกภาพ QR">
     </label>
    </div>
    <form id="request-scanner-form" method="post" class="request-scanner-form">
     <input type="hidden" name="csrf" value="<?= esc($token) ?>">
     <label><?= $unitScanner?'เลขคำขอ':'เลขคำขอหรือลิงก์จากใบรับคำขอ' ?><input name="receipt" type="text" required maxlength="512" autocomplete="off" spellcheck="false" placeholder="เช่น AN-…"></label>
     <button type="submit" class="action primary"><?= workspaceIcon('ready') ?>ตรวจคำขอ</button>
    </form>
    <p id="request-scan-message" role="status"></p>
   </section>
   <?php endif; ?>
  </div>
  <?php elseif ($view === 'contact-queue'): ?>
  <p id="queue-navigation-status" role="status" hidden></p>
  <section class="card admin-report" id="queue-content">
  <?php if ($mobileQueue): ?>
  <?php $mobileQueueUrl='dashboard.php?view=contact-queue&app=staff'.($demoQueue?'&demo=1':''); ?>
  <h1 class="staff-queue-title"><?= $focusReceipt!==''?'รายละเอียดคำขอ':($queueTab==='history'?'ประวัติ':'งานของฉัน') ?></h1>
  <?php if ($focusReceipt==='' && $queueTab!=='history'): ?>
  <nav class="staff-queue-tabs" aria-label="ประเภทงาน">
   <?php foreach (['pending','today','follow'] as $tab): ?>
   <a href="<?= esc($mobileQueueUrl.'&tab='.$tab) ?>" <?= $queueTab===$tab?'aria-current="page"':'' ?>><?= esc($mobileFilters[$tab][0]) ?> <strong<?= $tab==='pending'&&!$demoQueue?' data-pending-count="live.total"':'' ?>><?= number_format((int)$mobileTotals[$tab]) ?></strong></a>
   <?php endforeach; ?>
  </nav>
  <?php endif; ?>
  <?php else: ?>
  <div class="queue-heading">
   <div class="queue-heading-bar">
    <nav class="queue-tabs" aria-label="ประเภทคิว">
     <a href="dashboard.php?view=contact-queue" <?= !$demoQueue?'aria-current="page"':'' ?>><?= workspaceIcon('phone') ?>คิวจริง</a>
     <a href="dashboard.php?view=contact-queue&amp;demo=1" <?= $demoQueue?'aria-current="page"':'' ?>><?= workspaceIcon('chart') ?>คิวทดสอบ</a>
    </nav>
   </div>
   <div class="queue-heading-bar">
    <h2><?= $demoQueue?'คำขอที่บันทึกในโหมดทดสอบ':'คำขอที่ยืนยันเบอร์แล้ว' ?></h2>
    <a class="queue-attention" <?= $attentionOnly?'aria-current="page"':'' ?> href="dashboard.php?view=contact-queue<?= $demoQueue?'&amp;demo=1':'' ?><?= $attentionOnly?'':'&amp;attention=1' ?>"><?= workspaceIcon('history') ?><?= $attentionOnly?'ดูคำขอทั้งหมด':'งานที่ต้องติดตาม' ?></a>
   </div>
  <?php
   $desktopQueueUrl='dashboard.php?'.http_build_query(['view'=>'contact-queue']
       + ($demoQueue?['demo'=>1]:[]) + ($attentionOnly?['attention'=>1]:[]));
   $stateTabLabels=[''=>'ทั้งหมด','pending'=>'รอรับเรื่อง','accepted'=>'รับเรื่องแล้ว',
       'contacted'=>'ติดต่อแล้ว','retry_contact'=>'รอติดต่อซ้ำ','scheduled'=>'ยืนยันนัด',
       'attended'=>'มารับบริการ','test_completed'=>'ตรวจแล้ว','follow_up'=>'ติดตามผล','closed'=>'ปิดคำขอ'];
   $stateIcons=[''=>'filter','pending'=>'bell','accepted'=>'ready','contacted'=>'phone',
       'retry_contact'=>'history','scheduled'=>'calendar','attended'=>'person','test_completed'=>'ready',
       'follow_up'=>'message','closed'=>'lock'];
  ?>
  <nav class="queue-tabs queue-status-tabs" aria-label="กรองคำขอตามสถานะ">
   <?php foreach ([''=>'ทุกสถานะ'] + ANEMIA_REQUEST_STATES as $state=>$label): ?>
    <a title="<?= esc($label) ?>" href="<?= esc($desktopQueueUrl.($state!==''?'&status='.rawurlencode($state):'')) ?>"
       <?= $queueState===$state && $queueKind===''?'aria-current="page"':'' ?>><?= workspaceIcon($stateIcons[$state] ?? 'info') ?><?= esc($stateTabLabels[$state] ?? $label) ?>
      <span class="queue-status-count"><?= number_format((int)($state===''?array_sum($queueStateTotals):($queueStateTotals[$state]??0))) ?></span>
    </a>
   <?php endforeach; ?>
  </nav>
  </div>
  <?php $pendingInQueue = $demoQueue ? $pendingDemoCounts : $pendingContactCounts; $counterScope = $demoQueue ? 'demo' : 'live'; ?>
  <div class="contact-pending-summary" aria-live="polite" aria-atomic="true">
   <?php foreach (['appointment'=>['calendar','ขอนัดรอประสาน'],'callback'=>['phone','ขอติดต่อกลับรอประสาน']] as $kind=>[$icon,$label]): ?>
    <a href="<?= esc('dashboard.php?view=contact-queue'.($demoQueue?'&demo=1':'').'&status=pending&kind='.$kind) ?>"
       <?= $queueKind===$kind && !$attentionOnly && $focusReceipt===''?'aria-current="page"':'' ?>>
     <?= workspaceIcon($icon) ?><?= esc($label) ?> <strong data-pending-count="<?= $counterScope ?>.<?= $kind ?>"><?= $pendingInQueue[$kind] ?></strong>
    </a>
   <?php endforeach; ?>
  </div>
  <p><?= $demoQueue?'แสดงข้อมูลติดต่อที่กรอกในโหมดทดสอบ คิวนี้แยกจากคิวจริงและไม่มีการส่ง SMS':'ผู้ดูแลประสานกับหน่วยบริการที่ผู้ขอเลือก' ?></p>
  <details data-queue-referral-summary>
   <summary>สรุปการส่งต่อในคิวนี้</summary>
   <p>นับจากคำขอทั้งหมดตามสิทธิ์เข้าถึง ยอดผลตรวจนับเฉพาะรายการที่บันทึกผลแล้ว</p>
   <dl>
   <?php foreach (['requested'=>'คำขอทั้งหมด','attended'=>'เข้ารับบริการแล้ว','recorded'=>'บันทึกผลตรวจแล้ว'] + ANEMIA_CONFIRMATION_RESULTS as $key=>$label): ?>
    <dt><?= esc($label) ?></dt><dd><?= number_format((int)($referralStats[$key] ?? 0)) ?> รายการ</dd>
   <?php endforeach; ?>
   </dl>
  </details>
  <?php endif; ?>
  <?php if (!$mobileQueue): ?><div class="queue-list-viewport"><?php endif; ?>
  <?php if (!$contactQueue): ?><p class="queue-empty">ไม่พบคำขอในหน้านี้</p><?php else: ?>
  <?php if ($mobileQueue): ?>
  <div class="staff-queue-list">
   <?php foreach ($contactQueue as $item): ?>
   <button type="button" class="action queue-timeline-trigger staff-queue-item" data-queue-timeline="<?= esc($item['receipt']) ?>" <?= $focusReceipt!==''?'data-queue-open':'' ?> aria-haspopup="dialog"><strong><?= esc($item['receipt']) ?></strong><small><?= $item['request_kind']==='appointment'?'ขอนัดเข้าตรวจ':'ขอให้ติดต่อกลับ' ?><?= $queueTab==='today'&&$item['appointment_slot']?' · '.esc(ANEMIA_APPOINTMENT_SLOTS[$item['appointment_slot']] ?? ''):'' ?></small><span><?= esc(ANEMIA_REQUEST_STATES[$item['state']] ?? 'ดูสถานะ') ?></span></button>
   <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="tablewrap"><table class="admin-table contact-queue-table"><thead><tr><th>เลขคำขอ / เวลาส่ง</th><th>หน่วยบริการ / ความประสงค์</th><th>ข้อมูลติดต่อ</th><th>สถานะ</th></tr></thead><tbody>
  <?php foreach ($contactQueue as $item): ?><tr class="queue-clickable-row">
   <th scope="row"><?= esc($item['receipt']) ?><br><small><time datetime="<?= esc(str_replace(' ','T',$item['submitted_at']).'+07:00') ?>" title="<?= esc('ส่งเวลา '.substr($item['submitted_at'],11,5).' น.') ?>"><?= esc(workspaceThaiShortDate($item['submitted_at'])) ?></time></small></th>
   <td><?= esc($item['facility']) ?><br><small><?= $item['request_kind']==='appointment'?'ขอนัดเข้าตรวจ':'ให้ติดต่อกลับ' ?><?= $item['preferred_date']?' · '.esc(workspaceThaiShortDate($item['preferred_date'])):'' ?></small></td>
   <td><?= esc($item['contact_name']) ?><br><a href="tel:<?= esc($item['phone']) ?>"><?= esc($item['phone']) ?></a></td>
   <td><button type="button" class="action queue-timeline-trigger" data-queue-timeline="<?= esc($item['receipt']) ?>" <?= $focusReceipt!==''?'data-queue-open':'' ?> aria-haspopup="dialog"><?= workspaceIcon('history') ?><span><?= $item['overdue']?'เลยวันนัด · รอตรวจสอบ':esc(ANEMIA_REQUEST_STATES[$item['state']] ?? 'ดูสถานะ') ?></span><?= workspaceIcon('next') ?></button></td>
  </tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
  <?php endif; ?>
  <?php if (!$mobileQueue): ?></div><?php endif; ?>
  <?php if ($contactQueue): ?>
  <dialog class="signal-dialog workspace-dialog queue-timeline-dialog" id="queue-timeline" aria-labelledby="queue-timeline-title">
   <header class="workspace-dialog-heading queue-timeline-current" data-timeline-current>
    <span class="queue-current-icon" aria-hidden="true"><?= workspaceIcon('history') ?></span>
    <div><h2 id="queue-timeline-title" data-current-label aria-live="polite">กำลังโหลดสถานะ…</h2><small data-timeline-receipt></small><span data-current-detail hidden></span></div>
    <form method="dialog"><button class="action" aria-label="ปิด"><?= workspaceIcon('close') ?></button></form>
   </header>
   <?php if ($mobileQueue): ?><p data-timeline-contact hidden><span></span> <a></a></p><?php endif; ?>
   <details class="queue-timeline-history" hidden>
    <summary>ดูประวัติและขั้นถัดไป</summary>
    <ol class="queue-timeline-events"></ol>
   </details>
   <p data-timeline-message role="status"></p>
   <?php if ($isAdmin): ?>
    <button type="button" class="action" data-delete-queue><?= workspaceIcon('close') ?>ลบคำขอ</button>
    <dialog class="signal-dialog workspace-dialog" data-delete-dialog aria-labelledby="queue-delete-title">
     <h2 id="queue-delete-title" data-delete-title>ยืนยันการลบคำขอ</h2>
     <p><strong lang="en" data-delete-receipt></strong></p>
     <p data-delete-message role="status"></p>
     <form method="dialog" class="actions">
      <button class="action" data-delete-close>ยกเลิก</button>
      <button type="button" class="action primary" data-delete-confirm><?= workspaceIcon('close') ?>ยืนยันลบ</button>
     </form>
    </dialog>
   <?php endif; ?>
   <form method="post" id="queue-workflow-form" class="contact-queue-state" hidden>
    <input type="hidden" name="csrf" value="<?= esc($token) ?>"><input type="hidden" name="receipt"><input type="hidden" name="previous">
    <select name="state" aria-label="เปลี่ยนสถานะคำขอ">
     <option value="">เลือกงานที่จะทำ</option>
     <?php foreach (ANEMIA_REQUEST_STATES as $key=>$label): ?>
      <option value="<?= $key ?>"><?= esc($label) ?></option>
     <?php endforeach; ?>
    </select><button type="submit" class="action"><?= workspaceIcon('save') ?>บันทึก</button>
   </form>
   <fieldset data-confirmation-fields hidden><legend>ผลตรวจจากหน่วยบริการ</legend>
    <label>ผลตรวจ <select form="queue-workflow-form" name="confirmation_result" disabled><option value="">เลือกผลตรวจ</option>
     <?php foreach (ANEMIA_CONFIRMATION_RESULTS as $code=>$label): ?><option value="<?= esc($code) ?>"><?= esc($label) ?></option><?php endforeach; ?>
    </select></label>
    <label>วันที่ตรวจ <input form="queue-workflow-form" type="date" name="examined_on" max="<?= esc(date('Y-m-d')) ?>" disabled></label>
   </fieldset>
   <section data-workflow-round hidden>
    <details class="request-calendar"><summary><?= workspaceIcon('calendar') ?> <span data-round-date-label>เลือกวันนัด</span></summary><div data-round-calendar></div></details>
    <input form="queue-workflow-form" name="appointment_day" type="hidden">
    <fieldset data-round-slots><legend>เลือกรอบเข้าตรวจ</legend>
     <?php foreach (ANEMIA_APPOINTMENT_SLOTS as $slot=>$label): ?>
      <label><input form="queue-workflow-form" type="radio" name="appointment_slot" value="<?= esc($slot) ?>"> <?= esc($label) ?> <small data-round-count="<?= esc($slot) ?>">จำนวน – คน</small></label>
     <?php endforeach; ?>
    </fieldset>
    <fieldset data-callback-times hidden data-closed-days="<?= esc(implode(',',$callbackClosedDays)) ?>"><legend>เวลาโทรกลับ</legend>
     <?php foreach ($callbackTimes as $time): ?>
      <label><input form="queue-workflow-form" name="callback_time" type="radio" value="<?= esc($time) ?>"> <?= esc($time) ?> น.</label>
     <?php endforeach; ?>
    </fieldset>
    <fieldset data-contact-time hidden><legend>เวลาติดตาม (เวลาไทย)</legend>
     <?php foreach (['hour'=>['ชั่วโมง',23],'minute'=>['นาที',59]] as $part=>[$label,$maximum]): ?>
      <label><?= $label ?><select form="queue-workflow-form" name="contact_<?= $part ?>"><option value="">เลือก<?= $label ?></option>
       <?php for ($number=0;$number<=$maximum;$number++): $value=sprintf('%02d',$number); ?><option value="<?= $value ?>"><?= $value ?></option><?php endfor; ?>
      </select></label>
     <?php endforeach; ?>
    </fieldset>
    <p role="status" data-round-message></p>
   </section>
  </dialog>
  <?php endif; ?>
  <?php if ($focusReceipt==='' && (!$mobileQueue || $queuePage>1 || $queueNext)): ?>
   <?php $pageUrl=($mobileQueue?$mobileQueueUrl.'&tab='.$queueTab:$desktopQueueUrl.($queueState!==''?'&status='.rawurlencode($queueState):'').($queueKind!==''?'&kind='.$queueKind:'')).'&page='; ?>
   <nav data-queue-pager class="<?= $mobileQueue?'staff-queue-pager':'actions queue-status-filters' ?>" aria-label="เปลี่ยนหน้าคำขอ">
    <?php if ($queuePage>1): ?><a class="action" href="<?= esc($pageUrl.($queuePage-1)) ?>">ก่อนหน้า</a><?php endif; ?>
    <span>หน้า <?= number_format($queuePage) ?></span>
    <?php if ($queueNext): ?><a class="action" href="<?= esc($pageUrl.($queuePage+1)) ?>">ถัดไป</a><?php endif; ?>
   </nav>
  <?php endif; ?>
  </section>
  <script defer src="queue-timeline.js?v=21"></script>
  <?php elseif ($view === 'services'):
   require_once __DIR__.'/_private/service-settings.php';
   require __DIR__.'/_private/service-view.php';
  ?>
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
  <?php if ($staffWorkDock): ?>
  <nav class="staff-app-dock staff-app-work-dock" aria-label="เมนูแอปเจ้าหน้าที่">
   <a href="dashboard.php?view=contact-queue&amp;app=staff" <?= $mobileQueue&&$queueTab!=='history'?'aria-current="page"':'' ?>><?= workspaceIcon('ready') ?>งานของฉัน</a>
   <a class="staff-dock-scan" href="dashboard.php?view=scan-request&amp;app=unit" <?= $unitScanner?'aria-current="page"':'' ?>><span class="staff-dock-scan-icon"><?= workspaceIcon('scan') ?></span><span>สแกน QR</span></a>
   <a href="dashboard.php?view=contact-queue&amp;app=staff&amp;tab=history" <?= $mobileQueue&&$queueTab==='history'?'aria-current="page"':'' ?>><?= workspaceIcon('history') ?>ประวัติ</a>
   <button type="button" data-workspace-dialog="account" aria-haspopup="dialog"><?= workspaceIcon('users') ?>บัญชี</button>
  </nav>
  <?php endif; ?>
<?php if (!$staffAppView) require __DIR__ . '/_private/staff-footer.php'; ?>
<dialog id="workspace-account" class="workspace-dialog no-print" aria-labelledby="workspace-account-title">
 <header class="workspace-dialog-heading">
  <h2 id="workspace-account-title"><?= workspaceIcon('users') ?> <?= $staffWorkDock?'บัญชีเจ้าหน้าที่':'เปลี่ยนรหัสผ่าน' ?></h2>
  <form method="dialog"><button class="action"><?= workspaceIcon('close') ?>ปิด</button></form>
 </header>
 <p>บัญชี <?= esc($staff) ?> · <?= esc($roleLabel) ?></p>
 <?php if ($staffWorkDock): ?>
 <div class="actions staff-app-account-actions">
  <a class="action" href="dashboard.php?view=overview"><?= workspaceIcon('chart') ?>เปิดแดชบอร์ด</a>
  <form method="post" action="<?= $unitScanner?'login?next=unit-scan':'login?next=mobile' ?>"><input type="hidden" name="csrf" value="<?= esc($token) ?>"><button class="action" name="action" value="logout"><?= workspaceIcon('logout') ?>ออกจากระบบ</button></form>
 </div>
 <?php endif; ?>
 <form id="account-password-form" method="post">
  <input type="hidden" name="csrf" value="<?= esc($token) ?>">
  <input type="hidden" name="account_action" value="change_password">
  <input type="text" name="username" value="<?= esc($staff) ?>" autocomplete="username" hidden readonly>
  <label>รหัสผ่านปัจจุบัน<input type="password" name="current_password" autocomplete="current-password" maxlength="128" required></label>
  <label>รหัสผ่านใหม่ <small>อย่างน้อย 12 ตัวอักษร</small><input type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="128" required></label>
  <label>ยืนยันรหัสผ่านใหม่<input type="password" name="confirm_password" autocomplete="new-password" minlength="12" maxlength="128" required></label>
  <p role="status" data-password-message></p>
  <button type="submit" class="action primary"><?= workspaceIcon('save') ?>บันทึกรหัสผ่าน</button>
 </form>
</dialog>
<?php foreach (['about','team'] as $infoPage): ?>
<dialog id="workspace-<?= esc($infoPage) ?>" class="workspace-dialog no-print" aria-labelledby="workspace-title-<?= esc($infoPage) ?>">
 <header class="workspace-dialog-heading">
  <h2 id="workspace-title-<?= esc($infoPage) ?>"><?= esc($tabs[$infoPage]) ?></h2>
  <form method="dialog"><button class="action" autofocus><?= workspaceIcon('close') ?>ปิด</button></form>
 </header>
 <?php workspaceInfo($infoPage, $localRelease); ?>
</dialog>
<?php endforeach; ?>
<?php if (!$staffAppView): ?>
<script>
for (const menu of document.querySelectorAll('.admin-top-tabs .hosted-menu-group')) {
 const summary = menu.querySelector('summary');
 const links = menu.querySelector('.hosted-menu-links');
 const motion = matchMedia('(prefers-reduced-motion: reduce)');
 let closeTimer;
 let mouseLeft = false;
 let animations = [];
 let fadingOut = false;

 function cancelAnimations() {
  animations.forEach(animation => animation.cancel());
  animations = [];
  fadingOut = false;
 }

 function fadeItems(opening) {
  fadingOut = !opening;
  const items = [...links.children].filter(item => !item.hidden);
  animations = items.map((item, index) => item.animate(
   opening ? [{opacity: 0}, {opacity: 1}] : [{opacity: 1}, {opacity: 0}],
   {duration: 150, delay: (opening ? index : items.length - 1 - index) * 42, easing: 'ease-out', fill: 'both'}
  ));
  const rolled = {clipPath: 'inset(0 0 100% 0 round .7rem)'};
  const unrolled = {clipPath: 'inset(0 0 0 0 round .7rem)'};
  animations.push(links.animate(
   opening ? [rolled, unrolled] : [unrolled, rolled],
   {duration: 190 + Math.max(0, items.length - 1) * 42, easing: opening ? 'cubic-bezier(.22,.72,.25,1)' : 'ease-in-out', fill: 'both'}
  ));
  return Promise.all(animations.map(animation => animation.finished));
 }

 function scheduleClose() {
  clearTimeout(closeTimer);
  closeTimer = setTimeout(async () => {
   if (!menu.open || !mouseLeft || links.contains(document.activeElement)) return;
   cancelAnimations();
   if (!motion.matches && typeof links.animate === 'function') {
    try { await fadeItems(false); }
    catch { return; }
   }
   menu.open = false;
   cancelAnimations();
  }, 300);
 }

 menu.addEventListener('pointerenter', event => {
  if (event.pointerType !== 'mouse') return;
  mouseLeft = false;
  clearTimeout(closeTimer);
  if (menu.open) {
   if (fadingOut) cancelAnimations();
   return;
  }
  menu.open = true;
  if (!motion.matches && typeof links.animate === 'function') {
   fadeItems(true).then(cancelAnimations, () => {});
  }
 });
 menu.addEventListener('pointerleave', event => {
  if (event.pointerType !== 'mouse') return;
  mouseLeft = true;
  if (menu.open) scheduleClose();
 });
 links.addEventListener('focusin', cancelAnimations);
 links.addEventListener('focusout', () => {
  if (menu.open && mouseLeft) scheduleClose();
 });
 menu.addEventListener('toggle', () => {
  if (!menu.open) {
   clearTimeout(closeTimer);
   cancelAnimations();
  }
 });
 menu.addEventListener('keydown', event => {
  if (event.key !== 'Escape' || !menu.open) return;
  event.preventDefault();
  menu.open = false;
  summary.focus();
 });
}
</script>
<?php endif; ?>
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
<script>
(() => {
 const form=document.querySelector('#account-password-form');
 const message=form.querySelector('[data-password-message]');
 const submit=form.querySelector('button[type=submit]');
 form.addEventListener('submit',async event=>{
  event.preventDefault();
  if(submit.disabled)return;
  submit.disabled=true;
  message.textContent='กำลังบันทึก';
  try{
   const response=await fetch('dashboard.php',{method:'POST',body:new FormData(form),cache:'no-store'});
   if(response.redirected)throw new Error('กรุณาเข้าสู่ระบบใหม่');
   const result=await response.json();
   message.textContent=result.message;
   if(response.ok)form.reset();
  }catch(error){
   message.textContent=error instanceof SyntaxError?'บันทึกไม่ได้ กรุณาลองอีกครั้ง':error.message;
  }finally{
   submit.disabled=false;
  }
 });
 form.closest('dialog').addEventListener('close',()=>{
  form.reset();
  message.textContent='';
 });
})();
</script>
<div id="report-loading" class="report-loading-overlay no-print" hidden>
 <div class="report-loading-card" role="status"><span class="report-spinner" aria-hidden="true"></span><div><strong>กำลังโหลดรายงาน</strong><span class="report-loading-detail">กำลังเตรียมข้อมูลตามตัวกรอง</span></div></div>
</div>
<script>
(() => {
 const overlay = document.getElementById('report-loading');
 const content = document.getElementById('staff-report-content');
 const reports = new Set(['overview','reports','activity','evaluation','programme-report','sample']);
 let timer;
 const start = () => {
  if (timer !== undefined) return;
  timer = setTimeout(() => {
   content.setAttribute('aria-busy','true');
   overlay.hidden = false;
  },140);
 };
 addEventListener('pageshow', () => {
  clearTimeout(timer);
  timer = undefined;
  overlay.hidden = true;
  content.removeAttribute('aria-busy');
 });
 document.addEventListener('submit', event => {
  if (!event.defaultPrevented && event.target.matches('form[method=get]') &&
      reports.has(event.target.elements.view?.value)) start();
 });
 document.addEventListener('click', event => {
  const link = event.target.closest('a[href]');
  if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey || link.target || link.hasAttribute('download')) return;
  const url = new URL(link.href);
  if (url.origin !== location.origin || url.pathname !== location.pathname || url.searchParams.has('download')) return;
  if (url.href !== location.href && (url.pathname + url.search !== location.pathname + location.search) &&
      reports.has(url.searchParams.get('view') || 'overview')) start();
 });
})();
</script>
<?php if ($canQueue): ?>
<script>
(() => {
 if(!document.querySelector('[data-pending-count]'))return;
 let running=false;
 let queued=false;
 async function refreshPendingContacts(force=false){
  if(document.hidden)return;
  if(running){if(force)queued=true;return;}
  running=true;
  const controller=new AbortController();
  const timeout=setTimeout(()=>controller.abort(),8000);
  try{
   const response=await fetch('dashboard.php?view=contact-queue&counts=1',{cache:'no-store',signal:controller.signal});
   if(!response.ok||!response.headers.get('Content-Type')?.includes('application/json'))return;
   const counts=await response.json();
   document.querySelectorAll('[data-pending-count]').forEach(node=>{
    const [scope,kind]=node.dataset.pendingCount.split('.');
    const value=kind==='total' ? counts[scope]?.appointment+counts[scope]?.callback : counts[scope]?.[kind];
    if(!Number.isInteger(value)||value<0)return;
    const badge=node.classList.contains('contact-pending-badge');
    const label=badge&&value>99?'99+':value.toLocaleString('th-TH');
    if(node.textContent!==label)node.textContent=label;
    if(badge){
     node.hidden=value===0;
     if(node.closest('[data-staff-bell]'))node.closest('[data-staff-bell]').setAttribute('aria-label','คำขอใหม่ '+value+' รายการ');
     else node.setAttribute('aria-label','คำขอนัดรอประสาน '+value+' รายการ');
    }
   });
  }catch{}finally{
   clearTimeout(timeout);
   running=false;
   if(queued){queued=false;refreshPendingContacts();}
  }
 }
 setInterval(refreshPendingContacts,5000);
 document.addEventListener('anemia:queue-changed',()=>refreshPendingContacts(true));
 document.addEventListener('visibilitychange',()=>refreshPendingContacts());
 window.addEventListener('online',()=>refreshPendingContacts());
 window.addEventListener('pageshow',event=>{if(event.persisted)refreshPendingContacts();});
 refreshPendingContacts();
})();
</script>
<?php endif; ?>
</body></html>
