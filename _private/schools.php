<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

function anemiaEnsureSchools(PDO $db): void
{
    if ($db->query('SELECT version FROM anemia_schema WHERE version=27')->fetchColumn()) return;
    $definitions=[
        'school'=>'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL, province_code CHAR(2) NOT NULL, enabled TINYINT NOT NULL DEFAULT 1, UNIQUE(province_code,name)',
        'school_directory_link'=>'code VARCHAR(12) CHARACTER SET ascii PRIMARY KEY, school_id INT UNSIGNED NOT NULL, INDEX(school_id)',
        'school_teacher'=>'username VARCHAR(40) PRIMARY KEY, school_id INT UNSIGNED NOT NULL',
        'school_cohort'=>'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, school_id INT UNSIGNED NOT NULL, teacher VARCHAR(40) NOT NULL, title VARCHAR(100) NOT NULL, classroom VARCHAR(80) NOT NULL, opens_on DATE NOT NULL, closes_on DATE NOT NULL, invite CHAR(32) CHARACTER SET ascii NOT NULL UNIQUE, programme_version VARCHAR(40) NOT NULL, accepting TINYINT NOT NULL DEFAULT 1, INDEX(school_id,teacher)',
        'school_member'=>'cohort_id INT UNSIGNED NOT NULL, account_id CHAR(32) CHARACTER SET ascii NOT NULL, joined_at DATETIME NOT NULL, PRIMARY KEY(cohort_id,account_id)',
        'school_coordinator'=>'school_id INT UNSIGNED PRIMARY KEY, username VARCHAR(40) NOT NULL',
        'school_teacher_profile'=>'username VARCHAR(40) PRIMARY KEY, full_name VARCHAR(160) NOT NULL, contact VARCHAR(160) NOT NULL DEFAULT \'\'',
        'school_room'=>'cohort_id INT UNSIGNED PRIMARY KEY, school_id INT UNSIGNED NOT NULL, academic_year SMALLINT UNSIGNED NOT NULL, grade TINYINT UNSIGNED NOT NULL, room_no SMALLINT UNSIGNED NOT NULL, UNIQUE(school_id,academic_year,grade,room_no)',
        'school_room_teacher'=>'cohort_id INT UNSIGNED NOT NULL, duty VARCHAR(12) NOT NULL, username VARCHAR(40) NOT NULL, assigned_by VARCHAR(40) NOT NULL, started_at DATETIME NOT NULL, PRIMARY KEY(cohort_id,duty), INDEX(username,cohort_id)',
        'school_room_history'=>'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cohort_id INT UNSIGNED NOT NULL, duty VARCHAR(12) NOT NULL, username VARCHAR(40) NOT NULL, assigned_by VARCHAR(40) NOT NULL, started_at DATETIME NOT NULL, ended_at DATETIME NOT NULL, ended_by VARCHAR(40) NOT NULL, INDEX(cohort_id)',
        'school_audit'=>'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor VARCHAR(40) NOT NULL, action_name VARCHAR(72) NOT NULL, school_id INT UNSIGNED NOT NULL, cohort_id INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];
    foreach ($definitions as $table=>$columns) $db->exec("CREATE TABLE IF NOT EXISTS anemia_$table ($columns) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec('INSERT IGNORE INTO anemia_schema (version) VALUES (25),(26),(27)');
}

function schoolEscape(string $value): string
{
    return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}

function schoolText(mixed $input,int $max): string
{
    $value=is_string($input)?trim($input):'';
    if ($value==='' || preg_match('//u',$value)!==1 || preg_match_all('/./us',$value)>$max) throw new DomainException('กรุณาตรวจชื่อและความยาวของข้อความ');
    return $value;
}

function schoolDate(mixed $input): string
{
    $date=is_string($input) && preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/D',$input)?DateTimeImmutable::createFromFormat('!Y-m-d',$input):false;
    if (!$date || $date->format('Y-m-d')!==$input) throw new DomainException('กรุณาระบุวันที่ให้ถูกต้อง');
    return $input;
}

function schoolScope(array $identity): array
{
    return match ($identity['role']) {
        'account_admin','central_report'=>['1=1',[]],
        'regional_report'=>['p.region=?',[(int)$identity['region']]],
        'provincial_report'=>['s.province_code=?',[$identity['province_code']]],
        'school_teacher'=>['EXISTS (SELECT 1 FROM anemia_school_teacher t WHERE t.school_id=s.id AND t.username=?)',[$identity['username']]],
        default=>['1=0',[]],
    };
}

function schoolDirectory(string $province): array
{
    static $national;
    $national??=json_decode((string)file_get_contents(__DIR__.'/upper-secondary-2569.json'),true,512,JSON_THROW_ON_ERROR);
    return $national[$province]??['districts'=>[],'schools'=>[]];
}

function schoolDirectoryEntry(string $province,string $code): ?array
{
    foreach (schoolDirectory($province)['schools'] as $row) {
        if (in_array($code,[$row['code'],$row['smis']??null,$row['alternate_code']??null],true)) return $row;
    }
    return null;
}

function schoolJoinState(PDO $db,string $code,?string $account,bool $posting,string $today): array
{
    if ($posting && !$db->inTransaction()) throw new LogicException('School enrollment requires a transaction');
    $query=$db->prepare('SELECT c.id,c.title,c.classroom,c.accepting,c.opens_on,c.closes_on,s.name,s.enabled,r.academic_year
        FROM anemia_school_cohort c JOIN anemia_school s ON s.id=c.school_id LEFT JOIN anemia_school_room r ON r.cohort_id=c.id
        WHERE c.invite=?'.($posting?' FOR UPDATE':''));
    $query->execute([$code]);
    $cohort=$query->fetch(PDO::FETCH_ASSOC);
    if (!$cohort) throw new DomainException('ไม่พบรุ่นกิจกรรม กรุณาตรวจรหัสกับครู');
    $open=(bool)$cohort['enabled'] && (bool)$cohort['accepting'] && $today>=$cohort['opens_on'] && $today<=$cohort['closes_on'];
    $joined=false;
    if ($account!==null) {
        $member=$db->prepare('SELECT 1 FROM anemia_school_member WHERE cohort_id=? AND account_id=?');
        $member->execute([$cohort['id'],$account]);
        $joined=(bool)$member->fetchColumn();
    }
    return ['cohort'=>$cohort,'open'=>$open,'joined'=>$joined];
}

function schoolConfirmEnrollment(PDO $db,array $state,?string $account): void
{
    if (!$db->inTransaction()) throw new LogicException('School enrollment requires a transaction');
    if (!$account || (!$state['open'] && !$state['joined'])) throw new DomainException('รุ่นกิจกรรมยังไม่เปิดรับ หรือเซสชันหมดอายุ');
    if (!$state['joined']) {
        $db->prepare('INSERT INTO anemia_school_member (cohort_id,account_id,joined_at) VALUES (?,?,NOW())')
           ->execute([$state['cohort']['id'],$account]);
    }
}

require_once __DIR__.'/school-management.php';
