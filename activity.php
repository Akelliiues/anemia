<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET' && $method !== 'POST') { http_response_code(405); exit; }
if ($method === 'POST' &&
    (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin') !== 'same-origin' ||
     strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json')) {
    http_response_code(403); exit;
}
define('ANEMIA_STAFF_BOOT',true);
require __DIR__ . '/_private/activity.php';
date_default_timezone_set('Asia/Bangkok');
if ($method === 'POST') {
    $body = file_get_contents('php://input',false,null,0,1025);
    $data = is_string($body) && strlen($body) <= 1024 ? anemiaActivityInput(json_decode($body,true,5)) : null;
    if (!$data) { http_response_code(400); exit; }
}
try {
    $config = require __DIR__ . '/_private/config.php';
    require __DIR__ . '/_private/database.php';
    $db = anemiaDatabase($config);
    initializeAnemia($db,$config);
    if ($method === 'GET') {
        $month = date('Y-m');
        $region = filter_input(INPUT_GET,'region',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>13]]) ?: 0;
        $province = $_GET['province'] ?? '';
        $directory = require __DIR__ . '/_private/provinces.php';
        $provinceRegions = array_column($directory,2,0);
        if (!is_string($province) || ($province !== '' && (!isset($provinceRegions[$province]) || ($region && $provinceRegions[$province] !== $region)))) {
            http_response_code(400); exit;
        }
        $scope = $province !== '' ? 'code=?' : ($region ? 'region=?' : '');
        $params = $province !== '' ? [$province] : ($region ? [$region] : []);
        $counts = anemiaActivitySummary($db,$month,$scope,$params)['counts'];
        $sampleEnabled = (int)$db->query("SELECT enabled FROM anemia_host_setting WHERE setting_key='staff_sample'")->fetchColumn() === 1;
        $publish = static fn(int $count): ?int => $count >= 10 ? $count : null;
        echo json_encode(['sample_enabled'=>$sampleEnabled,'month'=>$month,
            'started'=>$publish($counts['start']), 'completed'=>$publish($counts['first']),
            'reviewed'=>$publish($counts['review_done']), 'directory'=>$publish($counts['directory'])],JSON_THROW_ON_ERROR);
        exit;
    }
    [$id,$event,$outcome,$province] = $data;
    if ($event === 'start') {
        $save = $db->prepare("INSERT INTO anemia_draft_activity (round_id,event_name,province_code,started_on)
            VALUES (?,'start',(SELECT code FROM anemia_province WHERE code=?),?)
            ON DUPLICATE KEY UPDATE round_id=round_id");
        $save->execute([$id,$province,date('Y-m-d')]);
    } else {
        $save = $db->prepare("INSERT IGNORE INTO anemia_draft_activity (round_id,event_name,outcome,province_code,started_on)
            SELECT source.round_id,?,?,source.province_code,source.started_on FROM anemia_draft_activity source
            WHERE source.round_id=? AND source.event_name='start'");
        $save->execute([$event,$outcome,$id]);
    }
    http_response_code(204);
} catch (Throwable $error) {
    error_log('Anemia activity failed (' . get_class($error) . ')');
    http_response_code(503);
}
