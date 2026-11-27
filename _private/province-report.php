<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

const ANEMIA_PROVINCE_METRICS=[
    'started'=>'เริ่มคัดกรอง','completed'=>'ทำรอบแรกครบ','low'=>'เสี่ยงต่ำ','medium'=>'เสี่ยงปานกลาง',
    'high'=>'เสี่ยงสูง','unknown'=>'ยังสรุปไม่ได้','requested'=>'ส่งคำขอ','attended'=>'มารับบริการ',
    'tested'=>'ตรวจแล้ว','confirmed'=>'ผลยืนยันโลหิตจาง',
];

function anemiaProvinceReport(PDO $db,array $provinces,string $month,bool $sample): array
{
    $rows=[];
    foreach ($provinces as $province) {
        $rows[$province['code']]=$province+array_fill_keys(array_keys(ANEMIA_PROVINCE_METRICS),0);
    }
    if (!$rows) return [];
    if ($sample) {
        require_once __DIR__.'/activity.php';
        require_once __DIR__.'/sample-reports.php';
        foreach ($rows as &$row) {
            $activity=anemiaSampleActivity([$row],$month);
            $row=array_replace($row,$activity['outcomes']['first'],[
                'started'=>$activity['counts']['start'],'completed'=>$activity['counts']['first'],
            ]);
            $row['requested']=intdiv($row['high'],3);
            $row['attended']=intdiv($row['requested']*7,10);
            $row['tested']=intdiv($row['attended']*8,10);
            $row['confirmed']=intdiv($row['tested']*3,10);
        }
        unset($row);
        return $rows;
    }

    $begin=new DateTimeImmutable($month.'-01',new DateTimeZone('Asia/Bangkok'));
    $codes=array_keys($rows);
    $placeholders=implode(',',array_fill(0,count($codes),'?'));
    $params=[$begin->format('Y-m-d'),$begin->modify('+1 month')->format('Y-m-d'),...$codes];
    $activity=$db->prepare("SELECT province_code,event_name,outcome,COUNT(*) AS total
        FROM anemia_draft_activity WHERE started_on>=? AND started_on<?
        AND province_code IN ($placeholders) AND event_name IN ('start','first')
        GROUP BY province_code,event_name,outcome");
    $activity->execute($params);
    foreach ($activity->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $row=&$rows[$item['province_code']];
        $total=(int)$item['total'];
        if ($item['event_name']==='start') {
            $row['started']+=$total;
        } else {
            $row['completed']+=$total;
            $band=in_array($item['outcome'],['low','medium','high','unknown'],true)?$item['outcome']:'unknown';
            $row[$band]+=$total;
        }
        unset($row);
    }
    $queue=$db->prepare("SELECT LEFT(f.district,2) AS province_code, COUNT(*) AS requested,
        SUM(q.state IN ('attended','test_completed','follow_up') OR c.receipt IS NOT NULL OR EXISTS
            (SELECT 1 FROM anemia_request_history h WHERE h.receipt=q.receipt AND h.state IN ('attended','test_completed'))) AS attended,
        SUM(q.state IN ('test_completed','follow_up') OR c.receipt IS NOT NULL OR EXISTS
            (SELECT 1 FROM anemia_request_history h WHERE h.receipt=q.receipt AND h.state='test_completed')) AS tested,
        SUM(c.result_code='confirmed') AS confirmed
        FROM anemia_contact_queue q
        JOIN anemia_network_facility f ON f.facility_key=q.facility_key
        LEFT JOIN anemia_request_confirmation c ON c.receipt=q.receipt
        WHERE q.submitted_at>=? AND q.submitted_at<? AND LEFT(f.district,2) IN ($placeholders)
        GROUP BY LEFT(f.district,2)");
    $queue->execute($params);
    foreach ($queue->fetchAll(PDO::FETCH_ASSOC) as $item) {
        foreach (['requested','attended','tested','confirmed'] as $key) {
            $rows[$item['province_code']][$key]=(int)$item[$key];
        }
    }
    return $rows;
}
