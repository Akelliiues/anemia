<?php
declare(strict_types=1);

if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

function anemiaServiceDefinitions(): array
{
    return [
        'otp'=>['OTP ทาง SMS','phone',[
            'ANEMIA_VERIFY_KEY'=>['API Key SID',false],
            'ANEMIA_VERIFY_SECRET'=>['API Key Secret',true],
            'ANEMIA_VERIFY_SERVICE'=>['Verify Service SID',false],
        ]],
        'line'=>['LINE Login','message',[
            'ANEMIA_LINE_CLIENT_ID'=>['Channel ID',false],
            'ANEMIA_LINE_CLIENT_SECRET'=>['Channel Secret',true],
        ]],
        'google'=>['Google','login',[
            'ANEMIA_GOOGLE_CLIENT_ID'=>['OAuth Client ID',false],
            'ANEMIA_GOOGLE_CLIENT_SECRET'=>['Client Secret',true],
        ]],
        'thaid'=>['ThaID','lock',[
            'THAID_REFERENCE'=>['เลขอ้างอิงการขอเชื่อมต่อ',false],
        ]],
    ];
}

function anemiaServiceKey(): ?string
{
    $key=base64_decode(getenv('ANEMIA_SERVICE_SETTINGS_KEY') ?: '',true);
    return function_exists('sodium_crypto_secretbox') && is_string($key) && strlen($key)===32 ? $key : null;
}

function anemiaServiceSettings(string $provider): array
{
    $path=__DIR__.'/service-settings-'.$provider.'.bin';
    if (!isset(anemiaServiceDefinitions()[$provider]) || !is_file($path)) return [];
    $key=anemiaServiceKey();
    $data=file_get_contents($path);
    if ($key===null || !is_string($data) || strlen($data)<40) return ['mode'=>'off','locked'=>true];
    $plain=sodium_crypto_secretbox_open(substr($data,24),substr($data,0,24),$key);
    $settings=$plain===false?null:json_decode($plain,true);
    return is_array($settings)?$settings:['mode'=>'off','locked'=>true];
}

function anemiaServiceValue(array $settings,string $key): string
{
    return $settings['values'][$key] ?? (getenv($key) ?: '');
}

function anemiaServiceLiveValue(array $settings,string $key): string
{
    return isset($settings['mode']) && $settings['mode']!=='live' ? '' : anemiaServiceValue($settings,$key);
}

function anemiaServiceCallback(): string
{
    return getenv('ANEMIA_ACCOUNT_CALLBACK') ?: 'https://anemia.ssotansum.com/participant-account.php';
}

function anemiaServiceReady(string $provider,array $settings): bool
{
    foreach (anemiaServiceDefinitions()[$provider][2] as $key=>$_) {
        if (anemiaServiceValue($settings,$key)==='') return false;
    }
    return match ($provider) {
        'otp'=>preg_match('/^SK[a-f0-9]{32}$/iD',anemiaServiceValue($settings,'ANEMIA_VERIFY_KEY')) &&
            preg_match('/^VA[a-f0-9]{32}$/iD',anemiaServiceValue($settings,'ANEMIA_VERIFY_SERVICE')),
        'line','google'=>filter_var(anemiaServiceCallback(),FILTER_VALIDATE_URL)!==false &&
            parse_url(anemiaServiceCallback(),PHP_URL_SCHEME)==='https',
        default=>true,
    };
}

function anemiaSaveService(string $provider,array $input,string $actor): void
{
    $definition=anemiaServiceDefinitions()[$provider]??null;
    $key=anemiaServiceKey();
    if (!$definition || $key===null) throw new DomainException('ยังบันทึกไม่ได้ กรุณาตรวจค่าการเข้ารหัสบนโฮสต์');
    $path=__DIR__.'/service-settings-'.$provider.'.bin';
    $lock=fopen($path.'.lock','c');
    if (!$lock) throw new DomainException('ไม่สามารถบันทึกค่าบริการบนโฮสต์ได้');
    try {
        if (!flock($lock,LOCK_EX)) throw new DomainException('กำลังบันทึกค่าบริการ กรุณาลองอีกครั้ง');
        $settings=anemiaServiceSettings($provider);
        if (!empty($settings['locked'])) throw new DomainException('อ่านค่าที่เข้ารหัสไว้ไม่ได้ กรุณาตรวจคีย์บนโฮสต์');
        $mode=$input['mode']??'';
        if (!in_array($mode,$provider==='thaid'?['off','pending']:['off','test','live'],true)) {
            throw new DomainException('กรุณาเลือกสถานะบริการ');
        }
        foreach ($definition[2] as $field=>$_) {
            $value=$input[$field]??'';
            if (!is_string($value) || strlen($value)>512 || preg_match('/[\x00-\x1f\x7f]/',$value)) {
                throw new DomainException('ค่าที่กรอกไม่ถูกต้อง');
            }
            if (trim($value)!=='') $settings['values'][$field]=trim($value);
        }
        if (in_array($mode,['test','live'],true) && !anemiaServiceReady($provider,$settings)) {
            throw new DomainException('กรุณาตรวจค่าที่จำเป็นก่อนเปิดบริการ');
        }
        $settings['mode']=$mode;
        $settings['updated_at']=date('c');
        $settings['updated_by']=$actor;
        $nonce=random_bytes(24);
        $temporary=tempnam(__DIR__,'service-settings-');
        if ($temporary===false) throw new DomainException('เขียนไฟล์ตั้งค่าบนโฮสต์ไม่ได้');
        try {
            chmod($temporary,0600);
            $encrypted=$nonce.sodium_crypto_secretbox(json_encode($settings,JSON_THROW_ON_ERROR),$nonce,$key);
            if (file_put_contents($temporary,$encrypted)!==strlen($encrypted) || !rename($temporary,$path)) {
                throw new DomainException('เขียนไฟล์ตั้งค่าบนโฮสต์ไม่ได้');
            }
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
    } finally {
        fclose($lock);
    }
}
function anemiaTestServiceOtp(string $action,array $input): string
{
    $settings=anemiaServiceSettings('otp');
    if (!in_array($settings['mode']??'live',['test','live'],true) ||
        !anemiaServiceReady('otp',$settings) || !function_exists('curl_init')) {
        throw new DomainException('ยังไม่พร้อมทดสอบ SMS กรุณาตรวจค่าบริการบนโฮสต์');
    }

    $check=$action==='otp-check';
    $test=$_SESSION['service_otp_test']??[];
    if ($check) {
        $code=$input['code']??null;
        if (($test['until']??0)<time() || ($test['attempts']??0)>=5 ||
            !is_string($code) || !preg_match('/^[0-9]{4,10}$/D',$code)) {
            unset($_SESSION['service_otp_test']);
            throw new DomainException('รหัสทดสอบหมดอายุหรือไม่ถูกต้อง กรุณาขอรหัสใหม่');
        }
        $_SESSION['service_otp_test']['attempts']++;
        $fields=['VerificationSid'=>$test['sid'],'Code'=>$code];
    } else {
        $phone=$input['phone']??null;
        if (!is_string($phone) || !preg_match('/^0[689][0-9]{8}$/D',$phone)) {
            throw new DomainException('กรุณากรอกเบอร์มือถือไทย 10 หลัก');
        }
        if (($_SESSION['service_otp_sent_at']??0)>time()-60) throw new DomainException('กรุณารอ 60 วินาทีก่อนส่งอีกครั้ง');
        $_SESSION['service_otp_sent_at']=time();
        $fields=['To'=>'+66'.substr($phone,1),'Channel'=>'sms'];
    }

    $sid=anemiaServiceValue($settings,'ANEMIA_VERIFY_SERVICE');
    $client=curl_init('https://verify.twilio.com/v2/Services/'.$sid.'/'.($check?'VerificationCheck':'Verifications'));
    curl_setopt_array($client,[
        CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_POSTFIELDS=>http_build_query($fields),
        CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,
        CURLOPT_USERPWD=>anemiaServiceValue($settings,'ANEMIA_VERIFY_KEY').':'.anemiaServiceValue($settings,'ANEMIA_VERIFY_SECRET'),
        CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>12,
    ]);
    $body=curl_exec($client);
    $status=curl_getinfo($client,CURLINFO_RESPONSE_CODE);
    $result=is_string($body)?json_decode($body,true):null;
    if ($status<200 || $status>=300 || ($result['status']??null)!==($check?'approved':'pending')) {
        throw new DomainException($check?'ตรวจรหัสไม่สำเร็จ กรุณาตรวจรหัสที่ได้รับหรือขอรหัสใหม่':'ส่ง SMS ทดสอบไม่สำเร็จ กรุณาตรวจค่าบริการและสิทธิ์ของบัญชี Twilio');
    }
    if ($check) {
        unset($_SESSION['service_otp_test']);
        $_SESSION['service_otp_verified_at']=time();
        return 'ยืนยันรหัส SMS ทดสอบสำเร็จ';
    }
    $verificationSid=$result['sid']??'';
    if (!is_string($verificationSid) || !preg_match('/^VE[a-f0-9]{32}$/iD',$verificationSid)) {
        throw new DomainException('ผู้ให้บริการไม่ส่งเลขอ้างอิงการทดสอบกลับมา');
    }
    $_SESSION['service_otp_test']=['sid'=>$verificationSid,'until'=>time()+300,'attempts'=>0];
    return 'ส่ง OTP ทดสอบแล้ว กรุณากรอกรหัสจาก SMS';
}
