<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
if (!in_array($_SERVER['REQUEST_METHOD']??'',['GET','HEAD'],true)) { http_response_code(405); exit; }
define('ANEMIA_STAFF_BOOT',true);
require __DIR__.'/_private/database.php';
require __DIR__.'/_private/voice-coach.php';
try {
    $config=require __DIR__.'/_private/config.php';
    $db=anemiaDatabase($config);
    initializeAnemia($db,$config);
    anemiaEnsureVoiceCoach($db);
    if (isset($_GET['audio'])) {
        $slot=$_GET['audio'];
        if (!is_string($slot) || !isset(anemiaVoiceSlots()[$slot])) { http_response_code(404); exit; }
        anemiaVoiceStream($db,$slot,'published');
    }
    $rows=$db->query("SELECT slot,display_text,audio IS NOT NULL AS has_audio FROM anemia_voice_coach WHERE edition='published'")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: public, max-age=30');
    echo json_encode(['slots'=>(object)array_column($rows,null,'slot')],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(503);
    header('Cache-Control: no-store');
    error_log('Anemia Voice Coach: '.get_class($error));
}
