<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

const ANEMIA_PROFILE_AVATARS=['drop'=>'หยดเลือด','leaf'=>'ใบไม้','star'=>'ดาว','flower'=>'ดอกไม้'];
const ANEMIA_PROFILE_STATUSES=[''=>'ไม่แสดงสถานะ','learning'=>'กำลังเรียนรู้','mission'=>'กำลังทำภารกิจ','invite'=>'พร้อมชวนเพื่อน'];

function anemiaParticipantActivityDate(string $value): string
{
    $date=new DateTimeImmutable($value);
    $months=['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    return $date->format('j').' '.$months[(int)$date->format('n')-1].' '.((int)$date->format('Y')+543);
}

function anemiaParticipantActivity(PDO $db,string $account): array
{
    $events=[];
    $query=$db->prepare('SELECT connected_at FROM anemia_participant_account WHERE id=?');
    $query->execute([$account]);
    $created=$query->fetchColumn();
    if ($created) $events[]=['title'=>'สร้างบัญชี Anemia Portal','icon'=>'person','date'=>$created,'points'=>0];
    $query=$db->prepare('SELECT provider,connected_at FROM anemia_participant_identity WHERE account_id=? ORDER BY connected_at,provider');
    $query->execute([$account]);
    $labels=['line'=>'LINE','google'=>'Google','facebook'=>'Facebook'];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $index=>$identity) {
        $events[]=['title'=>'เชื่อมบัญชี '.($labels[$identity['provider']]??'ภายนอก'),'icon'=>'ready','date'=>$identity['connected_at'],'points'=>$index===0?5:0];
    }
    $query=$db->prepare('SELECT id,team_id,completed_at FROM anemia_team_member WHERE account_id=?');
    $query->execute([$account]);
    $team=$query->fetch(PDO::FETCH_ASSOC);
    $milestone=0;
    if ($team) {
        if ($team['completed_at']) $events[]=['title'=>'เรียนจบบทแรก','icon'=>'book','date'=>$team['completed_at'],'points'=>10];
        $query=$db->prepare('SELECT m.completed_at FROM anemia_team_bonus b JOIN anemia_team_member m ON m.id=b.learner WHERE b.inviter=?');
        $query->execute([$team['id']]);
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $date) {
            $events[]=['title'=>'เพื่อนที่ชวนเรียนจบบทแรก','icon'=>'users','date'=>$date,'points'=>5];
        }
        $query=$db->prepare('SELECT COUNT(*) FROM anemia_team_member WHERE team_id=? AND completed_at IS NOT NULL');
        $query->execute([$team['team_id']]);
        $milestone=(int)$query->fetchColumn();
    }
    $query=$db->prepare("SELECT week_no,MIN(completed_at) AS completed_at FROM anemia_participant_programme_progress
        WHERE account_id=? AND programme_version LIKE 'school-%' GROUP BY week_no");
    $query->execute([$account]);
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $week) {
        $events[]=['title'=>'ผ่านกิจกรรมโรงเรียน สัปดาห์ที่ '.$week['week_no'],'icon'=>'book','date'=>$week['completed_at'],'points'=>1];
    }
    usort($events,static fn(array $a,array $b): int=>strcmp($b['date'],$a['date']));
    return ['events'=>$events,'points'=>array_sum(array_column($events,'points')),'joined'=>(bool)$team,'team_finished'=>$milestone];
}

require_once __DIR__.'/profile-photo.php';

function anemiaSaveParticipantProfile(PDO $db,string $account,int $epoch,array $input): void
{
    $name=$input['display_name']??null;
    $avatar=$input['avatar']??null;
    $status=$input['profile_status']??null;
    if (!is_string($name) || preg_match('/^[^\p{C}\r\n]{0,40}$/uD',trim($name))!==1 ||
        !is_string($avatar) || !isset(ANEMIA_PROFILE_AVATARS[$avatar]) ||
        !is_string($status) || !isset(ANEMIA_PROFILE_STATUSES[$status])) {
        throw new DomainException('ตรวจชื่อที่แสดง ไอคอน และสถานะอีกครั้ง');
    }
    $save=$db->prepare('INSERT INTO anemia_participant_profile(account_id,display_name,avatar,profile_status)
        SELECT id,?,?,? FROM anemia_participant_account WHERE id=? AND suspended_at IS NULL AND session_epoch=?
        ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),avatar=VALUES(avatar),profile_status=VALUES(profile_status)');
    $save->execute([trim($name),$avatar,$status,$account,$epoch]);
}

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

function anemiaParticipantEpoch(PDO $db,string $account): ?int
{
    $query=$db->prepare('SELECT session_epoch FROM anemia_participant_account WHERE id=? AND suspended_at IS NULL');
    $query->execute([$account]);
    $epoch=$query->fetchColumn();
    return $epoch===false?null:(int)$epoch;
}

function anemiaStartParticipantSession(PDO $db,string $account): void
{
    $epoch=anemiaParticipantEpoch($db,$account);
    if ($epoch===null) throw new DomainException('บัญชีนี้ถูกระงับ กรุณาติดต่อผู้ดูแลโครงการ');
    $schoolInvite=$_SESSION['school_invite']??null;
    session_regenerate_id(true);
    $_SESSION=['account'=>$account,'epoch'=>$epoch,'until'=>time()+28800,'csrf'=>bin2hex(random_bytes(32))];
    if ($schoolInvite!==null) $_SESSION['school_invite']=$schoolInvite;
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

function anemiaParticipantPassword(PDO $db,bool $register,array $input,?string $teamCookieHash,bool $migrate=false): string
{
    $username=is_string($input['username']??null)?strtolower(trim($input['username'])):'';
    $password=$input['password']??null;
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,39}$/D',$username) || !is_string($password) || $password==='') {
        throw new DomainException($register?'ตรวจชื่อบัญชีและ PIN':'ข้อมูลเข้าสู่ระบบไม่ถูกต้อง');
    }
    $pin=$migrate?($input['new_pin']??null):$password;
    if (!is_string($pin) || preg_match('/^[0-9]{6}$/D',$pin)!==1) throw new DomainException('กรุณากรอก PIN เป็นตัวเลข 6 หลัก');
    if (($register||$migrate) && $pin!==($input['confirm_password']??null)) throw new DomainException('PIN ทั้งสองช่องไม่ตรงกัน');
    if (!$register) {
        $query=$db->prepare('SELECT p.account_id,p.password_hash,a.suspended_at FROM anemia_participant_password p JOIN anemia_participant_account a ON a.id=p.account_id WHERE p.username=?');
        $query->execute([$username]);
        $row=$query->fetch(PDO::FETCH_ASSOC);
        $hash=$row['password_hash']??'$2y$12$CkxVzYqDSRWD7wAxUXU4ou5jz8lPTZuoSJb1ZGzAz3Rgi7PDgtCL2';
        $prehashed=str_starts_with($hash,'sha384:');
        $material=$prehashed?base64_encode(hash('sha384',$password,true)):$password;
        if ((!$prehashed && (strlen($material)>72 || str_contains($material,"\0"))) ||
            !password_verify($material,$prehashed?substr($hash,7):$hash) || !$row) {
            throw new DomainException($migrate?'ชื่อบัญชีหรือรหัสผ่านเดิมไม่ถูกต้อง':'ชื่อบัญชีหรือ PIN ไม่ถูกต้อง');
        }
        if ($row['suspended_at']!==null) throw new DomainException('บัญชีนี้ถูกระงับ กรุณาติดต่อผู้ดูแลโครงการ');
        if ($migrate) {
            $newHash='sha384:'.password_hash(base64_encode(hash('sha384',$pin,true)),PASSWORD_BCRYPT,['cost'=>12]);
            $save=$db->prepare('UPDATE anemia_participant_password SET password_hash=? WHERE username=? AND password_hash=?');
            $save->execute([$newHash,$username,$hash]);
            if ($save->rowCount()!==1) throw new DomainException('บัญชีนี้เพิ่งได้รับการอัปเดต กรุณาลองอีกครั้ง');
        }
        return $row['account_id'];
    }
    if (anemiaReservedParticipantUsername($username)) throw new DomainException('ชื่อนี้สงวนไว้สำหรับระบบ กรุณาเลือกชื่อบัญชีอื่น');
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
        $lock=$db->prepare('SELECT id,suspended_at FROM anemia_participant_account WHERE id=? FOR UPDATE');
        $lock->execute([$account]);
        $row=$lock->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new DomainException('ไม่พบบัญชี กรุณาเข้าสู่ระบบใหม่');
        if ($row['suspended_at']!==null) throw new DomainException('บัญชีนี้ถูกระงับ กรุณาติดต่อผู้ดูแลโครงการ');
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
