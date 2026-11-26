<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

require_once __DIR__.'/activity.php';
require_once __DIR__.'/evaluation.php';
require_once __DIR__.'/sample-reports.php';
require_once __DIR__.'/request-states.php';

function anemiaDemoReport(PDO $db, string $scope, string $month): array
{
    $query = $db->prepare('SELECT report_data FROM anemia_demo_report WHERE scope_key=? AND month_key=?');
    $query->execute([$scope,$month]);
    return json_decode((string)$query->fetchColumn(),true) ?? [];
}

function anemiaManageDemoData(PDO $db, string $action, string $actor): void
{
    $db->beginTransaction();
    try {
        $generate = $action === 'generate';
        $settings = $db->prepare("INSERT INTO anemia_host_setting (setting_key,enabled,changed_by,changed_at)
            VALUES ('staff_sample',?,?,?),('staff_sample_all_regions',?,?,?)
            ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),changed_by=VALUES(changed_by),changed_at=VALUES(changed_at)");
        $now = new DateTimeImmutable('now',new DateTimeZone('Asia/Bangkok'));
        $timestamp = $now->format('Y-m-d H:i:s');
        $settings->execute([(int)$generate,$actor,$timestamp,(int)$generate,$actor,$timestamp]);
        $db->exec('DELETE FROM anemia_demo_report');
        if (!$generate) {
            $db->exec('DELETE FROM anemia_contact_demo_queue');
            $db->exec("DELETE FROM anemia_request_history WHERE receipt LIKE 'DE-%'");
            $db->exec("DELETE FROM anemia_request_confirmation WHERE receipt LIKE 'DE-%'");
            $db->exec('DELETE FROM anemia_demo_team_bonus');
            $db->exec('UPDATE anemia_participant_account SET team_member_id=NULL WHERE team_member_id IS NOT NULL');
            $db->exec('DELETE FROM anemia_demo_team_member');
            $db->exec('DELETE FROM anemia_demo_member_network');
            $db->commit();
            return;
        }

        $provinces = $db->query('SELECT code,name,region,enabled FROM anemia_province ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);
        $scopes = ['all'=>$provinces];
        foreach ($provinces as $province) {
            $scopes['p:'.$province['code']] = [$province];
            $scopes['r:'.$province['region']][] = $province;
        }
        $save = $db->prepare('INSERT INTO anemia_demo_report (scope_key,month_key,report_data) VALUES (?,?,?)');
        $first = $now->modify('first day of this month');
        for ($offset=0; $offset<6; $offset++) {
            $month = $first->modify("-$offset months")->format('Y-m');
            foreach ($scopes as $scope=>$rows) {
                $evaluations = [];
                foreach (array_merge([''],array_keys(EVALUATION_AUDIENCES)) as $audience) {
                    $evaluations[$audience] = anemiaSampleEvaluation($rows,$month,$audience);
                }
                $report = ['activity'=>anemiaSampleActivity($rows,$month),'evaluation'=>$evaluations];
                $save->execute([$scope,$month,json_encode($report,JSON_THROW_ON_ERROR)]);
            }
        }
        $save = $db->prepare("INSERT INTO anemia_contact_demo_queue
            (receipt,facility,request_kind,contact_name,phone,preferred_date,submitted_at,state)
            VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE receipt=receipt");
        $states = array_keys(ANEMIA_REQUEST_STATES);
        foreach ($provinces as $index=>$province) {
            $save->execute([
                sprintf('DE-%016X',$index+1),'หน่วยบริการตัวอย่าง จ.'.$province['name'],
                $index%2===0?'appointment':'callback','ผู้ทดสอบ '.($index+1),'0000000000',
                $now->modify('+3 days')->format('Y-m-d'),$timestamp,$states[$index%count($states)]
            ]);
        }
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}
