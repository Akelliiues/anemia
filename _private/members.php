<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

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
