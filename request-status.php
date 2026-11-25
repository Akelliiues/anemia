<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
    ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin') !== 'same-origin' ||
    strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    http_response_code(403); exit;
}
$body=json_decode((string)file_get_contents('php://input',false,null,0,512),true,4);
$receipt=$body['receipt']??null;
if (!is_string($receipt) || !preg_match('/^(?:AN|DE)-[0-9A-F]{16}$/D',$receipt)) { http_response_code(400); exit; }

define('ANEMIA_STAFF_BOOT',true);
require __DIR__.'/_private/request-states.php';
try {
    $config=require __DIR__.'/_private/config.php';
    require __DIR__.'/_private/database.php';
    $db=anemiaDatabase($config);
    initializeAnemia($db,$config);
    $sample=str_starts_with($receipt,'DE-');
    $table=$sample?'anemia_contact_demo_queue':'anemia_contact_queue';
    $query=$db->prepare('SELECT state,appointment_at FROM '.$table.' WHERE receipt=?');
    $query->execute([$receipt]);
    $request=$query->fetch(PDO::FETCH_ASSOC);
    $state=$request['state']??null;
    if (!is_string($state) || !isset(ANEMIA_REQUEST_STATES[$state])) { http_response_code(404); exit; }
    echo json_encode(['state'=>$state,'label'=>ANEMIA_REQUEST_STATES[$state],'sample'=>$sample,
        'appointmentAt'=>$state==='scheduled' && $request['appointment_at'] ? str_replace(' ','T',$request['appointment_at']).'+07:00' : null,
        'checkedAt'=>gmdate('Y-m-d\TH:i:s\Z')],JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    error_log('Anemia receipt status failed ('.get_class($error).')');
    http_response_code(503);
}
