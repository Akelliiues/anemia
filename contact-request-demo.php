<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function demoContactReply(int $status,array $body): never
{
    http_response_code($status);
    echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    exit;
}

$secure=!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
if ((!$secure && PHP_SAPI!=='cli-server') || ($_SERVER['REQUEST_METHOD']??'')!=='POST' ||
    ($_SERVER['HTTP_SEC_FETCH_SITE']??'same-origin')!=='same-origin' ||
    strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json') {
    demoContactReply(403,['error'=>'คำขอทดสอบไม่ถูกต้อง']);
}
$publicDemo=($_GET['public']??'')==='1';
if ($publicDemo && (getenv('ANEMIA_VERIFY_KEY') || getenv('ANEMIA_VERIFY_SECRET') || getenv('ANEMIA_VERIFY_SERVICE'))) {
    demoContactReply(403,['error'=>'โฮสต์นี้ใช้การยืนยันเบอร์ทาง SMS']);
}
ini_set('session.use_strict_mode','1');
session_name($publicDemo?'anemia_public_demo':'anemia_staff_session');
session_set_cookie_params(['secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);
session_start();
if (!$publicDemo && (empty($_SESSION['username']) || (int)($_SESSION['until']??0)<=time())) demoContactReply(403,['error'=>'กรุณาเข้าสู่ระบบผู้ดูแลก่อนทดสอบ']);
define('ANEMIA_STAFF_BOOT',true);
date_default_timezone_set('Asia/Bangkok');

try {
    $config=require __DIR__.'/_private/config.php';
    require __DIR__.'/_private/database.php';
    require __DIR__.'/_private/access.php';
    $db=anemiaDatabase($config);
    initializeAnemia($db,$config);
    $sampleEnabled=(int)$db->query("SELECT enabled FROM anemia_host_setting WHERE setting_key='staff_sample'")->fetchColumn()===1;
    if (!$sampleEnabled) demoContactReply(503,['error'=>'โฮสต์นี้ปิดโหมดทดสอบแล้ว']);
    if (!$publicDemo) {
        $identity=anemiaStaffIdentity($db,(string)$_SESSION['username']);
        if (($identity['role']??null)!=='account_admin') demoContactReply(403,['error'=>'บัญชีนี้ไม่มีสิทธิ์ทดสอบ OTP']);
    }
    $data=json_decode((string)file_get_contents('php://input',false,null,0,4097),true,6);
    if (!is_array($data)) demoContactReply(400,['error'=>'คำขอไม่ถูกต้อง']);
    if (($data['action']??null)==='start') {
        $index=$data['facility']??null;
        $kind=$data['kind']??null;
        $date=$data['date']??'';
        $prefix=$data['prefix']??null; $firstName=$data['firstName']??null; $lastName=$data['lastName']??null;
        $phone=$data['phone']??null;
        if (!is_int($index) || $index<0 || !in_array($kind,['appointment','callback'],true) ||
            ($data['consent']??null)!==true || !is_string($date) ||
            !in_array($prefix,['น.ส.','นาง'],true) || !is_string($firstName) || !preg_match('/^.{1,35}$/uD',trim($firstName)) ||
            !is_string($lastName) || !preg_match('/^.{1,35}$/uD',trim($lastName)) || !is_string($phone) || !preg_match('/^0[0-9]{9}$/D',$phone)) demoContactReply(422,['error'=>'กรุณาตรวจข้อมูลการทดสอบอีกครั้ง']);
        if ($kind==='callback') $date='';
        if ($date!=='') {
            $day=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if (!$day || $day->format('Y-m-d')!==$date || $date<date('Y-m-d') || $date>date('Y-m-d',strtotime('+6 months'))) {
                demoContactReply(422,['error'=>'กรุณาเลือกวันที่ภายใน 6 เดือนข้างหน้า']);
            }
        }
        $source=file_get_contents(__DIR__.'/facility-directory.js');
        if (!is_string($source) || !preg_match('/^const hospitalDirectory=(\[.*\]);\s*$/ms',$source,$match)) throw new RuntimeException('Facility directory unavailable');
        $facility=json_decode($match[1],true,8,JSON_THROW_ON_ERROR)[$index]??null;
        if (!$facility) demoContactReply(422,['error'=>'ไม่พบหน่วยบริการ']);
        if ($publicDemo) {
            if (time()-(int)($_SESSION['demo_otp_started']??0)<15) demoContactReply(429,['error'=>'กรุณารอ 15 วินาทีก่อนลองใหม่']);
            $bucket=hash('sha256','public-demo:'.($_SERVER['REMOTE_ADDR']??''));
            $window=intdiv(time(),3600);
            $limit=$db->prepare('INSERT INTO anemia_otp_throttle (bucket,window_slot,attempts) VALUES (?,?,1) ON DUPLICATE KEY UPDATE attempts=LEAST(attempts+1,10000)');
            $limit->execute([$bucket,$window]);
            $count=$db->prepare('SELECT attempts FROM anemia_otp_throttle WHERE bucket=? AND window_slot=?');
            $count->execute([$bucket,$window]);
            if ((int)$count->fetchColumn()>12) demoContactReply(429,['error'=>'ทดสอบหลายครั้งเกินไป กรุณาลองใหม่ภายหลัง']);
            $_SESSION['demo_otp_started']=time();
        }
        $token=bin2hex(random_bytes(16));
        $_SESSION['demo_otp']=['token'=>$token,'facility'=>$facility[0],'kind'=>$kind,'date'=>$date?:null,'contact'=>$prefix.' '.trim($firstName).' '.trim($lastName),'phone'=>$phone,'until'=>time()+600];
        demoContactReply(200,['token'=>$token,'last4'=>substr($phone,-4),'demoCode'=>'482731']);
    }
    if (($data['action']??null)!=='confirm') demoContactReply(400,['error'=>'คำขอไม่ถูกต้อง']);
    $pending=$_SESSION['demo_otp']??null;
    $token=$data['token']??null;
    if (!$pending || $pending['until']<time() || !is_string($token) || !hash_equals($pending['token'],$token)) {
        demoContactReply(422,['error'=>'คำขอทดสอบหมดอายุ กรุณาขอรหัสใหม่']);
    }
    if (($data['code']??'')!=='482731') demoContactReply(422,['error'=>'รหัสตัวอย่างไม่ตรง']);
    if (isset($pending['receipt'])) demoContactReply(200,['receipt'=>$pending['receipt']]);
    $db->beginTransaction();
    if ((int)$db->query("SELECT enabled FROM anemia_host_setting WHERE setting_key='staff_sample' FOR UPDATE")->fetchColumn()!==1) {
        $db->rollBack();
        demoContactReply(503,['error'=>'โฮสต์นี้ปิดโหมดทดสอบแล้ว']);
    }
    $receipt='DE-'.strtoupper(substr($token,0,16));
    $save=$db->prepare("INSERT INTO anemia_contact_demo_queue (receipt,facility,request_kind,contact_name,phone,preferred_date,submitted_at)
        VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE receipt=receipt");
    $save->execute([$receipt,$pending['facility'],$pending['kind'],$pending['contact'],$pending['phone'],$pending['date'],date('Y-m-d H:i:s')]);
    $db->commit();
    $_SESSION['demo_otp']=['token'=>$token,'receipt'=>$receipt,'until'=>time()+600];
    demoContactReply(200,['receipt'=>$receipt]);
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Anemia demo contact request failed ('.get_class($error).')');
    demoContactReply(503,['error'=>'ยังบันทึกข้อมูลทดสอบไม่ได้ กรุณาลองอีกครั้ง']);
}
