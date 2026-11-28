<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD']??'')!=='GET') { http_response_code(405); exit('{}'); }
define('ANEMIA_STAFF_BOOT',true);
require __DIR__.'/_private/database.php';
require __DIR__.'/_private/network.php';
try {
    $config=require __DIR__.'/_private/config.php';
    $db=anemiaDatabase($config);
    initializeAnemia($db,$config);
    anemiaEnsureBooking($db);
    $query=$db->query("SELECT f.facility_key,f.kind,".anemiaEffectiveHub('f')." AS effective_hub,
        ".anemiaBookingOwnerSql()." AS queue_owner,b.appointment,b.callback,b.paused_until
        FROM anemia_network_booking b JOIN anemia_network_facility f ON f.code=b.facility_code WHERE b.appointment=1 OR b.callback=1");
    $configured=array_column($query->fetchAll(PDO::FETCH_ASSOC),null,'facility_key');
    $available=[];
    $today=(new DateTimeImmutable('now',new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
    if ($configured) foreach (anemiaFacilityDirectory() as $index=>$facility) {
        $state=$configured[anemiaFacilityKey($facility)]??null;
        if (!$state) continue;
        $flags=anemiaBookingFlags($state,$today);
        if (in_array(true,$flags,true)) $available[$index]=$flags;
    }
    header('Cache-Control: public, max-age=30');
    echo json_encode(['facilities'=>(object)$available],JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(503);
    header('Cache-Control: no-store');
    error_log('Anemia booking availability: '.get_class($error));
    echo '{}';
}
