<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

function schoolValue(PDO $db,string $sql,array $params): mixed
{
    $query=$db->prepare($sql);
    $query->execute($params);
    return $query->fetchColumn();
}

function schoolCanAppoint(PDO $db,array $identity,array $school): bool
{
    return match ($identity['role']) {
        'account_admin'=>true,
        'provincial_report'=>$identity['province_code']===$school['province_code'],
        'regional_report'=>(int)$identity['region']===(int)$school['region'] &&
            !schoolValue($db,"SELECT 1 FROM anemia_staff_access a JOIN anemia_staff u ON u.username=a.username
                WHERE a.province_code=? AND u.enabled=1 AND u.role='provincial_report' LIMIT 1",[$school['province_code']]),
        default=>false,
    };
}

function schoolAudit(PDO $db,string $actor,string $action,int $school,?int $cohort=null): void
{
    $db->prepare('INSERT INTO anemia_school_audit (actor,action_name,school_id,cohort_id) VALUES (?,?,?,?)')
        ->execute([$actor,$action,$school,$cohort]);
}

function schoolTeacher(PDO $db,int $school,mixed $value,bool $enroll=false): string
{
    $username=strtolower(schoolText($value,40));
    if (!schoolValue($db,"SELECT 1 FROM anemia_staff WHERE username=? AND enabled=1 AND role='school_teacher' FOR UPDATE",[$username])) {
        throw new DomainException('กรุณาเลือกบัญชีครูที่เปิดใช้งาน');
    }
    $existing=schoolValue($db,'SELECT school_id FROM anemia_school_teacher WHERE username=? FOR UPDATE',[$username]);
    if ((int)$existing===$school) return $username;
    if ($existing!==false || !$enroll) throw new DomainException('บัญชีครูยังไม่ได้อยู่ในทะเบียนของโรงเรียนนี้');
    $db->prepare('INSERT INTO anemia_school_teacher (username,school_id) VALUES (?,?)')->execute([$username,$school]);
    return $username;
}

function schoolInt(mixed $value,int $min,int $max): int
{
    $number=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>$max]]);
    if ($number===false) throw new DomainException('กรุณาตรวจสอบปี ระดับชั้น หรือจำนวนห้อง');
    return $number;
}

function schoolRoomPlan(array $input): array
{
    $year=schoolInt($input['year']??null,2500,2642);
    $rooms=[];
    foreach ([4,5,6] as $grade) {
        $count=schoolInt($input['grade_'.$grade]??'0',0,50);
        for ($no=1;$no<=$count;$no++) $rooms[]=[$grade,$no,'ม.'.$grade.'/'.$no];
    }
    return [$year,$rooms];
}

function schoolRoomAssignment(PDO $db,int $cohort,string $duty,string $username,string $actor): void
{
    $existing=schoolValue($db,'SELECT username FROM anemia_school_room_teacher WHERE cohort_id=? AND duty=?',[$cohort,$duty]);
    if ($existing===$username || ($existing===false && $username==='')) return;
    if ($existing!==false) {
        $db->prepare('INSERT INTO anemia_school_room_history (cohort_id,duty,username,assigned_by,started_at,ended_at,ended_by)
            SELECT cohort_id,duty,username,assigned_by,started_at,NOW(),? FROM anemia_school_room_teacher WHERE cohort_id=? AND duty=?')
            ->execute([$actor,$cohort,$duty]);
        $db->prepare('DELETE FROM anemia_school_room_teacher WHERE cohort_id=? AND duty=?')->execute([$cohort,$duty]);
    }
    if ($username!=='') {
        $db->prepare('INSERT INTO anemia_school_room_teacher (cohort_id,duty,username,assigned_by,started_at) VALUES (?,?,?,?,NOW())')
            ->execute([$cohort,$duty,$username,$actor]);
    }
}

function schoolSchoolRecord(PDO $db,int $school): array
{
    $query=$db->prepare('SELECT s.*,p.region,sc.username AS coordinator FROM anemia_school s
        JOIN anemia_province p ON p.code=s.province_code LEFT JOIN anemia_school_coordinator sc ON sc.school_id=s.id WHERE s.id=? FOR UPDATE');
    $query->execute([$school]);
    $record=$query->fetch(PDO::FETCH_ASSOC);
    if (!$record) throw new DomainException('ไม่พบโรงเรียน');
    return $record;
}

function schoolParticipation(PDO $db,array $schoolIds): array
{
    if (!$schoolIds) return [];
    $placeholders=implode(',',array_fill(0,count($schoolIds),'?'));
    $query=$db->prepare("SELECT s.id,r.academic_year,COUNT(DISTINCT c.id) AS rooms,
        COUNT(DISTINCT m.account_id) AS participants,
        (EXISTS (SELECT 1 FROM anemia_school_teacher t WHERE t.school_id=s.id)
        OR EXISTS (SELECT 1 FROM anemia_school_coordinator sc WHERE sc.school_id=s.id)
        OR EXISTS (SELECT 1 FROM anemia_school_audit a WHERE a.school_id=s.id
            AND (a.action_name LIKE 'grant:%' OR a.action_name LIKE 'coordinator:%'))) AS has_staff
        FROM anemia_school s LEFT JOIN anemia_school_cohort c ON c.school_id=s.id
        LEFT JOIN anemia_school_room r ON r.cohort_id=c.id
        LEFT JOIN anemia_school_member m ON m.cohort_id=c.id
        WHERE s.id IN ($placeholders) GROUP BY s.id,r.academic_year ORDER BY r.academic_year DESC");
    $query->execute($schoolIds);
    $summary=[];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id=(int)$row['id'];
        $summary[$id]??=['hasData'=>false,'years'=>[]];
        if ($row['has_staff'] || $row['rooms']) $summary[$id]['hasData']=true;
        if ($row['rooms']) $summary[$id]['years'][]=[
            'year'=>$row['academic_year']===null?'รุ่นเดิมที่ไม่ระบุปี':'ปีการศึกษา '.$row['academic_year'],
            'rooms'=>(int)$row['rooms'],
            'participants'=>(int)$row['participants'],
        ];
    }
    return $summary;
}

function schoolStaffingIssues(PDO $db,string $province,int $year,string $today): array
{
    $query=$db->prepare("SELECT s.id AS school_id,s.name AS school_name,c.id AS cohort_id,c.classroom,r.academic_year,c.opens_on,
        CASE WHEN rt.username IS NULL THEN 'missing' ELSE 'inactive' END AS issue
        FROM anemia_school s JOIN anemia_school_room r ON r.school_id=s.id
        JOIN anemia_school_cohort c ON c.id=r.cohort_id AND c.school_id=s.id
        LEFT JOIN anemia_school_room_teacher rt ON rt.cohort_id=c.id AND rt.duty='primary'
        LEFT JOIN anemia_staff u ON u.username=rt.username AND u.enabled=1 AND u.role='school_teacher'
        LEFT JOIN anemia_school_teacher roster ON roster.username=rt.username AND roster.school_id=s.id
        WHERE s.province_code=? AND s.enabled=1 AND c.closes_on>=?
          AND (u.username IS NULL OR roster.username IS NULL)
          AND (rt.username IS NOT NULL OR c.opens_on<=DATE_ADD(?, INTERVAL 14 DAY))".
        ($year?' AND r.academic_year=?':'').' ORDER BY c.opens_on,s.name,r.grade,r.room_no');
    $query->execute([$province,$today,$today,...($year?[$year]:[])]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function schoolSave(PDO $db,array $identity,array $input): int
{
    $actor=$identity['username'];
    $admin=$identity['role']==='account_admin';
    $teacher=$identity['role']==='school_teacher';
    $action=$input['school_action']??'';
    $school=(int)filter_var($input['school']??null,FILTER_VALIDATE_INT);
    $cohort=(int)filter_var($input['cohort']??null,FILTER_VALIDATE_INT);
    $db->beginTransaction();
    try {
        if ($action==='school-create' || $action==='directory-add') {
            if (!$admin) throw new DomainException('บัญชีนี้ไม่มีสิทธิ์เพิ่มโรงเรียน');
            $name=$input['name']??null;
            $province=$input['province']??null;
            if ($action==='directory-add') {
                $code=$input['directory_code']??null;
                $province=$input['directory_province']??'34';
                $entry=is_string($province) && is_string($code)?schoolDirectoryEntry($province,$code):null;
                if (!$entry) throw new DomainException('ไม่พบโรงเรียนในข้อมูลอ้างอิงของจังหวัดที่เลือก');
                $name=$entry['name'];
            }
            if (!is_string($province) || !schoolValue($db,'SELECT 1 FROM anemia_province WHERE code=?',[$province])) throw new DomainException('กรุณาเลือกจังหวัด');
            $school=0;
            if ($action==='directory-add') {
                $school=(int)schoolValue($db,'SELECT school_id FROM anemia_school_directory_link WHERE code=? FOR UPDATE',[$entry['code']]);
                if (!$school) {
                    $school=(int)schoolValue($db,'SELECT id FROM anemia_school WHERE province_code=? AND name IN (?,?) ORDER BY name=? DESC,id LIMIT 1 FOR UPDATE',
                        [$province,$name,$entry['legacy_name']??$name,$name]);
                }
            }
            if (!$school) {
                $db->prepare('INSERT INTO anemia_school (name,province_code) VALUES (?,?)')->execute([schoolText($name,160),$province]);
                $school=(int)$db->lastInsertId();
            }
            if ($action==='directory-add') {
                $db->prepare('INSERT IGNORE INTO anemia_school_directory_link (code,school_id) VALUES (?,?)')->execute([$entry['code'],$school]);
            }
        } else {
            $record=schoolSchoolRecord($db,$school);
            if ($teacher && (!(int)$record['enabled'] || !schoolValue($db,'SELECT 1 FROM anemia_school_teacher WHERE school_id=? AND username=?',[$school,$actor]))) {
                throw new DomainException('บัญชีนี้ไม่มีสิทธิ์ในโรงเรียน');
            }
            $manage=$admin || ($teacher && $record['coordinator']===$actor);
            switch ($action) {
                case 'coordinator':
                    if (!schoolCanAppoint($db,$identity,$record)) throw new DomainException('บัญชีนี้ไม่มีสิทธิ์แต่งตั้งผู้ประสานงานโรงเรียน');
                    $username=schoolTeacher($db,$school,$input['teacher']??null,true);
                    $db->prepare('INSERT INTO anemia_school_coordinator (school_id,username) VALUES (?,?) ON DUPLICATE KEY UPDATE username=VALUES(username)')
                        ->execute([$school,$username]);
                    $action.=':'.$username;
                    break;
                case 'school-withdraw':
                case 'school-archive':
                case 'school-reopen':
                    if (!$admin) throw new DomainException('บัญชีนี้ไม่มีสิทธิ์เปลี่ยนสถานะโรงเรียน');
                    $enabled=$action==='school-reopen';
                    if ((bool)$record['enabled']===$enabled) {
                        $db->commit();
                        return $school;
                    }
                    if ($action==='school-withdraw' && schoolParticipation($db,[$school])[$school]['hasData']) {
                        throw new DomainException('โรงเรียนมีข้อมูลแล้ว กรุณาเลือกยุติการเข้าร่วมเพื่อเก็บประวัติ');
                    }
                    $db->prepare('UPDATE anemia_school SET enabled=? WHERE id=?')->execute([(int)$enabled,$school]);
                    if (!$enabled) $db->prepare('UPDATE anemia_school_cohort SET accepting=0 WHERE school_id=? AND accepting=1')->execute([$school]);
                    break;
                case 'school-edit':
                    if (!$admin) throw new DomainException('บัญชีนี้ไม่มีสิทธิ์แก้ไขทะเบียนโรงเรียน');
                    $db->prepare('UPDATE anemia_school SET name=? WHERE id=?')->execute([schoolText($input['name']??null,160),$school]);
                    break;
                case 'grant':
                    if (!$manage) throw new DomainException('บัญชีนี้ไม่มีสิทธิ์จัดการทะเบียนครู');
                    $action.=':'.schoolTeacher($db,$school,$input['teacher']??null,true);
                    break;
                case 'revoke':
                    if (!$manage) throw new DomainException('บัญชีนี้ไม่มีสิทธิ์จัดการทะเบียนครู');
                    $username=strtolower(schoolText($input['teacher']??null,40));
                    if ($record['coordinator']===$username) throw new DomainException('กรุณาแต่งตั้งผู้ประสานงานคนใหม่ก่อนถอนสิทธิ์');
                    $query=$db->prepare('SELECT t.cohort_id,t.duty FROM anemia_school_room_teacher t JOIN anemia_school_room r ON r.cohort_id=t.cohort_id WHERE r.school_id=? AND t.username=?');
                    $query->execute([$school,$username]);
                    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) schoolRoomAssignment($db,(int)$row['cohort_id'],$row['duty'],'',$actor);
                    $db->prepare('DELETE FROM anemia_school_teacher WHERE username=? AND school_id=?')->execute([$username,$school]);
                    $action.=':'.$username;
                    break;
                case 'teacher-profile':
                    if (!$teacher) throw new DomainException('เฉพาะครูเจ้าของบัญชีที่แก้ข้อมูลติดต่อได้');
                    $contact=$input['contact']??'';
                    $contact=$contact===''?'':schoolText($contact,160);
                    $db->prepare('INSERT INTO anemia_school_teacher_profile (username,full_name,contact) VALUES (?,?,?)
                        ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),contact=VALUES(contact)')
                        ->execute([$actor,schoolText($input['full_name']??null,160),$contact]);
                    break;
                case 'rooms-create':
                case 'rooms-copy':
                    if (!$manage || !(int)$record['enabled']) throw new DomainException('บัญชีนี้ไม่มีสิทธิ์สร้างห้องในโรงเรียนนี้');
                    [$year,$rooms]=schoolRoomPlan($input);
                    if ($action==='rooms-copy') {
                        $from=schoolInt($input['from_year']??null,2500,$year-1);
                        $query=$db->prepare('SELECT r.grade,r.room_no,c.classroom FROM anemia_school_room r JOIN anemia_school_cohort c ON c.id=r.cohort_id WHERE r.school_id=? AND r.academic_year=?');
                        $query->execute([$school,$from]);
                        $rooms=$query->fetchAll(PDO::FETCH_NUM);
                    }
                    if (!$rooms) throw new DomainException('กรุณาระบุจำนวนห้อง หรือเลือกปีที่มีห้องให้คัดลอก');
                    $opens=schoolDate($input['opens']??null);
                    $closes=schoolDate($input['closes']??null);
                    if ($closes<$opens) throw new DomainException('วันปิดรับต้องตรงกับหรืออยู่หลังวันเปิดรับ');
                    $title=schoolText($input['title']??null,100);
                    $insert=$db->prepare('INSERT INTO anemia_school_cohort (school_id,teacher,title,classroom,opens_on,closes_on,invite,programme_version,accepting) VALUES (?,?,?,?,?,?,?,?,0)');
                    $room=$db->prepare('INSERT INTO anemia_school_room (cohort_id,school_id,academic_year,grade,room_no) VALUES (?,?,?,?,?)');
                    foreach ($rooms as [$grade,$no,$classroom]) {
                        if (schoolValue($db,'SELECT 1 FROM anemia_school_room WHERE school_id=? AND academic_year=? AND grade=? AND room_no=?',[$school,$year,$grade,$no])) continue;
                        $insert->execute([$school,$actor,$title,$classroom,$opens,$closes,bin2hex(random_bytes(16)),'school-'.bin2hex(random_bytes(12))]);
                        $room->execute([(int)$db->lastInsertId(),$school,$year,$grade,$no]);
                    }
                    break;
                case 'rooms-accept':
                case 'room-assign':
                    if (!$manage) throw new DomainException('บัญชีนี้ไม่มีสิทธิ์มอบหมายครูประจำห้อง');
                    $rooms=$input['rooms']??null;
                    if (!is_array($rooms) || !$rooms || count($rooms)>150) throw new DomainException('กรุณาเลือกห้องเรียน');
                    $duty=$input['duty']??'';
                    $username='';
                    if ($action==='room-assign') {
                        if (!in_array($duty,['primary','assistant'],true)) throw new DomainException('กรุณาเลือกบทบาทครู');
                        $username=($input['teacher']??'')===''?'':schoolTeacher($db,$school,$input['teacher']);
                    } else {
                        $accepting=schoolInt($input['accepting']??null,0,1);
                        if ($accepting && !(int)$record['enabled']) throw new DomainException('โรงเรียนปิดใช้งานอยู่');
                    }
                    foreach (array_unique(array_map(static fn($value)=>schoolInt($value,1,PHP_INT_MAX),$rooms)) as $roomId) {
                        if (!schoolValue($db,'SELECT 1 FROM anemia_school_room WHERE school_id=? AND cohort_id=?',[$school,$roomId])) throw new DomainException('ไม่พบห้องเรียนในโรงเรียนนี้');
                        if ($action==='rooms-accept') {
                            $db->prepare('UPDATE anemia_school_cohort SET accepting=? WHERE id=? AND school_id=?')->execute([$accepting,$roomId,$school]);
                            schoolAudit($db,$actor,$accepting?'room-open':'room-close',$school,$roomId);
                            continue;
                        }
                        if ($username!=='' && schoolValue($db,'SELECT 1 FROM anemia_school_room_teacher WHERE cohort_id=? AND username=? AND duty<>?',[$roomId,$username,$duty])) throw new DomainException('บัญชีนี้รับผิดชอบอีกบทบาทในห้องที่เลือกอยู่แล้ว');
                        schoolRoomAssignment($db,$roomId,$duty,$username,$actor);
                        schoolAudit($db,$actor,$action.':'.$duty.':'.$username,$school,$roomId);
                    }
                    break;
                case 'room-target':
                    if (!(int)$record['enabled']) throw new DomainException('กรุณาเปิดใช้งานโรงเรียนก่อนตั้งเป้าหมาย');
                    $targetRoom=$db->prepare("SELECT c.teacher,r.cohort_id AS room_id,rt.username AS assigned
                        FROM anemia_school_cohort c LEFT JOIN anemia_school_room r ON r.cohort_id=c.id
                        LEFT JOIN anemia_school_room_teacher rt ON rt.cohort_id=r.cohort_id AND rt.username=?
                        WHERE c.id=? AND c.school_id=? FOR UPDATE");
                    $targetRoom->execute([$actor,$cohort,$school]);
                    $room=$targetRoom->fetch(PDO::FETCH_ASSOC);
                    if (!$room || (!$manage && !($teacher && ($room['room_id']?$room['assigned']===$actor:$room['teacher']===$actor)))) {
                        throw new DomainException('บัญชีนี้ไม่มีสิทธิ์ตั้งเป้าหมายของห้องนี้');
                    }
                    $value=$input['target']??'';
                    if ($value==='') {
                        $db->prepare('DELETE FROM anemia_school_room_target WHERE cohort_id=?')->execute([$cohort]);
                    } else {
                        $target=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
                        if ($target===false) throw new DomainException('กรุณากรอกจำนวนเป้าหมายเป็นจำนวนเต็มตั้งแต่ 1 ถึง 65,535 คน');
                        $db->prepare('INSERT INTO anemia_school_room_target (cohort_id,participants) VALUES (?,?)
                            ON DUPLICATE KEY UPDATE participants=VALUES(participants)')->execute([$cohort,$target]);
                    }
                    break;
                case 'cohort-edit':
                case 'cohort-toggle':
                    if (!(int)$record['enabled']) throw new DomainException('กรุณาเปิดใช้งานโรงเรียนก่อนแก้ไขห้อง');
                    $query=$db->prepare('SELECT c.teacher,r.cohort_id AS room_id FROM anemia_school_cohort c
                        LEFT JOIN anemia_school_room r ON r.cohort_id=c.id
                        WHERE c.id=? AND c.school_id=? FOR UPDATE');
                    $query->execute([$cohort,$school]);
                    $row=$query->fetch(PDO::FETCH_ASSOC);
                    if (!$row || (!$manage && !($teacher && !$row['room_id'] && $row['teacher']===$actor))) throw new DomainException('ไม่พบห้องกิจกรรมในสิทธิ์ของบัญชีนี้');
                    if ($action==='cohort-toggle') {
                        $db->prepare('UPDATE anemia_school_cohort SET accepting=1-accepting WHERE id=?')->execute([$cohort]);
                    } else {
                        $opens=schoolDate($input['opens']??null);
                        $closes=schoolDate($input['closes']??null);
                        if ($closes<$opens) throw new DomainException('วันปิดรับต้องตรงกับหรืออยู่หลังวันเปิดรับ');
                        $db->prepare('UPDATE anemia_school_cohort SET title=?,classroom=?,opens_on=?,closes_on=? WHERE id=?')
                            ->execute([schoolText($input['title']??null,100),schoolText($input['classroom']??null,80),$opens,$closes,$cohort]);
                    }
                    break;
                default:
                    throw new DomainException('ไม่พบการดำเนินการ');
            }
        }
        if (!in_array($action,['room-assign','rooms-accept'],true)) schoolAudit($db,$actor,$action,$school,$cohort?:null);
        $db->commit();
        return $school;
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}
