<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/participant-account.php';

function anemiaSetDemoMemberNetwork(PDO $db,string $id,string $staff,bool $create): bool
{
    if (!preg_match('/^[a-f0-9]{32}$/D',$id)) throw new DomainException('ไม่พบรหัสสมาชิก');
    if ($create) {
        $query=$db->prepare('INSERT INTO anemia_demo_member_network(account_id,branches,created_by,created_at)
            SELECT id,?,?,? FROM anemia_participant_account WHERE id=?
            ON DUPLICATE KEY UPDATE account_id=VALUES(account_id)');
        $query->execute([json_encode([4,2,3,1,0],JSON_THROW_ON_ERROR),$staff,date('Y-m-d H:i:s'),$id]);
    } else {
        $query=$db->prepare('DELETE FROM anemia_demo_member_network WHERE account_id=?');
        $query->execute([$id]);
    }
    return $query->rowCount()>0;
}

function anemiaRenameMember(PDO $db,string $id,string $username,string $reason,string $staff): bool
{
    $username=strtolower(trim($username));
    $reason=trim($reason);
    if (!preg_match('/^[a-f0-9]{32}$/D',$id) || !preg_match('/^.{1,400}$/usD',$reason)) throw new DomainException('กรุณาระบุเหตุผล');
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,39}$/D',$username)) throw new DomainException('รูปแบบชื่อบัญชีไม่ถูกต้อง');
    if (anemiaReservedParticipantUsername($username)) throw new DomainException('ชื่อนี้สงวนไว้สำหรับระบบ');
    $db->beginTransaction();
    try {
        $lookup=$db->prepare('SELECT username FROM anemia_participant_password WHERE account_id=? FOR UPDATE');
        $lookup->execute([$id]);
        $old=$lookup->fetchColumn();
        if ($old===false) throw new DomainException('บัญชีนี้ยังไม่มีชื่อสำหรับเข้าสู่ระบบด้วย PIN');
        if ($old!==$username) {
            $db->prepare('UPDATE anemia_participant_password SET username=? WHERE account_id=?')->execute([$username,$id]);
            $db->prepare('INSERT INTO anemia_member_audit(account_id,actor,action_name,reason,created_at) VALUES (?,?,\'rename\',?,?)')
               ->execute([$id,$staff,$old.' → '.$username.' · '.$reason,date('Y-m-d H:i:s')]);
        }
        $db->commit();
        return $old!==$username;
    } catch (Throwable $error) {
        $db->rollBack();
        if ($error instanceof PDOException && (int)($error->errorInfo[1]??0)===1062) throw new DomainException('ชื่อบัญชีนี้ถูกใช้แล้ว');
        throw $error;
    }
}

function anemiaMemberListName(?string $username,string $id): string
{
    if ($username===null) return substr($id,0,12);
    $digits=preg_replace('/[._-]/','',$username);
    return preg_match('/^0[0-9]{9}$/D',$digits)?substr($digits,0,2).'X-XXX-'.substr($digits,-4):$username;
}

function anemiaSetMemberStatus(PDO $db,string $id,string $action,string $reason,string $staff): bool
{
    if (!preg_match('/^[a-f0-9]{32}$/D',$id) || !in_array($action,['suspend','restore'],true) ||
        preg_match('/^.{1,500}$/usD',$reason)!==1 || trim($reason)==='') throw new DomainException('กรุณาระบุเหตุผลและตรวจข้อมูลบัญชี');
    $db->beginTransaction();
    try {
        $suspend=$action==='suspend';
        $timestamp=date('Y-m-d H:i:s');
        $stateCheck=$suspend?'IS NULL':'IS NOT NULL';
        $save=$db->prepare("UPDATE anemia_participant_account SET suspended_at=?,session_epoch=session_epoch+1 WHERE id=? AND suspended_at $stateCheck");
        $save->execute([$suspend?$timestamp:null,$id]);
        $changed=$save->rowCount()===1;
        if ($changed) {
            $db->prepare('INSERT INTO anemia_member_audit(account_id,actor,action_name,reason,created_at) VALUES (?,?,?,?,?)')
               ->execute([$id,$staff,$action,trim($reason),$timestamp]);
        }
        $db->commit();
        return $changed;
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}
