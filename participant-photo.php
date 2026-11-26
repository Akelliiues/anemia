<?php
declare(strict_types=1);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
if (($_SERVER['REQUEST_METHOD']??'')!=='GET' || (!$secure&&PHP_SAPI!=='cli-server') || !isset($_COOKIE['anemia_participant'])) {
    http_response_code(404); exit;
}
define('ANEMIA_STAFF_BOOT',true);
require __DIR__.'/_private/participant-account.php';
anemiaParticipantSession($secure);
$account=($_SESSION['until']??0)>=time()?($_SESSION['account']??null):null;
$epoch=(int)($_SESSION['epoch']??0);
session_write_close();
if (!$account) { http_response_code(404); exit; }
try {
    require __DIR__.'/_private/database.php';
    $config=require __DIR__.'/_private/config.php';
    $db=anemiaDatabase($config);
    initializeAnemia($db,$config);
    if (($_GET['session']??'')==='1') {
        http_response_code(anemiaParticipantEpoch($db,$account)===$epoch ? 204 : 404);
        exit;
    }
    $query=$db->prepare('SELECT p.photo FROM anemia_participant_photo p JOIN anemia_participant_account a ON a.id=p.account_id
        WHERE a.id=? AND a.suspended_at IS NULL AND a.session_epoch=?');
    $query->execute([$account,$epoch]);
    $photo=$query->fetchColumn();
    if (!is_string($photo)) { http_response_code(404); exit; }
    header('Content-Type: image/jpeg');
    echo $photo;
} catch (Throwable $error) {
    error_log('Anemia participant photo: '.get_class($error));
    http_response_code(503);
}
