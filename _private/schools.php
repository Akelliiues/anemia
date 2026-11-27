<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

function anemiaEnsureSchools(PDO $db): void
{
    if ($db->query('SELECT version FROM anemia_schema WHERE version=25')->fetchColumn()) return;
    $definitions=[
        'school'=>'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL, province_code CHAR(2) NOT NULL, enabled TINYINT NOT NULL DEFAULT 1, UNIQUE(province_code,name)',
        'school_teacher'=>'username VARCHAR(40) PRIMARY KEY, school_id INT UNSIGNED NOT NULL',
        'school_cohort'=>'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, school_id INT UNSIGNED NOT NULL, teacher VARCHAR(40) NOT NULL, title VARCHAR(100) NOT NULL, classroom VARCHAR(80) NOT NULL, opens_on DATE NOT NULL, closes_on DATE NOT NULL, invite CHAR(32) CHARACTER SET ascii NOT NULL UNIQUE, programme_version VARCHAR(40) NOT NULL, accepting TINYINT NOT NULL DEFAULT 1, INDEX(school_id,teacher)',
        'school_member'=>'cohort_id INT UNSIGNED NOT NULL, account_id CHAR(32) CHARACTER SET ascii NOT NULL, joined_at DATETIME NOT NULL, PRIMARY KEY(cohort_id,account_id)',
        'school_audit'=>'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor VARCHAR(40) NOT NULL, action_name VARCHAR(72) NOT NULL, school_id INT UNSIGNED NOT NULL, cohort_id INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];
    foreach ($definitions as $table=>$columns) $db->exec("CREATE TABLE IF NOT EXISTS anemia_$table ($columns) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec('INSERT IGNORE INTO anemia_schema (version) VALUES (25)');
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
        'school_teacher'=>['s.enabled=1 AND EXISTS (SELECT 1 FROM anemia_school_teacher t WHERE t.school_id=s.id AND t.username=?)',[$identity['username']]],
        default=>['1=0',[]],
    };
}

function schoolSave(PDO $db,array $identity,array $input): void
{
    $actor=$identity['username'];
    $admin=$identity['role']==='account_admin';
    $teacher=$identity['role']==='school_teacher';
    $action=$input['school_action']??'';
    if (!$admin && (!$teacher || !in_array($action,['cohort-create','cohort-edit','cohort-toggle'],true))) {
        throw new DomainException('บัญชีนี้ไม่มีสิทธิ์แก้ไขกิจกรรม');
    }
    $school=filter_var($input['school']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $cohort=filter_var($input['cohort']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $db->beginTransaction();
    try {
        if ($action==='school-create') {
            $province=$input['province']??'';
            $check=$db->prepare('SELECT code FROM anemia_province WHERE code=?');
            $check->execute([is_string($province)?$province:'']);
            if (!$check->fetchColumn()) throw new DomainException('กรุณาเลือกจังหวัด');
            $db->prepare('INSERT INTO anemia_school (name,province_code) VALUES (?,?)')->execute([schoolText($input['name']??null,160),$province]);
            $school=(int)$db->lastInsertId();
        } else {
            $check=$db->prepare('SELECT enabled FROM anemia_school WHERE id=? FOR UPDATE');
            $check->execute([$school]);
            $enabled=$check->fetchColumn();
            if ($enabled===false) throw new DomainException('ไม่พบโรงเรียน');
            if ($teacher) {
                $check=$db->prepare('SELECT 1 FROM anemia_school_teacher WHERE username=? AND school_id=? FOR UPDATE');
                $check->execute([$actor,$school]);
                if (!(int)$enabled || !$check->fetchColumn()) throw new DomainException('บัญชีนี้ไม่มีสิทธิ์ในโรงเรียน');
            }
            if ($action==='school-toggle') {
                $db->prepare('UPDATE anemia_school SET enabled=1-enabled WHERE id=?')->execute([$school]);
            } elseif (in_array($action,['grant','revoke'],true)) {
                $username=schoolText($input['teacher']??null,40);
                $check=$db->prepare("SELECT 1 FROM anemia_staff WHERE username=? AND role='school_teacher' AND enabled=1");
                $check->execute([$username]);
                if ($action==='grant' && (!$check->fetchColumn() || !(int)$enabled)) throw new DomainException('กรุณาสร้างบัญชีบทบาทครูและเปิดโรงเรียนก่อน');
                if ($action==='grant') {
                    $db->prepare('INSERT INTO anemia_school_teacher (username,school_id) VALUES (?,?) ON DUPLICATE KEY UPDATE school_id=VALUES(school_id)')->execute([$username,$school]);
                } else {
                    $db->prepare('DELETE FROM anemia_school_teacher WHERE username=? AND school_id=?')->execute([$username,$school]);
                }
                $action.=':'.$username;
            } else {
                if (in_array($action,['cohort-edit','cohort-toggle'],true)) {
                    $check=$db->prepare('SELECT teacher FROM anemia_school_cohort WHERE id=? AND school_id=? FOR UPDATE');
                    $check->execute([$cohort,$school]);
                    $owner=$check->fetchColumn();
                    if ($owner===false || ($teacher && $owner!==$actor)) throw new DomainException('ไม่พบรุ่นกิจกรรมในสิทธิ์ของบัญชีนี้');
                }
                if ($action==='cohort-toggle') {
                    $db->prepare('UPDATE anemia_school_cohort SET accepting=1-accepting WHERE id=?')->execute([$cohort]);
                } elseif (in_array($action,['cohort-create','cohort-edit'],true)) {
                    $opens=schoolDate($input['opens']??null);
                    $closes=schoolDate($input['closes']??null);
                    if ($closes<$opens) throw new DomainException('วันปิดรับต้องตรงกับหรืออยู่หลังวันเปิดรับ');
                    $values=[schoolText($input['title']??null,100),schoolText($input['classroom']??null,80),$opens,$closes];
                    if ($action==='cohort-create') {
                        if (!(int)$enabled) throw new DomainException('โรงเรียนปิดใช้งานอยู่');
                        $owner=$teacher?$actor:schoolText($input['teacher']??null,40);
                        $check=$db->prepare('SELECT 1 FROM anemia_school_teacher WHERE username=? AND school_id=? FOR UPDATE');
                        $check->execute([$owner,$school]);
                        if (!$check->fetchColumn()) throw new DomainException('กรุณาเลือกครูที่ได้รับมอบหมายในโรงเรียน');
                        $db->prepare('INSERT INTO anemia_school_cohort (title,classroom,opens_on,closes_on,school_id,teacher,invite,programme_version) VALUES (?,?,?,?,?,?,?,?)')
                           ->execute([...$values,$school,$owner,bin2hex(random_bytes(16)),ANEMIA_PROGRAMME_VERSION]);
                        $cohort=(int)$db->lastInsertId();
                    } else {
                        $db->prepare('UPDATE anemia_school_cohort SET title=?,classroom=?,opens_on=?,closes_on=? WHERE id=?')->execute([...$values,$cohort]);
                    }
                } else {
                    throw new DomainException('ไม่พบการดำเนินการ');
                }
            }
        }
        $db->prepare('INSERT INTO anemia_school_audit (actor,action_name,school_id,cohort_id) VALUES (?,?,?,?)')
           ->execute([$actor,$action,$school,$cohort?:null]);
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}
