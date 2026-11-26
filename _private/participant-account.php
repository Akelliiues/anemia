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

function anemiaAttachTeam(PDO $db,string $account,?string $teamCookieHash): void
{
    if (!$teamCookieHash) return;
    $db->prepare('UPDATE anemia_participant_account a
        JOIN anemia_demo_team_member m ON m.cookie_hash=?
        LEFT JOIN anemia_participant_account owner ON owner.team_member_id=m.id
        SET a.team_member_id=m.id
        WHERE a.id=? AND a.team_member_id IS NULL AND owner.id IS NULL')->execute([$teamCookieHash,$account]);
}

function anemiaReservedParticipantUsername(string $username): bool
{
    $reserved=['admin','administrator','admins','administrators','sysadmin','superadmin','system','root','staff','support',
        'moderator','webmaster','owner','official','anemia','anemiaportal','anemiastaff','anemiaadmin','portaladmin',
        'security','postmaster','noreply'];
    $extra=preg_split('/[\s,]+/',getenv('ANEMIA_RESERVED_PARTICIPANT_USERNAMES')?:'',-1,PREG_SPLIT_NO_EMPTY);
    $normalize=static fn(string $name): string => preg_replace('/[._-]/','',strtolower($name));
    $name=$normalize($username);
    $withoutSuffix=preg_replace('/\d+$/D','',$name);
    foreach (array_merge($reserved,$extra) as $entry) {
        $key=$normalize($entry);
        if ($key!=='' && ($name===$key || $withoutSuffix===$key)) return true;
    }
    return false;
}

function anemiaParticipantPassword(PDO $db,bool $register,array $input,?string $teamCookieHash): string
{
    $username=is_string($input['username']??null)?strtolower(trim($input['username'])):'';
    $password=$input['password']??null;
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,39}$/D',$username) || !is_string($password) || $password==='') {
        throw new DomainException($register?'ตรวจชื่อบัญชีและความยาวรหัสผ่าน':'ชื่อบัญชีหรือรหัสผ่านไม่ถูกต้อง');
    }
    if (!$register) {
        $query=$db->prepare('SELECT account_id,password_hash FROM anemia_participant_password WHERE username=?');
        $query->execute([$username]);
        $row=$query->fetch(PDO::FETCH_ASSOC);
        $hash=$row['password_hash']??'$2y$12$CkxVzYqDSRWD7wAxUXU4ou5jz8lPTZuoSJb1ZGzAz3Rgi7PDgtCL2';
        $prehashed=str_starts_with($hash,'sha384:');
        $material=$prehashed?base64_encode(hash('sha384',$password,true)):$password;
        if ((!$prehashed && (strlen($material)>72 || str_contains($material,"\0"))) ||
            !password_verify($material,$prehashed?substr($hash,7):$hash) || !$row) {
            throw new DomainException('ชื่อบัญชีหรือรหัสผ่านไม่ถูกต้อง');
        }
        return $row['account_id'];
    }
    if (anemiaReservedParticipantUsername($username)) throw new DomainException('ชื่อนี้สงวนไว้สำหรับระบบ กรุณาเลือกชื่อบัญชีอื่น');
    if (preg_match('/^.{6,}$/usD',$password)!==1) throw new DomainException('ตั้งรหัสผ่านอย่างน้อย 6 ตัวอักษร');
    if ($password!==($input['confirm_password']??null)) throw new DomainException('รหัสผ่านทั้งสองช่องไม่ตรงกัน');
    // Fixed-length input prevents bcrypt from truncating longer passwords.
    $hash='sha384:'.password_hash(base64_encode(hash('sha384',$password,true)),PASSWORD_BCRYPT,['cost'=>12]);
    $account=bin2hex(random_bytes(16));
    $db->beginTransaction();
    try {
        $db->prepare('INSERT INTO anemia_participant_account(id,connected_at) VALUES (?,NOW())')->execute([$account]);
        $db->prepare('INSERT INTO anemia_participant_password VALUES (?,?,?)')->execute([$username,$account,$hash]);
        anemiaAttachTeam($db,$account,$teamCookieHash);
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        if ($error instanceof PDOException && (int)($error->errorInfo[1]??0)===1062) throw new DomainException('ชื่อบัญชีนี้ถูกใช้แล้ว กรุณาเลือกชื่ออื่น');
        throw $error;
    }
    return $account;
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
        anemiaAttachTeam($db,$account,$teamCookieHash);
        $db->commit();
        return $account;
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}
