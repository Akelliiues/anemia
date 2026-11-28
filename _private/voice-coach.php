<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

function anemiaVoiceArticles(): array
{
    static $articles;
    return $articles??=require __DIR__.'/voice-articles.php';
}

function anemiaVoiceSlots(): array
{
    return ['home'=>'ตัวอย่างบนหน้าหลัก','low'=>'ผลประเมิน · ดูแลต่อเนื่อง',
        'medium'=>'ผลประเมิน · มีบางเรื่องให้ดูเพิ่ม','high'=>'ผลประเมิน · แนะนำให้ตรวจเพิ่ม',
        'unknown'=>'ผลประเมิน · ข้อมูลยังไม่พอสรุป']+array_map(static fn(array $article): string=>$article[0],anemiaVoiceArticles());
}

function anemiaVoiceExamples(): array
{
    return [
        'home'=>'ดูคำแนะนำหลังตอบแบบประเมิน แล้วเลือกขั้นตอนที่เหมาะกับคุณ',
        'unknown'=>'ขอบคุณที่ตอบเท่าที่จำได้ค่ะ แบบประเมินนี้ยังสรุประดับความเสี่ยงไม่ได้ คุณอ่านคำแนะนำต่อได้เลย หากนึกข้อมูลออก ค่อยกลับมาเติม หรือเลือกดูช่องทางปรึกษาหน่วยบริการได้ค่ะ',
        'low'=>'จากคำตอบของคุณ ผลเบื้องต้นบ่งชี้ว่าความเสี่ยงโลหิตจางน้อยค่ะ มื้อต่อไปลองเพิ่มผัก และเลือกอาหารที่มีธาตุเหล็ก เช่น เนื้อไม่ติดมันหรือถั่ว หากมีอาการหรือยังเป็นกังวล ปรึกษาหน่วยบริการได้เลยค่ะ',
        'medium'=>'มีบางคำตอบที่ควรดูเพิ่มค่ะ คุณลองตอบอีกมุม หรือเลือกปรึกษาหน่วยบริการเรื่องตรวจเลือดได้เลย',
        'high'=>'เราแนะนำให้ตรวจเลือดเพิ่มเติมเพื่อให้รู้ชัดขึ้นค่ะ คุณเลือกสถานที่ตรวจหรือขอให้เจ้าหน้าที่ติดต่อกลับได้ตามที่สะดวก',
    ]+array_map(static fn(array $article): string=>$article[1],anemiaVoiceArticles());
}

function anemiaEnsureVoiceCoach(PDO $db): void
{
    if ($db->query('SELECT version FROM anemia_schema WHERE version=32')->fetchColumn()) return;
    $db->exec("CREATE TABLE IF NOT EXISTS anemia_voice_coach (
        slot VARCHAR(12) CHARACTER SET ascii NOT NULL,
        edition ENUM('draft','published') NOT NULL,
        display_text TEXT NOT NULL, audio MEDIUMBLOB NULL,
        updated_by VARCHAR(40) NOT NULL, updated_at DATETIME NOT NULL,
        PRIMARY KEY(slot,edition)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec('INSERT IGNORE INTO anemia_schema(version) VALUES (32)');
}

function anemiaVoiceSave(PDO $db,string $slot,array $input,array $file,string $actor): void
{
    $text=$input['display_text']??null;
    if (!is_string($text) || !preg_match('/^.{1,1500}$/usD',trim($text))) {
        throw new DomainException('กรุณาใส่ข้อความที่แสดง ความยาวไม่เกิน 1,500 ตัวอักษร');
    }
    $bytes=null;
    $upload=$file['error']??UPLOAD_ERR_NO_FILE;
    if ($upload!==UPLOAD_ERR_NO_FILE) {
        if (!class_exists('finfo')) throw new DomainException('โฮสต์ยังไม่ได้เปิดส่วนตรวจชนิดไฟล์ fileinfo');
        if ($upload!==UPLOAD_ERR_OK || ($file['size']??0)<1 || $file['size']>2*1024*1024 ||
            !is_uploaded_file($file['tmp_name']) || (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name'])!=='audio/mpeg') {
            throw new DomainException('กรุณาอัปโหลดไฟล์ MP3 ขนาดไม่เกิน 2 MB');
        }
        $bytes=file_get_contents($file['tmp_name']);
        if (!is_string($bytes)) throw new DomainException('อ่านไฟล์เสียงไม่ได้');
    }
    $db->prepare("INSERT INTO anemia_voice_coach(slot,edition,display_text,updated_by,updated_at) VALUES (?,'draft',?,?,NOW())
        ON DUPLICATE KEY UPDATE display_text=VALUES(display_text),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)")
        ->execute([$slot,trim($text),$actor]);
    if ($bytes!==null || ($input['remove_audio']??'')==='1') {
        $query=$db->prepare("UPDATE anemia_voice_coach SET audio=? WHERE slot=? AND edition='draft'");
        $query->bindValue(1,$bytes,$bytes===null?PDO::PARAM_NULL:PDO::PARAM_LOB);
        $query->bindValue(2,$slot);
        $query->execute();
    }
}

function anemiaVoicePublish(PDO $db,string $slot,string $actor): void
{
    $draft=$db->prepare("SELECT 1 FROM anemia_voice_coach WHERE slot=? AND edition='draft' FOR UPDATE");
    $draft->execute([$slot]);
    if (!$draft->fetchColumn()) throw new DomainException('บันทึกฉบับร่างก่อนเผยแพร่');
    $draft->closeCursor();
    $db->prepare("INSERT INTO anemia_voice_coach(slot,edition,display_text,audio,updated_by,updated_at)
        SELECT slot,'published',display_text,audio,?,NOW() FROM anemia_voice_coach WHERE slot=? AND edition='draft'
        ON DUPLICATE KEY UPDATE display_text=VALUES(display_text),audio=VALUES(audio),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)")
        ->execute([$actor,$slot]);
}

function anemiaVoiceStream(PDO $db,string $slot,string $edition): never
{
    $query=$db->prepare('SELECT audio FROM anemia_voice_coach WHERE slot=? AND edition=?');
    $query->execute([$slot,$edition]);
    $audio=$query->fetchColumn();
    if (!is_string($audio) || $audio==='') { http_response_code(404); exit; }
    $size=strlen($audio);
    header('Content-Type: audio/mpeg');
    header('Accept-Ranges: bytes');
    header('Cache-Control: '.($edition==='draft'?'private, no-store':'public, max-age=30'));
    $range=$_SERVER['HTTP_RANGE']??'';
    if (preg_match('/^bytes=(\d+)-(\d*)$/D',$range,$matches)) {
        $first=(int)$matches[1];
        $last=$matches[2]===''?$size-1:min($size-1,(int)$matches[2]);
        if ($first>$last) { http_response_code(416); header("Content-Range: bytes */$size"); exit; }
        http_response_code(206);
        header("Content-Range: bytes $first-$last/$size");
        $audio=substr($audio,$first,$last-$first+1);
    }
    header('Content-Length: '.strlen($audio));
    if (($_SERVER['REQUEST_METHOD']??'')!=='HEAD') echo $audio;
    exit;
}
