<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

function anemiaParticipantSession(bool $secure): void
{
    ini_set('session.use_strict_mode','1');
    session_name('anemia_participant');
    session_set_cookie_params(['secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}

function anemiaTeamCookieHash(): ?string
{
    $cookie=$_COOKIE['anemia_team']??null;
    return is_string($cookie)&&preg_match('/^[a-f0-9]{64}$/D',$cookie)?hash('sha256',$cookie):null;
}

function anemiaConnectParticipant(PDO $db,string $provider,string $subject,?string $active,?string $teamCookieHash): string
{
    $subjectHash=hash('sha256',$subject);
    $db->beginTransaction();
    try {
        $query=$db->prepare('SELECT account_id FROM anemia_participant_identity WHERE provider=? AND subject_hash=? FOR UPDATE');
        $query->execute([$provider,$subjectHash]);
        $found=$query->fetchColumn()?:null;
        if ($found && $active && $active!==$found) throw new DomainException('ช่องทางนี้เชื่อมกับบัญชีอื่นอยู่แล้ว');
        $account=$found??$active;
        if ($account===null) {
            $account=bin2hex(random_bytes(16));
            $db->prepare('INSERT INTO anemia_participant_account(id,connected_at) VALUES (?,NOW())')->execute([$account]);
        }
        $lock=$db->prepare('SELECT id FROM anemia_participant_account WHERE id=? FOR UPDATE');
        $lock->execute([$account]);
        if (!$lock->fetchColumn()) throw new DomainException('ไม่พบบัญชี กรุณาเข้าสู่ระบบใหม่');
        if (!$found) {
            $check=$db->prepare('SELECT 1 FROM anemia_participant_identity WHERE account_id=? AND provider=?');
            $check->execute([$account,$provider]);
            if ($check->fetchColumn()) throw new DomainException('บัญชีนี้เชื่อมช่องทางดังกล่าวแล้ว');
            $db->prepare('INSERT INTO anemia_participant_identity VALUES (?,?,?,NOW())')->execute([$provider,$subjectHash,$account]);
        }
        if ($teamCookieHash) {
            $db->prepare('UPDATE anemia_participant_account a
                JOIN anemia_demo_team_member m ON m.cookie_hash=?
                LEFT JOIN anemia_participant_account owner ON owner.team_member_id=m.id
                SET a.team_member_id=m.id
                WHERE a.id=? AND a.team_member_id IS NULL AND owner.id IS NULL')->execute([$teamCookieHash,$account]);
        }
        $db->commit();
        return $account;
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}
