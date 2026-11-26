<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

// Bump the programme version with changes to the instrument so baseline always precedes its activities.
const ANEMIA_PROGRAMME_VERSION = 'draft-12w-v1';
const ANEMIA_PROGRAMME_INSTRUMENT_VERSION = 'draft-learning-v1';

function anemiaEnsureParticipantProgramme(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS anemia_participant_programme_progress (
        account_id CHAR(32) CHARACTER SET ascii NOT NULL,
        programme_version VARCHAR(40) CHARACTER SET ascii NOT NULL,
        week_no TINYINT UNSIGNED NOT NULL,
        completed_at DATETIME NOT NULL,
        PRIMARY KEY (account_id,programme_version,week_no)
    ) ENGINE=InnoDB");
    $db->exec("CREATE TABLE IF NOT EXISTS anemia_participant_programme_assessment (
        account_id CHAR(32) CHARACTER SET ascii NOT NULL,
        programme_version VARCHAR(40) CHARACTER SET ascii NOT NULL,
        instrument_version VARCHAR(40) CHARACTER SET ascii NOT NULL,
        phase ENUM('before','after') NOT NULL,
        score TINYINT UNSIGNED NOT NULL,
        max_score TINYINT UNSIGNED NOT NULL,
        answers_json TEXT NOT NULL,
        submitted_at DATETIME NOT NULL,
        PRIMARY KEY (account_id,programme_version,instrument_version,phase)
    ) ENGINE=InnoDB");
}

// Keep published question IDs and activities stable within a version.
function anemiaProgrammeWeeks(): array
{
    return [
        1=>['รู้จักคำและหัวข้อในคลังความรู้','เปิดคลังความรู้ เลือกหนึ่งหัวข้อเกี่ยวกับภาวะโลหิตจาง แล้วจดคำที่อยากทำความเข้าใจเพิ่มไว้กับตัวเอง','index.html?view=learn'],
        2=>['ดูที่มาของข้อมูล','เลือกบทความสุขภาพหนึ่งชิ้น ลองหาชื่อผู้เผยแพร่ วันที่เผยแพร่ และเอกสารอ้างอิง',''],
        3=>['อ่านข้อมูลบนฉลากอาหาร','เลือกฉลากอาหารหนึ่งชิ้น ลองหาขนาดหน่วยบริโภคและจำนวนหน่วยบริโภคต่อบรรจุภัณฑ์',''],
        4=>['เปรียบเทียบข้อมูลอาหาร','ลองเปรียบเทียบฉลากสองชิ้นโดยเริ่มจากการตรวจว่าปริมาณอ้างอิงของทั้งสองฉลากเท่ากันหรือไม่',''],
        5=>['เตรียมคำถามสำหรับบุคลากรสุขภาพ','เขียนคำถามเกี่ยวกับภาวะโลหิตจางที่อยากถามสักหนึ่งข้อ เก็บคำถามไว้กับตัวเอง',''],
        6=>['ค้นหาสถานบริการ','เปิดเมนูค้นหาโรงพยาบาล เลือกพื้นที่ที่สนใจ แล้วดูช่องทางติดต่อของสถานบริการหนึ่งแห่ง','index.html?view=facility-directory'],
        7=>['เตรียมข้อมูลก่อนติดต่อ','สมมติว่าต้องการนัดหมาย ลองทำรายการสิ่งที่อยากสอบถาม เช่น เวลาทำการและเอกสารที่ต้องนำไป',''],
        8=>['แยกผลแบบประเมินออกจากผลตรวจ','เปิดหน้าแรกแล้วอ่านคำอธิบายของแบบประเมิน สรุปกับตัวเองว่าแบบประเมินออนไลน์กับผลตรวจมีที่มาต่างกันอย่างไร','index.html'],
        9=>['คิดก่อนส่งต่อข้อมูลสุขภาพ','ลองอ่านข้อความสุขภาพที่เคยได้รับ แล้วคิดว่าจะตรวจที่มาจากที่ไหนก่อนส่งต่อ',''],
        10=>['ทบทวนเรื่องที่ยังสงสัย','กลับไปดูคำหรือคำถามที่จดไว้ เลือกหนึ่งข้อที่ยังสงสัยแล้วหาว่าควรถามใครหรืออ่านจากแหล่งใด',''],
        11=>['อธิบายด้วยคำของตัวเอง','เลือกหนึ่งเรื่องที่ได้เรียนรู้ แล้วลองอธิบายให้คนใกล้ตัวฟังหรือสรุปไว้กับตัวเอง',''],
        12=>['ทบทวนสิ่งที่ได้เรียนรู้','ทบทวนสิ่งที่ทำมาตลอดกิจกรรม แล้วเลือกหนึ่งเรื่องที่อยากนำไปใช้หรือศึกษาเพิ่ม',''],
    ];
}

function anemiaProgrammeQuestions(): array
{
    return [
        ['id'=>'source','text'=>'ก่อนส่งต่อข้อความสุขภาพ ควรเริ่มตรวจจากอะไร','choices'=>['a'=>'จำนวนครั้งที่ข้อความถูกแชร์','b'=>'ผู้เผยแพร่ วันที่ และแหล่งอ้างอิง','c'=>'ความยาวของข้อความ'],'correct'=>'b'],
        ['id'=>'label','text'=>'เมื่อต้องการเปรียบเทียบข้อมูลบนฉลากอาหารสองชิ้น ควรตรวจอะไรให้เข้าใจก่อน','choices'=>['a'=>'ขนาดหน่วยบริโภคที่ฉลากใช้อ้างอิง','b'=>'สีของฉลาก','c'=>'ขนาดตัวอักษรของชื่อสินค้า'],'correct'=>'a'],
        ['id'=>'facility','text'=>'วิธีใดช่วยยืนยันเวลาทำการของสถานบริการก่อนเดินทาง','choices'=>['a'=>'ใช้เวลาทำการของสถานบริการอื่นในพื้นที่','b'=>'ดูเฉพาะเวลาที่คนรู้จักเคยไป','c'=>'ตรวจจากช่องทางทางการของสถานบริการหรือติดต่อโดยตรง'],'correct'=>'c'],
        ['id'=>'assessment','text'=>'หากต้องการทราบว่าต้องตรวจเพิ่มเติมหรือไม่ หลังทำแบบประเมินออนไลน์ควรถามใคร','choices'=>['a'=>'บุคลากรสุขภาพ','b'=>'ผู้ดูแลเพจที่แชร์แบบประเมิน','c'=>'ผู้ตอบแบบประเมินคนอื่น'],'correct'=>'a'],
        ['id'=>'questions','text'=>'วิธีใดช่วยให้จำคำถามที่อยากถามบุคลากรสุขภาพได้','choices'=>['a'=>'ถามตามคำถามที่คนอื่นใช้ทุกข้อ','b'=>'จดคำถามที่สงสัยไว้ก่อนติดต่อ','c'=>'รอให้ถึงวันนัดแล้วค่อยนึกคำถาม'],'correct'=>'b'],
    ];
}

function anemiaProgrammeScore(mixed $answers,array $questions): int
{
    if (!is_array($answers) || count($answers)!==count($questions)) {
        throw new DomainException('กรุณาตอบให้ครบทุกข้อ');
    }
    $score=0;
    foreach ($questions as $number=>$question) {
        $choice=$answers[$number]??null;
        if (!is_string($choice) || !array_key_exists($choice,$question['choices'])) {
            throw new DomainException('กรุณาเลือกคำตอบจากตัวเลือกในแบบประเมิน');
        }
        $score+=(int)($choice===$question['correct']);
    }
    return $score;
}

function anemiaProgrammeNextWeek(array $completed): ?int
{
    foreach (array_keys(anemiaProgrammeWeeks()) as $week) {
        if (!in_array($week,$completed,true)) return $week;
    }
    return null;
}

function anemiaProgrammeState(PDO $db,string $account): array
{
    $progress=$db->prepare('SELECT week_no FROM anemia_participant_programme_progress WHERE account_id=? AND programme_version=? ORDER BY week_no');
    $progress->execute([$account,ANEMIA_PROGRAMME_VERSION]);
    $assessment=$db->prepare('SELECT phase,score,max_score,submitted_at FROM anemia_participant_programme_assessment
        WHERE account_id=? AND programme_version=? AND instrument_version=?');
    $assessment->execute([$account,ANEMIA_PROGRAMME_VERSION,ANEMIA_PROGRAMME_INSTRUMENT_VERSION]);
    return ['completed'=>array_map('intval',$progress->fetchAll(PDO::FETCH_COLUMN)),
        'assessments'=>$assessment->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC)];
}

function anemiaSaveProgrammeAction(PDO $db,string $account,int $epoch,array $input): void
{
    $action=$input['action']??'';
    if (($input['programme_version']??'')!==ANEMIA_PROGRAMME_VERSION ||
        ($input['instrument_version']??'')!==ANEMIA_PROGRAMME_INSTRUMENT_VERSION) {
        throw new DomainException('มีแบบประเมินหรือกิจกรรมฉบับใหม่ กรุณาเปิดหน้านี้อีกครั้ง');
    }
    $db->beginTransaction();
    try {
        $session=$db->prepare('SELECT 1 FROM anemia_participant_account WHERE id=? AND session_epoch=? AND suspended_at IS NULL FOR UPDATE');
        $session->execute([$account,$epoch]);
        if (!$session->fetchColumn()) throw new DomainException('เซสชันสิ้นสุดแล้ว กรุณาเข้าสู่ระบบใหม่');
        ['completed'=>$completed,'assessments'=>$assessments]=anemiaProgrammeState($db,$account);
        $next=anemiaProgrammeNextWeek($completed);
        if ($action==='week') {
            $week=filter_var($input['week']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>count(anemiaProgrammeWeeks())]]);
            if ($week===false || !isset($assessments['before']) || ($week!==$next && !in_array($week,$completed,true))) {
                throw new DomainException('กรุณาทำแบบประเมินก่อนเริ่มและทำกิจกรรมตามลำดับสัปดาห์');
            }
            if (!in_array($week,$completed,true)) {
                $db->prepare('INSERT INTO anemia_participant_programme_progress (account_id,programme_version,week_no,completed_at)
                    VALUES (?,?,?,NOW())')->execute([$account,ANEMIA_PROGRAMME_VERSION,$week]);
            }
        } elseif ($action==='before' || $action==='after') {
            if ($action==='before' && ($input['acknowledge']??'')!=='1') {
                throw new DomainException('กรุณารับทราบเกี่ยวกับการใช้แบบประเมินฉบับทดลอง');
            }
            if ($action==='after' && (!isset($assessments['before']) || $next!==null)) {
                throw new DomainException('กรุณาทำกิจกรรมให้ครบ 12 สัปดาห์ก่อนตอบแบบประเมินหลังจบ');
            }
            if (!isset($assessments[$action])) {
                $questions=anemiaProgrammeQuestions();
                $answers=$input['answers']??null;
                $score=anemiaProgrammeScore($answers,$questions);
                $record=[];
                foreach ($questions as $number=>$question) $record[$question['id']]=$answers[$number];
                $db->prepare('INSERT INTO anemia_participant_programme_assessment
                    (account_id,programme_version,instrument_version,phase,score,max_score,answers_json,submitted_at)
                    VALUES (?,?,?,?,?,?,?,NOW())')->execute([$account,ANEMIA_PROGRAMME_VERSION,ANEMIA_PROGRAMME_INSTRUMENT_VERSION,
                        $action,$score,count($questions),json_encode($record,JSON_THROW_ON_ERROR)]);
            }
        } else {
            throw new DomainException('กรุณาเลือกกิจกรรมหรือแบบประเมิน');
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}
