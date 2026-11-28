<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

function usageAuditLevels(): array
{
    return ['account_admin'=>50,'central_report'=>40,'regional_report'=>30,'provincial_report'=>20,'school_teacher'=>10,'participant'=>5];
}

function usageAuditEnsure(PDO $db): void
{
    if ($db->query('SELECT version FROM anemia_schema WHERE version=29')->fetchColumn()) return;
    $db->exec("CREATE TABLE IF NOT EXISTS anemia_usage_audit (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        actor VARCHAR(40) NOT NULL, actor_role VARCHAR(40) NOT NULL, privilege_level TINYINT UNSIGNED NOT NULL,
        region TINYINT UNSIGNED NULL, province_code CHAR(2) NULL,
        event_kind VARCHAR(24) NOT NULL, event_name VARCHAR(72) NOT NULL,
        school_id INT UNSIGNED NULL, cohort_id INT UNSIGNED NULL, target_teacher VARCHAR(40) NULL,
        INDEX(region,privilege_level,id), INDEX(province_code,privilege_level,id), INDEX(event_kind,id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec('INSERT IGNORE INTO anemia_schema (version) VALUES (29)');
}

function usageAuditWrite(PDO $db,array $actor,string $kind,string $event,?array $school=null,?string $target=null,?int $cohort=null): void
{
    $query=$db->prepare('INSERT INTO anemia_usage_audit
        (actor,actor_role,privilege_level,region,province_code,event_kind,event_name,school_id,target_teacher,cohort_id,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
    $query->execute([
        $actor['username'],$actor['role'],usageAuditLevels()[$actor['role']],
        $school['region']??$actor['province_region']??$actor['region']??null,$school['province_code']??$actor['province_code']??null,
        $kind,$event,$school['id']??null,$target,$cohort
    ]);
}

function usageAuditSchool(PDO $db,int $schoolId): ?array
{
    $query=$db->prepare('SELECT s.id,s.province_code,p.region FROM anemia_school s JOIN anemia_province p ON p.code=s.province_code WHERE s.id=?');
    $query->execute([$schoolId]);
    return $query->fetch(PDO::FETCH_ASSOC)?:null;
}

function usageAuditStaffSession(PDO $db,string $username,string $event): void
{
    require_once __DIR__.'/access.php';
    $identity=anemiaStaffIdentity($db,$username);
    if (!$identity) return;
    $school=null;
    if ($identity['role']==='school_teacher') {
        require_once __DIR__.'/schools.php';
        anemiaEnsureSchools($db);
        $query=$db->prepare('SELECT school_id FROM anemia_school_teacher WHERE username=?');
        $query->execute([$username]);
        $schoolId=(int)$query->fetchColumn();
        if ($schoolId) $school=usageAuditSchool($db,$schoolId);
    }
    usageAuditEnsure($db);
    usageAuditWrite($db,$identity,'session',$event,$school);
}

function usageCanPreviewTeacher(array $actor): bool
{
    return in_array($actor['role'],['account_admin','regional_report','provincial_report'],true);
}

function usageTeacherPreview(PDO $db,array $actor,int $schoolId,string $username): ?array
{
    if (!usageCanPreviewTeacher($actor)) return null;
    [$scope,$params]=schoolScope($actor);
    $query=$db->prepare("SELECT s.id,s.province_code,p.region,u.username FROM anemia_school s
        JOIN anemia_province p ON p.code=s.province_code
        JOIN anemia_school_teacher t ON t.school_id=s.id
        JOIN anemia_staff u ON u.username=t.username AND u.enabled=1 AND u.role='school_teacher'
        WHERE s.id=? AND t.username=? AND $scope");
    $query->execute([$schoolId,$username,...$params]);
    return $query->fetch(PDO::FETCH_ASSOC)?:null;
}

function usageAuditScope(array $identity): array
{
    return match ($identity['role']) {
        'account_admin'=>['1=1',[]],
        'regional_report'=>['l.region=? AND l.privilege_level<=? AND l.privilege_level>0',[(int)$identity['region'],30]],
        'provincial_report'=>['l.province_code=? AND l.region=? AND l.privilege_level<=? AND l.privilege_level>0',
            [$identity['province_code'],(int)($identity['province_region']??0),20]],
        default=>['1=0',[]]
    };
}
