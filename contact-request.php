<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function requestReply(int $status,array $body): never
{
    http_response_code($status);
    echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    exit;
}

$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (!$secure) requestReply(403,['error'=>'กรุณาเปิดหน้านี้ผ่าน HTTPS']);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
    ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin') !== 'same-origin' ||
    strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    requestReply(403,['error'=>'คำขอไม่ถูกต้อง']);
}
$apiKey=getenv('ANEMIA_VERIFY_KEY') ?: '';
$secret=getenv('ANEMIA_VERIFY_SECRET') ?: '';
$service=getenv('ANEMIA_VERIFY_SERVICE') ?: '';
if ($apiKey==='' && $secret==='' && $service==='') {
    requestReply(503,['demoAvailable'=>true,'error'=>'ยังไม่ได้ตั้งค่า SMS']);
}
if (!preg_match('/^SK[0-9a-f]{32}$/iD',$apiKey) || !$secret || !preg_match('/^VA[0-9a-f]{32}$/iD',$service) || !function_exists('curl_init')) {
    requestReply(503,['error'=>'ยังไม่เปิดบริการยืนยันเบอร์โทร กรุณาติดต่อหน่วยบริการโดยตรง']);
}
$data=json_decode((string)file_get_contents('php://input',false,null,0,4097),true,6);
if (!is_array($data)) requestReply(400,['error'=>'คำขอไม่ถูกต้อง']);

function verifyProvider(string $service,string $key,string $secret,bool $check,array $fields): array
{
    $client=curl_init('https://verify.twilio.com/v2/Services/'.$service.'/'.($check?'VerificationCheck':'Verifications'));
    curl_setopt_array($client,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>http_build_query($fields),CURLOPT_USERPWD=>$key.':'.$secret,
        CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>12]);
    $response=curl_exec($client);
    $status=curl_getinfo($client,CURLINFO_RESPONSE_CODE);
    unset($client);
    if ($status===429) requestReply(429,['error'=>'ลองหลายครั้งเกินไป กรุณารอสักครู่']);
    if ($check && in_array($status,[400,404],true)) requestReply(422,['error'=>'รหัสไม่ตรงหรือหมดอายุ กรุณาลองอีกครั้งหรือขอรหัสใหม่']);
    if ($status<200 || $status>=300 || !is_string($response)) throw new RuntimeException('Verify API failed');
    $decoded=json_decode($response,true,8,JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Verify API invalid response');
    return $decoded;
}

session_name('anemia_contact_request');
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Strict','path'=>'/']);
session_start();
date_default_timezone_set('Asia/Bangkok');
define('ANEMIA_STAFF_BOOT',true);
try {
    $config=require __DIR__.'/_private/config.php';
    require __DIR__.'/_private/database.php';
    $db=anemiaDatabase($config);
    initializeAnemia($db,$config);
    $action=$data['action']??null;
    if ($action==='start') {
        $prefix=$data['prefix']??null; $firstName=$data['firstName']??null; $lastName=$data['lastName']??null;
        $phone=$data['phone']??null; $kind=$data['kind']??null;
        $index=$data['facility']??null; $date=$data['date']??'';
        if (!in_array($prefix,['น.ส.','นาง'],true) ||
            !is_string($firstName) || !preg_match('/^.{1,35}$/uD',trim($firstName)) ||
            !is_string($lastName) || !preg_match('/^.{1,35}$/uD',trim($lastName)) ||
            !is_string($phone) || !preg_match('/^0[0-9]{9}$/D',$phone) ||
            !in_array($kind,['appointment','callback'],true) || !is_int($index) || $index<0 ||
            ($data['consent']??null)!==true || !is_string($date)) requestReply(422,['error'=>'กรุณาตรวจชื่อ เบอร์โทร และความประสงค์อีกครั้ง']);
        $name=$prefix.' '.trim($firstName).' '.trim($lastName);
        if ($kind==='callback') $date='';
        if ($date!=='') {
            $day=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if (!$day || $day->format('Y-m-d')!==$date || $date<date('Y-m-d') || $date>date('Y-m-d',strtotime('+6 months'))) {
                requestReply(422,['error'=>'กรุณาเลือกวันที่สะดวกภายใน 6 เดือนข้างหน้า']);
            }
        }
        $source=file_get_contents(__DIR__.'/facility-directory.js');
        if (!is_string($source) || !preg_match('/^const hospitalDirectory=(\[.*\]);\s*$/ms',$source,$match)) throw new RuntimeException('Facility directory unavailable');
        $facility=json_decode($match[1],true,8,JSON_THROW_ON_ERROR)[$index]??null;
        if (!$facility) requestReply(422,['error'=>'ไม่พบหน่วยบริการที่เลือก']);
        if (time()-(int)($_SESSION['last_request_otp']??0)<60) requestReply(429,['error'=>'กรุณารอ 1 นาทีก่อนขอรหัสอีกครั้ง']);
        $window=intdiv(time(),3600);
        $increment=$db->prepare('INSERT INTO anemia_otp_throttle (bucket,window_slot,attempts) VALUES (?,?,1) ON DUPLICATE KEY UPDATE attempts=LEAST(attempts+1,10000)');
        $count=$db->prepare('SELECT attempts FROM anemia_otp_throttle WHERE bucket=? AND window_slot=?');
        foreach (['phone:'.$phone=>4,'ip:'.($_SERVER['REMOTE_ADDR']??'')=>18] as $value=>$limit) {
            $bucket=hash_hmac('sha256',$value,$secret);
            $increment->execute([$bucket,$window]); $count->execute([$bucket,$window]);
            if ((int)$count->fetchColumn()>$limit) requestReply(429,['error'=>'ขอรหัสหลายครั้งเกินไป กรุณาลองใหม่ภายหลัง']);
        }
        if (random_int(1,100)===1) {
            $cleanup=$db->prepare('DELETE FROM anemia_otp_throttle WHERE window_slot<? LIMIT 1000');
            $cleanup->execute([$window-48]);
        }
        $_SESSION['last_request_otp']=time();
        unset($_SESSION['pending_contact_request']);
        $number='+66'.substr($phone,1);
        $sent=verifyProvider($service,$apiKey,$secret,false,['To'=>$number,'Channel'=>'sms']);
        if (($sent['status']??null)!=='pending' || !preg_match('/^VE[0-9a-f]{32}$/iD',$sent['sid']??'')) throw new RuntimeException('Verification did not start');
        $_SESSION['pending_contact_request']=['sid'=>$sent['sid'],'number'=>$number,'name'=>trim($name),'kind'=>$kind,'facility'=>$facility[0],
            'date'=>$date?:null,'until'=>time()+600,'attempts'=>0,'token'=>bin2hex(random_bytes(16))];
        requestReply(200,['token'=>$_SESSION['pending_contact_request']['token'],'last4'=>substr($phone,-4)]);
    }
    if ($action!=='confirm') requestReply(400,['error'=>'คำขอไม่ถูกต้อง']);
    $pending=$_SESSION['pending_contact_request']??null;
    $completed=$_SESSION['completed_contact_request']??null;
    $code=$data['code']??null;
    $token=$data['token']??null;
    if (!is_string($code) || !preg_match('/^[0-9]{6}$/D',$code) || !is_string($token)) requestReply(422,['error'=>'กรุณากรอกรหัส 6 หลัก']);
    if ($completed && $completed['until']>=time() && hash_equals($completed['token'],$token)) requestReply(200,['receipt'=>$completed['receipt']]);
    if (!$pending || $pending['until']<time() || !hash_equals($pending['token'],$token) || (empty($pending['approved']) && $pending['attempts']>=5)) {
        requestReply(422,['error'=>'คำขอรหัสหมดอายุหรือยืนยันเกินจำนวนครั้ง กรุณาขอรหัสใหม่']);
    }
    if (empty($pending['approved'])) {
        $_SESSION['pending_contact_request']['attempts']++;
        $checked=verifyProvider($service,$apiKey,$secret,true,['VerificationSid'=>$pending['sid'],'Code'=>$code]);
        if (($checked['status']??null)!=='approved' || ($checked['to']??null)!==$pending['number']) requestReply(422,['error'=>'รหัสไม่ถูกต้อง กรุณาตรวจอีกครั้ง']);
        $_SESSION['pending_contact_request']['approved']=true;
    }
    $receipt='AN-'.strtoupper(substr(hash('sha256',$pending['sid']),0,16));
    $save=$db->prepare('INSERT INTO anemia_contact_queue (receipt,facility,request_kind,contact_name,phone,preferred_date,submitted_at) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE receipt=receipt');
    $save->execute([$receipt,$pending['facility'],$pending['kind'],$pending['name'],$pending['number'],$pending['date'],date('Y-m-d H:i:s')]);
    unset($_SESSION['pending_contact_request']);
    $_SESSION['completed_contact_request']=['receipt'=>$receipt,'token'=>$token,'until'=>time()+600];
    requestReply(200,['receipt'=>$receipt]);
} catch (Throwable $error) {
    error_log('Anemia contact request failed ('.get_class($error).')');
    requestReply(503,['error'=>'ยังดำเนินการไม่ได้ กรุณาลองอีกครั้ง']);
}
