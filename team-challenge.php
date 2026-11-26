<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function teamReply(int $status,array $body): never
{
    http_response_code($status);
    echo json_encode($body,JSON_THROW_ON_ERROR);
    exit;
}

$method=$_SERVER['REQUEST_METHOD']??'';
if (!in_array($method,['GET','POST'],true)) teamReply(405,[]);
$secure=!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
if (!$secure && PHP_SAPI!=='cli-server') teamReply(403,[]);
$data=[];
if ($method==='POST') {
    if (($_SERVER['HTTP_SEC_FETCH_SITE']??'same-origin')!=='same-origin' ||
        strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json') teamReply(403,[]);
    $data=json_decode((string)file_get_contents('php://input',false,null,0,1024),true);
    if (!is_array($data) || !in_array($data['action']??'',['join','complete'],true)) teamReply(422,[]);
}

define('ANEMIA_STAFF_BOOT',true);
require __DIR__.'/_private/participant-account.php';
anemiaParticipantSession($secure);
$account=($_SESSION['until']??0)>=time()?($_SESSION['account']??null):null;
$epoch=(int)($_SESSION['epoch']??0);
session_write_close();
try {
    $config=require __DIR__.'/_private/config.php';
    require __DIR__.'/_private/database.php';
    $db=anemiaDatabase($config);
    initializeAnemia($db,$config);
    $db->beginTransaction();
    if ($account!==null && anemiaParticipantEpoch($db,$account)!==$epoch) {
        $db->rollBack(); teamReply(403,['error'=>'เซสชันบัญชีสิ้นสุดแล้ว กรุณาเข้าสู่ระบบใหม่']);
    }
    if ((int)$db->query("SELECT enabled FROM anemia_host_setting WHERE setting_key='staff_sample' LOCK IN SHARE MODE")->fetchColumn()!==1) {
        $db->rollBack(); teamReply(200,['enabled'=>false,'connected'=>$account!==null]);
    }
    $cookie=$_COOKIE['anemia_team']??'';
    $cookie=is_string($cookie)&&preg_match('/^[a-f0-9]{64}$/D',$cookie)?$cookie:'';
    $member=null;
    if ($account!==null) {
        $lookup=$db->prepare('SELECT m.id,m.team_id,m.invited_by,m.completed_at FROM anemia_participant_account a
            LEFT JOIN anemia_demo_team_member m ON m.id=a.team_member_id WHERE a.id=? AND a.suspended_at IS NULL AND a.session_epoch=? FOR UPDATE');
        $lookup->execute([$account,$epoch]);
        $row=$lookup->fetch(PDO::FETCH_ASSOC);
        if (!$row) { $db->rollBack(); teamReply(403,['error'=>'เซสชันบัญชีสิ้นสุดแล้ว กรุณาเข้าสู่ระบบใหม่']); }
        $member=!empty($row['id'])?$row:null;
    } elseif ($cookie!=='') {
        $lookup=$db->prepare('SELECT m.id,m.team_id,m.invited_by,m.completed_at FROM anemia_demo_team_member m
            WHERE m.cookie_hash=? AND NOT EXISTS (SELECT 1 FROM anemia_participant_account a WHERE a.team_member_id=m.id)');
        $lookup->execute([hash('sha256',$cookie)]);
        $member=$lookup->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $action=$data['action']??'';
    if (!$member && $action==='join') {
        $inviter=$data['invite']??'';
        $parent=null;
        if ($inviter!=='') {
            if (!is_string($inviter)||!preg_match('/^[a-f0-9]{32}$/D',$inviter)) { $db->rollBack(); teamReply(422,['error'=>'ลิงก์ชวนไม่ถูกต้อง']); }
            $lookup=$db->prepare('SELECT id,team_id FROM anemia_demo_team_member WHERE id=?');
            $lookup->execute([$inviter]);
            $parent=$lookup->fetch(PDO::FETCH_ASSOC);
            if (!$parent) { $db->rollBack(); teamReply(422,['error'=>'ลิงก์ชวนนี้หมดอายุ']); }
        }
        $cookie=bin2hex(random_bytes(32));
        $id=bin2hex(random_bytes(16));
        $member=['id'=>$id,'team_id'=>$parent['team_id']??$id,'invited_by'=>$parent['id']??null,'completed_at'=>null];
        $db->prepare('INSERT INTO anemia_demo_team_member(id,cookie_hash,team_id,invited_by) VALUES(?,?,?,?)')
           ->execute([$id,hash('sha256',$cookie),$member['team_id'],$member['invited_by']]);
        if ($account!==null) {
            $db->prepare('UPDATE anemia_participant_account SET team_member_id=? WHERE id=?')->execute([$id,$account]);
        } else {
            setcookie('anemia_team',$cookie,['expires'=>time()+86400*90,'secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
        }
    }
    if ($member && $action==='complete') {
        if (($data['answer']??'')!=='facilities') { $db->rollBack(); teamReply(422,['error'=>'ลองเลือกคำตอบอีกครั้ง']); }
        $lock=$db->prepare('SELECT id FROM anemia_demo_team_member WHERE id=? FOR UPDATE');
        $lock->execute([$member['team_id']]);
        $complete=$db->prepare('UPDATE anemia_demo_team_member SET completed_at=NOW() WHERE id=? AND completed_at IS NULL');
        $complete->execute([$member['id']]);
        if ($complete->rowCount()===1 && $member['invited_by']) {
            $bonuses=$db->prepare('SELECT learner FROM anemia_demo_team_bonus WHERE inviter=? FOR UPDATE');
            $bonuses->execute([$member['invited_by']]);
            if (count($bonuses->fetchAll(PDO::FETCH_COLUMN))<3) {
                $db->prepare('INSERT INTO anemia_demo_team_bonus VALUES (?,?)')->execute([$member['id'],$member['invited_by']]);
            }
        }
        $member['completed_at']=true;
    }
    $linked=false;
    if ($account!==null) {
        $identity=$db->prepare('SELECT 1 FROM anemia_participant_identity WHERE account_id=? LIMIT 1');
        $identity->execute([$account]);
        $linked=(bool)$identity->fetchColumn();
    }
    $result=['enabled'=>true,'joined'=>$member!==null,'connected'=>$account!==null,'connectionPoints'=>$linked?5:0];
    if ($member) {
        $count=$db->prepare('SELECT COUNT(*) FROM anemia_demo_team_member WHERE team_id=? AND completed_at IS NOT NULL');
        $count->execute([$member['team_id']]);
        $completed=(int)$count->fetchColumn();
        $count=$db->prepare('SELECT COUNT(*) FROM anemia_demo_team_bonus WHERE inviter=?');
        $count->execute([$member['id']]);
        $bonus=(int)$count->fetchColumn()*5;
        $result+=['invite'=>$member['id'],'finished'=>(bool)$member['completed_at'],'completed'=>$completed,'bonus'=>$bonus,
            'points'=>$bonus+($member['completed_at']?10:0)+$result['connectionPoints']];
    }
    $db->commit();
    teamReply(200,$result);
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Anemia team challenge: '.get_class($error));
    teamReply(503,['error'=>'เปิดกิจกรรมไม่ได้ กรุณาลองอีกครั้ง']);
}
