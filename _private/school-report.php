<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

function schoolAssessmentJoins(): string
{
    return "LEFT JOIN anemia_participant_programme_assessment b ON b.account_id=m.account_id
        AND b.programme_version=c.programme_version AND b.instrument_version=? AND b.phase='before'
        LEFT JOIN anemia_participant_programme_assessment a ON a.account_id=m.account_id
        AND a.programme_version=c.programme_version AND a.instrument_version=? AND a.phase='after'";
}

function schoolReport(PDO $db,array $identity,int $region,string $province,int $year,string $today,bool $bySchool=false,int $schoolId=0): array
{
    [$scope,$params]=schoolScope($identity);
    if ($region) { $scope.=' AND p.region=?'; $params[]=$region; }
    if ($province!=='') { $scope.=' AND p.code=?'; $params[]=$province; }
    if ($schoolId) { $scope.=' AND s.id=?'; $params[]=$schoolId; }
    $yearClause=$year?' AND EXISTS (SELECT 1 FROM anemia_school_room r WHERE r.cohort_id=c.id AND r.academic_year=?)':'';
    $identityColumns=$bySchool?'s.id,s.name,s.enabled,staff.username AS coordinator,':'';
    $group=$bySchool?' GROUP BY s.id,s.name,s.enabled,staff.username ORDER BY s.name':'';
    $assessments=schoolAssessmentJoins();
    $query=$db->prepare("SELECT $identityColumns
        COUNT(DISTINCT s.id) AS schools,
        COUNT(DISTINCT CASE WHEN c.id IS NOT NULL THEN s.id END) AS schools_with_rooms,
        COUNT(DISTINCT CASE WHEN s.enabled=1 AND staff.username IS NULL THEN s.id END) AS missing_coordinator,
        COUNT(DISTINCT c.id) AS rooms,
        COUNT(DISTINCT CASE WHEN s.enabled=1 AND c.accepting=1 AND ? BETWEEN c.opens_on AND c.closes_on THEN c.id END) AS accepting,
        COUNT(DISTINCT m.account_id) AS members,
        COUNT(DISTINCT b.account_id) AS started,
        COUNT(DISTINCT a.account_id) AS completed
        FROM anemia_school s JOIN anemia_province p ON p.code=s.province_code
        LEFT JOIN anemia_school_coordinator sc ON sc.school_id=s.id
        LEFT JOIN anemia_staff staff ON staff.username=sc.username AND staff.enabled=1 AND staff.role='school_teacher'
        LEFT JOIN anemia_school_cohort c ON c.school_id=s.id$yearClause
        LEFT JOIN anemia_school_member m ON m.cohort_id=c.id
        $assessments
        WHERE $scope$group");
    $query->execute([$today,...($year?[$year]:[]),ANEMIA_PROGRAMME_INSTRUMENT_VERSION,ANEMIA_PROGRAMME_INSTRUMENT_VERSION,...$params]);
    if ($bySchool) return $query->fetchAll(PDO::FETCH_ASSOC);
    return array_map('intval',$query->fetch(PDO::FETCH_ASSOC));
}

function schoolWeeklyProgress(PDO $db,array $identity,int $region): array
{
    [$scope,$params]=schoolScope($identity);
    if ($region) { $scope.=' AND p.region=?'; $params[]=$region; }
    $query=$db->prepare("SELECT w.week_no,COUNT(DISTINCT m.account_id)
        FROM anemia_school s JOIN anemia_province p ON p.code=s.province_code
        JOIN anemia_school_cohort c ON c.school_id=s.id
        JOIN anemia_school_member m ON m.cohort_id=c.id
        JOIN anemia_participant_programme_progress w ON w.account_id=m.account_id AND w.programme_version=c.programme_version
        WHERE $scope GROUP BY w.week_no");
    $query->execute($params);
    return array_map('intval',$query->fetchAll(PDO::FETCH_KEY_PAIR));
}
