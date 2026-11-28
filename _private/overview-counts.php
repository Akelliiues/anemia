<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

function anemiaOverviewCounts(PDO $db, string $from, string $until): array
{
    $rows = [];
    $queries = [
        "SELECT DATE_FORMAT(e.started_on,'%Y-%m') AS period,p.region,e.outcome,COUNT(*) AS total
         FROM anemia_draft_activity e JOIN anemia_province p ON p.code=e.province_code
         WHERE e.started_on>=? AND e.started_on<? AND e.event_name='first'
         GROUP BY period,p.region,e.outcome",
        "SELECT DATE_FORMAT(q.submitted_at,'%Y-%m') AS period,p.region,COUNT(*) AS total,
         SUM(q.state IN ('test_completed','follow_up') OR c.receipt IS NOT NULL OR EXISTS
             (SELECT 1 FROM anemia_request_history h WHERE h.receipt=q.receipt AND h.state='test_completed')) AS tested
         FROM anemia_contact_queue q
         JOIN anemia_network_facility f ON f.facility_key=q.facility_key
         JOIN anemia_province p ON p.code=LEFT(f.district,2)
         LEFT JOIN anemia_request_confirmation c ON c.receipt=q.receipt
         WHERE q.submitted_at>=? AND q.submitted_at<?
         GROUP BY period,p.region",
    ];
    foreach ($queries as $source=>$sql) {
        $query=$db->prepare($sql);
        $query->execute([$from,$until]);
        while ($item=$query->fetch(PDO::FETCH_ASSOC)) {
            $region=(int)$item['region'];
            $key=$item['period'].':'.$region;
            $rows[$key]??=['period'=>$item['period'],'region'=>$region,'screened'=>0,'low'=>0,'medium'=>0,'high'=>0,'unknown'=>0,'referred'=>0,'tested'=>0];
            $row=&$rows[$key];
            if ($source===0) {
                $band=in_array($item['outcome'],['low','medium','high'],true)?$item['outcome']:'unknown';
                $row[$band]+=(int)$item['total'];
                $row['screened']+=(int)$item['total'];
            } else {
                $row['referred']=(int)$item['total'];
                $row['tested']=(int)$item['tested'];
            }
            unset($row);
        }
    }
    return array_values($rows);
}
