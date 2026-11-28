<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

function anemiaFacilityDirectory(): array
{
    $source=file_get_contents(__DIR__.'/../facility-directory.js');
    if (!is_string($source) || !preg_match('/^const hospitalDirectory=(\[.*\]);\s*$/ms',$source,$match)) throw new RuntimeException('Facility directory unavailable');
    return json_decode($match[1],true,8,JSON_THROW_ON_ERROR);
}

function anemiaFacilityKey(array $row): string
{
    return hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));
}

function anemiaEffectiveHub(string $alias): string
{
    return "COALESCE($alias.hub,(SELECT IF(COUNT(*)=1,MIN(h.code),NULL) FROM anemia_network_facility h
        WHERE h.district=$alias.district AND h.kind='hospital' AND h.main_hospital=1))";
}

function anemiaQueueNetworkScope(bool $admin,?array $grant): array
{
    if ($admin) return ['1=1',[]];
    if (($grant['scope_kind'] ?? '')!=='facility') return ['1=0',[]];
    $hub=anemiaEffectiveHub('f');
    return ["EXISTS (SELECT 1 FROM anemia_network_facility f WHERE f.facility_key=q.facility_key
        AND (f.code=? OR (f.kind='primary' AND $hub=?)))",[$grant['scope_code'],$grant['scope_code']]];
}

function anemiaManageNetworkScope(bool $admin,?array $grant): array
{
    if ($admin) return ['1=1',[]];
    return match ($grant['scope_kind'] ?? '') {
        'province'=>['LEFT(f.district,2)=?',[$grant['scope_code']]],
        'district'=>['f.district=?',[$grant['scope_code']]],
        default=>['1=0',[]],
    };
}

function anemiaEnsureBooking(PDO $db): void
{
    if ($db->query('SELECT version FROM anemia_schema WHERE version=31')->fetchColumn()) return;
    $db->exec("CREATE TABLE IF NOT EXISTS anemia_network_booking (
        facility_code VARCHAR(12) CHARACTER SET ascii PRIMARY KEY,
        appointment TINYINT NOT NULL, callback TINYINT NOT NULL, paused_until DATE NULL,
        updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB");
    if ((int)$db->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':anemia-booking-index'), 10)")->fetchColumn()!==1) {
        throw new RuntimeException('Booking migration lock unavailable');
    }
    try {
        if (!$db->query("SHOW INDEX FROM anemia_network_grant WHERE Key_name='booking_queue_owner'")->fetchColumn()) {
            $db->exec('ALTER TABLE anemia_network_grant ADD INDEX booking_queue_owner (scope_kind,scope_code)');
        }
    } finally {
        $db->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':anemia-booking-index'))")->closeCursor();
    }
    $db->exec('INSERT IGNORE INTO anemia_schema (version) VALUES (31)');
}

function anemiaBookingFlags(array $facility,string $today): array
{
    $ready=!empty($facility['queue_owner']) && ($facility['kind']==='hospital' || !empty($facility['effective_hub']));
    $paused=(!empty($facility['appointment']) || !empty($facility['callback'])) && !empty($facility['paused_until']) && $facility['paused_until']>$today;
    return ['appointment'=>$ready && !$paused && !empty($facility['appointment']),
        'callback'=>$ready && !$paused && !empty($facility['callback']),'paused'=>$paused];
}

function anemiaBookingOwnerSql(): string
{
    return "EXISTS (SELECT 1 FROM anemia_network_grant g JOIN anemia_staff s ON s.username=g.username AND s.enabled=1
        WHERE g.scope_kind='facility' AND g.scope_code=f.code AND s.role<>'school_teacher')";
}

function anemiaBookingFacility(PDO $db,string $key,bool $lock=false): ?array
{
    $query=$db->prepare("SELECT f.code,f.kind,f.district,".anemiaEffectiveHub('f')." AS effective_hub,
        ".anemiaBookingOwnerSql()." AS queue_owner,b.appointment,b.callback,b.paused_until FROM anemia_network_facility f
        LEFT JOIN anemia_network_booking b ON b.facility_code=f.code WHERE f.facility_key=?".($lock?' FOR UPDATE':''));
    $query->execute([$key]);
    return $query->fetch(PDO::FETCH_ASSOC)?:null;
}

function anemiaSaveBooking(PDO $db,bool $admin,?array $grant,string $staff,array $input): string
{
    $code=$input['facility']??null;
    if (!is_string($code) || !preg_match('/^[0-9]{1,12}$/D',$code)) throw new DomainException('กรุณาเลือกหน่วยบริการ');
    [$scope,$params]=($grant['scope_kind']??'')==='facility' && !$admin
        ? ['f.code=?',[$grant['scope_code']]]:anemiaManageNetworkScope($admin,$grant);
    $query=$db->prepare("SELECT f.kind,".anemiaEffectiveHub('f')." AS effective_hub
        FROM anemia_network_facility f WHERE f.code=? AND $scope FOR UPDATE");
    $query->execute([$code,...$params]);
    $facility=$query->fetch(PDO::FETCH_ASSOC);
    if (!$facility) throw new DomainException('ไม่มีสิทธิ์ตั้งค่าหน่วยบริการนี้');
    $appointment=($input['appointment']??'')==='1';
    $callback=($input['callback']??'')==='1';
    $resume=$input['resume']??'';
    $date=is_string($resume) && $resume!==''?DateTimeImmutable::createFromFormat('!Y-m-d',$resume):false;
    if ($resume!=='' && (!$date || $date->format('Y-m-d')!==$resume || $resume<=date('Y-m-d'))) {
        throw new DomainException('กรุณาเลือกวันเปิดรับอีกครั้งหลังวันนี้');
    }
    if ($appointment || $callback) {
        if ($facility['kind']==='primary' && !$facility['effective_hub']) throw new DomainException('กรุณากำหนดโรงพยาบาลแม่ข่ายก่อนเปิดรับ');
        $owner=$db->prepare('SELECT '.anemiaBookingOwnerSql().' FROM anemia_network_facility f WHERE f.code=?');
        $owner->execute([$code]);
        if (!$owner->fetchColumn()) throw new DomainException('กรุณากำหนดเจ้าหน้าที่รับผิดชอบคิวก่อนเปิดรับ');
    }
    $db->prepare('INSERT INTO anemia_network_booking VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE
        appointment=VALUES(appointment),callback=VALUES(callback),paused_until=VALUES(paused_until),updated_at=VALUES(updated_at)')
        ->execute([$code,(int)$appointment,(int)$callback,$resume?:null]);
    $db->prepare("INSERT INTO anemia_network_audit(actor,action_name,target,old_value,new_value,changed_at) VALUES (?,'booking',?,NULL,?,NOW())")
        ->execute([$staff,$code,implode(':',[(int)$appointment,(int)$callback,$resume])]);
    return 'บันทึกสถานะรับคำขอออนไลน์แล้ว';
}

function anemiaSaveNetwork(PDO $db,bool $admin,?array $grant,string $staff,array $input): string
{
    $action=$input['network_action']??'';
    $audit=$db->prepare('INSERT INTO anemia_network_audit(actor,action_name,target,old_value,new_value,changed_at) VALUES (?,?,?,?,?,NOW())');
    if ($action==='register' && $admin) {
        $directory=anemiaFacilityDirectory();
        $csv=$input['registry']??'';
        if (!is_string($csv) || strlen($csv)>500000) throw new DomainException('ขนาดข้อมูลทะเบียนไม่ถูกต้อง');
        $lines=preg_split('/\R/',trim($csv),-1,PREG_SPLIT_NO_EMPTY);
        if (!$lines || count($lines)>11000) throw new DomainException('กรุณาระบุรายการหน่วยบริการ');
        $save=$db->prepare('INSERT INTO anemia_network_facility(code,facility_key,name,district,kind,main_hospital) VALUES (?,?,?,?,?,?)');
        foreach ($lines as $line) {
            [$code,$district,$index,$main]=array_pad(array_map('trim',explode(',',$line)),4,'');
            if (!preg_match('/^\d{5,12}$/D',$code) || !preg_match('/^\d{4}$/D',$district) || !ctype_digit($index) ||
                !isset($directory[(int)$index]) || !in_array($main,['0','1'],true)) throw new DomainException('ตรวจรหัสหน่วย รหัสอำเภอ ลำดับ และค่าระบุแม่ข่าย');
            $row=$directory[(int)$index];
            $kind=(int)$row[4]===1?'primary':'hospital';
            if ($kind==='primary' && $main==='1') throw new DomainException('กำหนดแม่ข่ายเริ่มต้นได้เฉพาะโรงพยาบาล');
            $save->execute([$code,anemiaFacilityKey($row),$row[0],$district,$kind,(int)$main]);
            $audit->execute([$staff,'register',$code,null,$district]);
        }
        return 'เพิ่มหน่วยบริการแล้ว '.count($lines).' แห่ง';
    }
    if ($action==='grant' && $admin) {
        $username=$input['username']??null;
        $kind=$input['scope_kind']??null;
        $code=$input['scope_code']??null;
        if (!is_string($username) || !preg_match('/^[a-z0-9._-]{3,40}$/D',$username) || !is_string($code) ||
            !in_array($kind,['province','district','facility','remove'],true)) throw new DomainException('ตรวจบัญชีและขอบเขตสิทธิ์');
        $exists=$db->prepare("SELECT username FROM anemia_staff WHERE username=? AND role<>'account_admin'");
        $exists->execute([$username]);
        if (!$exists->fetchColumn()) throw new DomainException('ไม่พบบัญชีเจ้าหน้าที่');
        $lookup=$db->prepare('SELECT CONCAT(scope_kind,\':\',scope_code) FROM anemia_network_grant WHERE username=? FOR UPDATE');
        $lookup->execute([$username]);
        $old=$lookup->fetchColumn()?:null;
        if ($kind==='remove') {
            $db->prepare('DELETE FROM anemia_network_grant WHERE username=?')->execute([$username]);
            $new=null;
        } else {
            $check=$db->prepare(match ($kind) {
                'province'=>'SELECT code FROM anemia_province WHERE code=?',
                'district'=>'SELECT district FROM anemia_network_facility WHERE district=? LIMIT 1',
                'facility'=>'SELECT code FROM anemia_network_facility WHERE code=?',
            });
            $check->execute([$code]);
            if (!$check->fetchColumn()) throw new DomainException('ไม่พบพื้นที่หรือหน่วยบริการในทะเบียน');
            $db->prepare('INSERT INTO anemia_network_grant VALUES (?,?,?) ON DUPLICATE KEY UPDATE scope_kind=VALUES(scope_kind),scope_code=VALUES(scope_code)')->execute([$username,$kind,$code]);
            $new=$kind.':'.$code;
        }
        $audit->execute([$staff,'grant',$username,$old,$new]);
        return 'บันทึกสิทธิ์เครือข่ายแล้ว';
    }
    if ($action==='link') {
        [$scope,$params]=anemiaManageNetworkScope($admin,$grant);
        $code=$input['facility']??null;
        $hub=$input['hub']??null;
        if (!is_string($code)||!is_string($hub)) throw new DomainException('กรุณาเลือกหน่วยบริการ');
        $lookup=$db->prepare("SELECT f.district,f.hub,".anemiaEffectiveHub('f')." AS effective_hub FROM anemia_network_facility f WHERE f.code=? AND f.kind='primary' AND $scope FOR UPDATE");
        $lookup->execute([$code,...$params]);
        $row=$lookup->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new DomainException('ไม่พบ รพ.สต. ในพื้นที่ที่รับผิดชอบ');
        if ($hub!=='') {
            $check=$db->prepare("SELECT code FROM anemia_network_facility WHERE code=? AND district=? AND kind='hospital'");
            $check->execute([$hub,$row['district']]);
            if (!$check->fetchColumn()) throw new DomainException('เลือกโรงพยาบาลในอำเภอเดียวกัน');
        }
        $db->prepare('UPDATE anemia_network_facility SET hub=? WHERE code=?')->execute([$hub?:null,$code]);
        $audit->execute([$staff,'link',$code,$row['effective_hub'],$hub?:null]);
        return 'บันทึกแม่ข่ายแล้ว';
    }
    throw new DomainException('บัญชีนี้ไม่มีสิทธิ์ดำเนินการ');
}
