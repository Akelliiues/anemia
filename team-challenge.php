<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
const ANEMIA_REFERRAL_POINTS=5;
const ANEMIA_REFERRAL_LIMIT=3;
const ANEMIA_TEAM_LESSON_POINTS=10;

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
$action=$data['action']??'';
if ($account===null) {
    if ($action==='join') teamReply(401,['error'=>'เข้าสู่ระบบหรือสมัครบัญชีก่อนเข้าทีม']);
    teamReply(200,['enabled'=>true,'joined'=>false,'connected'=>false,'connectionPoints'=>0]);
}
try {
    $config=require __DIR__.'/_private/config.php';
    require __DIR__.'/_private/database.php';
    $db=anemiaDatabase($config);
    initializeAnemia($db,$config);
    $db->beginTransaction();
    $lookup=$db->prepare('SELECT id FROM anemia_participant_account WHERE id=? AND suspended_at IS NULL AND session_epoch=? FOR UPDATE');
    $lookup->execute([$account,$epoch]);
    if (!$lookup->fetchColumn()) {
        $db->rollBack(); teamReply(403,['error'=>'เซสชันบัญชีสิ้นสุดแล้ว กรุณาเข้าสู่ระบบใหม่']);
    }
    $lookup=$db->prepare('SELECT id,team_id,invited_by,completed_at FROM anemia_team_member WHERE account_id=?');
    $lookup->execute([$account]);
    $member=$lookup->fetch(PDO::FETCH_ASSOC)?:null;
    if (!$member && $action==='join') {
        $inviter=$data['invite']??'';
        $parent=null;
        if ($inviter!=='') {
            if (!is_string($inviter)||!preg_match('/^[a-f0-9]{32}$/D',$inviter)) { $db->rollBack(); teamReply(422,['error'=>'ลิงก์ชวนไม่ถูกต้อง']); }
            $lookup=$db->prepare('SELECT m.id,m.team_id FROM anemia_team_member m JOIN anemia_participant_account a ON a.id=m.account_id
                WHERE m.id=? AND a.suspended_at IS NULL AND a.id<>?');
            $lookup->execute([$inviter,$account]);
            $parent=$lookup->fetch(PDO::FETCH_ASSOC);
            if (!$parent) { $db->rollBack(); teamReply(422,['error'=>'ลิงก์ชวนนี้หมดอายุ']); }
        }
        $id=bin2hex(random_bytes(16));
        $member=['id'=>$id,'team_id'=>$parent['team_id']??$id,'invited_by'=>$parent['id']??null,'completed_at'=>null];
        $db->prepare('INSERT INTO anemia_team_member(account_id,id,team_id,invited_by) VALUES(?,?,?,?)')
           ->execute([$account,$id,$member['team_id'],$member['invited_by']]);
    }
    if ($member && $action==='complete') {
        if (($data['answer']??'')!=='facilities') { $db->rollBack(); teamReply(422,['error'=>'ลองเลือกคำตอบอีกครั้ง']); }
        $lock=$db->prepare('SELECT id FROM anemia_team_member WHERE id=? FOR UPDATE');
        $lock->execute([$member['team_id']]);
        $complete=$db->prepare('UPDATE anemia_team_member SET completed_at=NOW() WHERE id=? AND completed_at IS NULL');
        $complete->execute([$member['id']]);
        if ($complete->rowCount()===1 && $member['invited_by']) {
            $bonuses=$db->prepare('SELECT learner FROM anemia_team_bonus WHERE inviter=? FOR UPDATE');
            $bonuses->execute([$member['invited_by']]);
            if (count($bonuses->fetchAll(PDO::FETCH_COLUMN))<ANEMIA_REFERRAL_LIMIT) {
                $db->prepare('INSERT INTO anemia_team_bonus VALUES (?,?)')->execute([$member['id'],$member['invited_by']]);
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
        $count=$db->prepare('SELECT COUNT(*) FROM anemia_team_member WHERE team_id=? AND completed_at IS NOT NULL');
        $count->execute([$member['team_id']]);
        $completed=(int)$count->fetchColumn();
        $count=$db->prepare('SELECT COUNT(*) FROM anemia_team_bonus WHERE inviter=?');
        $count->execute([$member['id']]);
        $awards=(int)$count->fetchColumn();
        $bonus=$awards*ANEMIA_REFERRAL_POINTS;
        $count=$db->prepare('SELECT COUNT(*) FROM anemia_team_member WHERE invited_by=?');
        $count->execute([$member['id']]);
        $directCount=(int)$count->fetchColumn();
        $branches=$db->prepare('SELECT (SELECT COUNT(*) FROM anemia_team_member child WHERE child.invited_by=direct.id)
            FROM anemia_team_member direct WHERE direct.invited_by=? ORDER BY direct.id LIMIT 8');
        $branches->execute([$member['id']]);
        $result+=['invite'=>$member['id'],'finished'=>(bool)$member['completed_at'],'completed'=>$completed,'bonus'=>$bonus,
            'points'=>$bonus+($member['completed_at']?ANEMIA_TEAM_LESSON_POINTS:0)+$result['connectionPoints'],
            'rules'=>['perFriend'=>ANEMIA_REFERRAL_POINTS,'limit'=>ANEMIA_REFERRAL_LIMIT,'lesson'=>ANEMIA_TEAM_LESSON_POINTS,
                'remaining'=>max(0,ANEMIA_REFERRAL_LIMIT-$awards)],
            'network'=>['direct'=>$directCount,'branches'=>array_map('intval',$branches->fetchAll(PDO::FETCH_COLUMN))]];
    }
    $db->commit();
    teamReply(200,$result);
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Anemia team challenge: '.get_class($error));
    teamReply(503,['error'=>'เปิดกิจกรรมไม่ได้ กรุณาลองอีกครั้ง']);
}
