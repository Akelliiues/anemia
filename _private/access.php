<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

const ANEMIA_REPORT_ROLES = [
    'central_report' => 'ผู้ดูรายงานส่วนกลาง',
    'regional_report' => 'ผู้ดูรายงานระดับเขต',
    'provincial_report' => 'ผู้ดูรายงานระดับจังหวัด',
];

function anemiaStaffIdentity(PDO $db, string $username): ?array
{
    $query = $db->prepare('SELECT s.username,s.role,a.region,a.province_code,p.region AS province_region
        FROM anemia_staff s LEFT JOIN anemia_staff_access a ON a.username=s.username
        LEFT JOIN anemia_province p ON p.code=a.province_code WHERE s.username=? AND s.enabled=1');
    $query->execute([$username]);
    $account = $query->fetch(PDO::FETCH_ASSOC);
    if (!$account) return null;
    return match ($account['role']) {
        'account_admin', 'central_report' => $account,
        'regional_report' => (int)$account['region'] >= 1 && (int)$account['region'] <= 13 ? $account : null,
        'provincial_report' => $account['province_region'] !== null ? $account : null,
        default => null,
    };
}

function anemiaProvinceScope(array $account, int $region): array
{
    if ($account['role'] === 'provincial_report') return ['code=?', [$account['province_code']]];
    if ($account['role'] === 'regional_report') return ['region=?', [(int)$account['region']]];
    return $region ? ['region=?', [$region]] : ['', []];
}

function anemiaAccessInput(array $input): ?array
{
    $username = is_string($input['username'] ?? null) ? strtolower(trim($input['username'])) : '';
    $role = $input['role'] ?? null;
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,39}$/D', $username) ||
        !is_string($role) || !isset(ANEMIA_REPORT_ROLES[$role])) return null;
    $region = $role === 'regional_report' ? filter_var($input['region'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>13]]) : null;
    $province = $role === 'provincial_report' ? ($input['province'] ?? '') : null;
    if ($region === false || ($province !== null && (!is_string($province) || !preg_match('/^[0-9]{2}$/D', $province)))) return null;
    return ['username'=>$username,'role'=>$role,'region'=>$region,'province'=>$province,'enabled'=>($input['enabled'] ?? '') === '1' ? 1 : 0];
}

function saveAnemiaAccess(PDO $db, string $actor, string $action, array $data): array
{
    $db->beginTransaction();
    try {
        $query = $db->prepare('SELECT role, enabled FROM anemia_staff WHERE username=? FOR UPDATE');
        $query->execute([$actor]);
        $owner = $query->fetch(PDO::FETCH_ASSOC);
        if (!$owner || $owner['role'] !== 'account_admin' || (int)$owner['enabled'] !== 1) throw new DomainException('ผู้ใช้ไม่มีสิทธิ์จัดการบัญชี');
        $query->execute([$data['username']]);
        $existing = $query->fetch(PDO::FETCH_ASSOC);
        if ($existing && $existing['role'] === 'account_admin') throw new DomainException('บัญชีผู้ดูแลหลักแก้ไขจากหน้านี้ไม่ได้');
        if ($action === 'create' && $existing) throw new DomainException('ชื่อบัญชีนี้มีอยู่แล้ว');
        if ($action === 'update' && !$existing) throw new DomainException('ไม่พบบัญชีที่จะแก้ไข');
        if ($data['province'] !== null) {
            $province = $db->prepare('SELECT code FROM anemia_province WHERE code=?');
            $province->execute([$data['province']]);
            if (!$province->fetchColumn()) throw new DomainException('จังหวัดนี้ไม่อยู่ในทะเบียน');
        }
        $password = null;
        if ($action === 'create') {
            $password = bin2hex(random_bytes(12));
            $salt = bin2hex(random_bytes(24));
            $hash = hash_pbkdf2('sha256', $password, $salt, 600000, 64);
            $save = $db->prepare('INSERT INTO anemia_staff(username,salt,password_hash,role,enabled) VALUES(?,?,?,?,?)');
            $save->execute([$data['username'],$salt,$hash,$data['role'],$data['enabled']]);
        } else {
            $save = $db->prepare('UPDATE anemia_staff SET role=?,enabled=? WHERE username=?');
            $save->execute([$data['role'],$data['enabled'],$data['username']]);
        }
        $scope = $db->prepare('INSERT INTO anemia_staff_access(username,region,province_code) VALUES(?,?,?)
            ON DUPLICATE KEY UPDATE region=VALUES(region),province_code=VALUES(province_code)');
        $scope->execute([$data['username'],$data['region'],$data['province']]);
        $audit = $db->prepare('INSERT INTO anemia_access_audit(actor,target_username,action_name,role_name,region,province_code,enabled) VALUES(?,?,?,?,?,?,?)');
        $audit->execute([$actor,$data['username'],$action,$data['role'],$data['region'],$data['province'],$data['enabled']]);
        $db->commit();
        return ['username'=>$data['username'],'password'=>$password];
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}
