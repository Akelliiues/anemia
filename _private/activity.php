<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

const ANEMIA_ACTIVITY_LABELS = [
    'start'=>'เริ่มรอบทดลอง', 'first'=>'ทำแบบประเมินรอบแรกครบ', 'review_open'=>'เปิดคำถามทบทวน',
    'review_done'=>'ตอบคำถามทบทวน', 'review_skip'=>'ข้ามคำถามทบทวน',
    'signal'=>'เห็นคำเตือนตัวอย่าง', 'directory'=>'เปิดค้นหาสถานบริการ',
    'callback_open'=>'เปิดแบบฟอร์มติดต่อกลับ', 'callback_sent'=>'ส่งคำขอติดต่อกลับจำลอง',
    'step_1'=>'ตอบถึงเรื่องที่ 1', 'step_2'=>'ตอบถึงเรื่องที่ 2', 'step_3'=>'ตอบถึงเรื่องที่ 3',
    'step_4'=>'ตอบถึงเรื่องที่ 4', 'step_5'=>'ตอบถึงเรื่องที่ 5', 'step_6'=>'ตอบถึงเรื่องที่ 6',
    'step_7'=>'ตอบถึงเรื่องที่ 7', 'step_8'=>'ตอบถึงเรื่องที่ 8', 'step_9'=>'ตอบถึงเรื่องที่ 9', 'step_10'=>'ตอบถึงเรื่องที่ 10',
];

function anemiaActivitySummary(PDO $db, string $month, string $scope = '', array $params = []): array
{
    $begin = new DateTimeImmutable($month . '-01', new DateTimeZone('Asia/Bangkok'));
    $sql = 'SELECT e.event_name,e.outcome,COUNT(*) AS total FROM anemia_draft_activity e';
    if ($scope !== '') $sql .= ' JOIN anemia_province p ON p.code=e.province_code';
    $sql .= ' WHERE e.started_on>=? AND e.started_on<?';
    if ($scope !== '') $sql .= ' AND p.' . $scope;
    $query = $db->prepare($sql . ' GROUP BY e.event_name,e.outcome');
    $query->execute([$begin->format('Y-m-d'),$begin->modify('+1 month')->format('Y-m-d'),...$params]);
    $counts = array_fill_keys(array_keys(ANEMIA_ACTIVITY_LABELS),0);
    $outcomes = ['first'=>[], 'review_done'=>[]];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = $row['event_name'];
        if (!isset($counts[$key])) continue;
        $counts[$key] += (int)$row['total'];
        if (isset($outcomes[$key]) && $row['outcome'] !== null) $outcomes[$key][$row['outcome']] = (int)$row['total'];
    }
    return ['counts'=>$counts, 'outcomes'=>$outcomes];
}

function anemiaActivityInput(mixed $input): ?array
{
    if (!is_array($input)) return null;
    $id = $input['id'] ?? null;
    $event = $input['event'] ?? null;
    $outcome = $input['outcome'] ?? null;
    $province = $input['province'] ?? null;
    if (!is_string($id) || !preg_match('/^[0-9a-f]{32}$/D',$id)) return null;
    if (!is_string($event) || !isset(ANEMIA_ACTIVITY_LABELS[$event])) return null;
    if ($province !== null && (!is_string($province) || !preg_match('/^[0-9]{2}$/D',$province))) return null;
    if (in_array($event,['first','review_done'],true)) {
        if (!in_array($outcome,['low','medium','high','unknown'],true)) return null;
    } elseif ($outcome !== null) return null;
    return [$id,$event,$outcome,$province];
}
