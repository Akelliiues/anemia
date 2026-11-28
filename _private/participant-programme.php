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
        1=>['เหนื่อยง่าย แปลว่าโลหิตจางไหม?','นึกถึงกิจวัตรที่ทำบ่อย เช่น เดินไปป้ายรถหรือขึ้นบันได ช่วงนี้ทำได้เหมือนเดิมไหม?','index.html?view=learn'],
        2=>['ข้อความนี้มาจากไหน?','เปิดข้อความสุขภาพที่เคยได้รับหนึ่งข้อความ ลองตามกลับไปให้ถึงหน้าแรกที่เผยแพร่',''],
        3=>['หนึ่งถุง กินกี่ครั้ง?','ดูของที่คุณหยิบมา แล้วเติมว่า “ทั้งห่อนี้มี … หน่วย ส่วนที่ฉันมักกินคือ … หน่วย”',''],
        4=>['เทียบอาหารสองกล่องให้เป็น','หยิบอาหารคล้ายกันสองชิ้น ดูว่าฉลากคิดตัวเลขต่อกี่กรัม ถ้าไม่ได้บอกปริมาณธาตุเหล็ก ใช้ตัวอย่างซีเรียลในบทฝึกแทนได้เลย',''],
        5=>['จะเล่าอาการให้เจ้าหน้าที่ฟังยังไงดี?','เขียนว่า “ช่วงนี้ฉันสังเกตว่า…” แล้วเติมอีกบรรทัดว่า “เรื่องที่อยากถามคือ…”',''],
        6=>['หาที่ตรวจที่คุณไปได้จริง','เลือกหนึ่งแห่ง จดชื่อ อำเภอ และช่องทางติดต่อ วันนี้ลองค้นหาได้โดยยังไม่ต้องส่งคำขอนัด','index.html?view=facility-directory'],
        7=>['ส่งคำขอนัดแล้ว ดูอะไรต่อ?','ถ้าจะบอกวันนัดให้คนที่จะพาไป คุณจะบอกวัน รอบ และจุดหมายอย่างไร?',''],
        8=>['คะแนนบนจอกับผลเลือด บอกอะไรคนละอย่าง?','ลองพูดให้จบว่า “แบบประเมินบอกให้ฉัน… ส่วนเรื่องที่ฉันอยากถามเจ้าหน้าที่คือ…”','index.html'],
        9=>['เพื่อนส่งรีวิวธาตุเหล็กมาให้ จะตอบยังไง?','พิมพ์ตอบเพื่อนหนึ่งบรรทัด ให้รู้ว่าจะไปถามใคร และควรเอาข้อมูลอะไรไปด้วย',''],
        10=>['จัดมื้อกลางวันให้มีธาตุเหล็ก','จดชื่ออาหารหนึ่งมื้อ แล้วดูว่าอะไรให้ธาตุเหล็ก และอะไรให้วิตามินซี',''],
        11=>['ชวนเพื่อนไปตรวจด้วยคำที่ฟังแล้วสบายใจ','ลองสลับบทกัน คนหนึ่งเพิ่งเห็นผล อีกคนเป็นเพื่อนที่รับฟัง ถ้าเรียนคนเดียว ลองพูดคำชวนออกเสียง',''],
        12=>['เลือกหนึ่งเรื่องที่อยากทำต่อ','เติมว่า “วัน… ฉันจะ… แล้วจะกลับมาดูว่าทำได้ไหมตอน…”',''],
    ];
}

function anemiaWeekOneQuestions(): array
{
    return [
        'oxygen'=>['ฮีโมโกลบินมีหน้าที่อะไร?', ['oxygen'=>'ช่วยพาออกซิเจนไปทั่วร่างกาย','food'=>'ช่วยย่อยอาหาร','clot'=>'ช่วยให้เลือดหยุดไหล'], 'oxygen', 'ฮีโมโกลบินอยู่ในเม็ดเลือดแดงและช่วยพาออกซิเจน'],
        'symptoms'=>['ถ้าเหนื่อยง่ายอย่างเดียว จะบอกได้เลยไหมว่าเป็นโลหิตจาง?', ['stairs'=>'ได้ ถ้าเหนื่อยตอนเดินขึ้นบันได','many'=>'ยังบอกไม่ได้ เพราะเกิดได้จากหลายสาเหตุ','days'=>'ได้ ถ้าเหนื่อยติดกันหลายวัน'], 'many', 'อาการเหนื่อยอย่างเดียวบอกไม่ได้ว่าเกิดจากอะไร การตรวจเลือดช่วยให้ประเมินได้ชัดขึ้น'],
        'next'=>['ถ้าแบบประเมินแนะนำให้ตรวจเพิ่ม ควรทำอย่างไร?', ['wait'=>'รอเรียนครบ 12 สัปดาห์','score'=>'ใช้คะแนนสรุปว่าเป็นโลหิตจาง','contact'=>'ปรึกษาหน่วยบริการเรื่องการตรวจได้เลย'], 'contact', 'คุณปรึกษาหน่วยบริการได้ทันทีโดยไม่ต้องรอจบกิจกรรม'],
    ];
}

function anemiaReadingLessons(): array
{
    return [
        2 => [
            'ถ้าเพื่อนส่งรูปที่เขียนว่า “เหนื่อยง่าย ให้กินธาตุเหล็ก” มาให้ ลองขอลิงก์เรื่องเต็ม กดเข้าไปดูชื่อคนเขียน วันที่ และที่มาของคำแนะนำ ถ้าหาเจ้าของเรื่องไม่เจอ เก็บไว้ถามเจ้าหน้าที่ก่อนได้',
            'เพื่อนส่งภาพคำแนะนำที่ตัดชื่อผู้เขียนออก ควรทำอะไรก่อนแชร์?',
            'ถามหาต้นฉบับแล้วอ่านบริบท',
            'ดูว่ามีคนกดถูกใจกี่คน',
            'ต้นฉบับช่วยให้ตรวจได้ว่าคำแนะนำนั้นใช้กับใคร',
        ],
        3 => [
            'พลิกกล่องแล้วหาคำว่า “หนึ่งหน่วยบริโภค” คำนี้บอกว่าตัวเลขบนฉลากคิดจากอาหารเท่าไร ลองนึกภาพขนมหนึ่งถุงแบ่งกินได้สองครั้งเท่า ๆ กัน ถ้าฉลากคิดต่อครึ่งถุง กินครึ่งถุงก็อ่านตัวเลขตามฉลากได้เลย กินหมดถุงให้นับสองเท่า',
            'หนึ่งกล่องมีสองหน่วย กินครึ่งกล่องจะเท่ากับกี่หน่วย?',
            'หนึ่งหน่วย',
            'สองหน่วย',
            'ครึ่งหนึ่งของกล่องที่มีสองหน่วยเท่ากับหนึ่งหน่วย',
        ],
        4 => [
            'สมมติว่าซีเรียลกล่องแรกมีธาตุเหล็ก 2 มิลลิกรัมต่อ 30 กรัม อีกกล่องมี 3 มิลลิกรัมต่อ 60 กรัม ถ้าเทใส่ถ้วยเท่ากันที่ 60 กรัม กล่องแรกให้ 4 มิลลิกรัม ส่วนกล่องที่สองให้ 3 ลองทำแบบเดียวกันกับฉลากที่บ้าน ถ้าฉลากไม่ได้บอกปริมาณธาตุเหล็ก ให้ข้ามการเทียบธาตุเหล็กของคู่นั้น',
            'ถ้าฉลากใช้หน่วยบริโภคต่างกัน ควรเทียบอย่างไร?',
            'เทียบที่ปริมาณอาหารเท่ากัน',
            'เลือกกล่องที่พิมพ์ตัวเลขใหญ่กว่า',
            'การใช้ปริมาณเท่ากันทำให้เทียบสิ่งเดียวกัน',
        ],
        5 => [
            'เริ่มจากสิ่งที่เจอจริง เช่น “อาทิตย์นี้เดินขึ้นบันไดไปห้องเรียนแล้วต้องหยุดพัก ทั้งที่เมื่อก่อนเดินได้เลย” จากนั้นถามเรื่องที่กังวล เช่น “แบบนี้ควรตรวจอะไรเพิ่มไหมคะ” ถ้าเป็นเรื่องประจำเดือน ลองบอกด้วยว่าเริ่มเปลี่ยนไปจากรอบก่อนอย่างไร',
            'ข้อความไหนช่วยเล่าอาการได้ชัดกว่า?',
            'ช่วงนี้เดินขึ้นบันไดหนึ่งชั้นแล้วต้องหยุดพัก ต่างจากเดิม',
            'รู้สึกว่าไม่เหมือนคนอื่น',
            'เล่าสิ่งที่เปลี่ยนจากปกติพร้อมตัวอย่างที่เกิดขึ้นจริง',
        ],
        6 => [
            'เริ่มจากชื่อจังหวัดและอำเภอ ดูให้แน่ใจว่าชื่อโรงพยาบาลตรงกับที่ตั้งใจไป แล้วจดชื่อกับช่องทางติดต่อไว้ ก่อนออกจากบ้านลองถามว่า “ถ้าจะมาปรึกษาเรื่องโลหิตจาง ต้องไปจุดไหน เปิดวันไหน และต้องนัดก่อนไหมคะ”',
            'ก่อนเดินทางไปตรวจ ควรเช็กเรื่องใดกับหน่วยบริการ?',
            'วันให้บริการและวิธีนัด',
            'จำนวนดาวรีวิวอย่างเดียว',
            'ข้อมูลจากหน่วยบริการช่วยให้ไปถูกวันและถูกจุด',
        ],
        7 => [
            'ถ้ายังไม่ได้ส่งคำขอ เริ่มได้ว่า “ทำแบบประเมินแล้ว อยากขอนัดตรวจค่ะ ต้องเตรียมอะไรไปบ้าง” ถ้าส่งคำขอแล้ว ให้เปิดเลขคำขอไว้ตอนโทร เมื่อเจ้าหน้าที่ยืนยันนัด ค่อยจดวัน รอบเช้าหรือบ่าย และจุดที่ต้องไป',
            'ส่งคำขอแล้ว แต่ยังไม่มีวันนัด ควรทำอย่างไร?',
            'ติดตามสถานะหรือติดต่อหน่วยบริการ',
            'ถือว่านัดสำเร็จในวันที่เลือกทันที',
            'วันนัดที่ยืนยันแล้วช่วยให้วางแผนเดินทางได้ตรงกัน',
        ],
        8 => [
            'คะแนนบนจอมาจากคำตอบที่คุณเลือก ถ้าหน้าผลชวนไปตรวจ คุณนำผลนี้ไปคุยกับเจ้าหน้าที่ได้ ส่วนผลเลือดจะได้หลังเจาะเลือดตรวจ ถ้าคะแนนต่ำแต่ยังเหนื่อยจนทำกิจวัตรลำบาก หรือกังวลว่าประจำเดือนมามาก คุณขอคำปรึกษาได้เลย',
            'คะแนนต่ำแต่ยังมีอาการรบกวนชีวิต ควรเลือกทางใด?',
            'เล่าอาการและปรึกษาหน่วยบริการ',
            'รอให้คะแนนสูงก่อนทุกครั้ง',
            'คะแนนช่วยบอกแนวทางเบื้องต้น เจ้าหน้าที่ใช้ข้อมูลอาการร่วมด้วย',
        ],
        9 => [
            'ถ้าเพื่อนส่งรีวิวว่า “กินตัวนี้แล้วหายเหนื่อย” ลองดูว่าคนโพสต์ขายสินค้านั้นด้วยไหม ประสบการณ์ของคนหนึ่งบอกไม่ได้ว่ายาเหมาะกับเพื่อนหรือเปล่า คุณตอบได้ว่า “เก็บรูปฉลากไว้ เดี๋ยวเอาไปถามเภสัชกรก่อนนะ”',
            'เพื่อนขอให้แชร์ข้อความแนะนำยี่ห้อและขนาดยา ควรทำอย่างไร?',
            'ตรวจต้นทางและให้เพื่อนปรึกษาผู้ดูแลเรื่องยา',
            'แชร์ต่อเพราะคนโพสต์บอกว่าได้ผล',
            'คำแนะนำเรื่องอาหารเสริมต้องเหมาะกับแต่ละคน',
        ],
        10 => [
            'ถ้ามื้อกลางวันมีข้าวกับแกงถั่ว ลองเลือกส้มกินปิดท้าย ถั่วมีธาตุเหล็ก ส่วนวิตามินซีในส้มช่วยให้ร่างกายรับธาตุเหล็กจากถั่วได้ดีขึ้น ถ้าที่บ้านมีมะเขือเทศ จะใส่มะเขือเทศในกับข้าวแทนก็ได้ ลองจดคู่ที่คุณอยากกินจริง ๆ ไว้หนึ่งคู่',
            'มื้อที่มีถั่ว อยากเพิ่มอาหารที่มีวิตามินซี เลือกอะไรได้?',
            'ส้ม',
            'น้ำเปล่า',
            'ส้มเป็นตัวอย่างผลไม้ที่มีวิตามินซี',
        ],
        11 => [
            'ถ้าเพื่อนอ่านผลแล้วถามว่า “แปลว่าเราเป็นโลหิตจางแล้วเหรอ” ลองตอบว่า “คะแนนบอกว่าควรไปตรวจเพิ่ม เจ้าหน้าที่จะช่วยดูว่าเกิดจากอะไร ให้เราช่วยหาที่ตรวจไหม” แล้วรอฟังว่าเพื่อนอยากให้ช่วยเรื่องไหน',
            'ถ้าเพื่อนได้คะแนนสูง คุณจะชวนอย่างไร?',
            'ลองติดต่อหน่วยบริการเพื่อประเมินเพิ่มเติมด้วยกัน',
            'บอกเพื่อนว่าเป็นโลหิตจางแน่นอน',
            'การชวนไปตรวจช่วยให้เพื่อนได้รับคำตอบตามผลประเมินจริง',
        ],
        12 => [
            'ถ้าชอบมื้อที่ลองจัดไว้ ลองจดว่า “วันจันทร์จะเอาส้มไปกินกับข้าวกลางวัน” พอถึงเย็นค่อยดูว่าทำได้ไหม ถ้าพกไม่สะดวก ครั้งหน้าอาจเลือกร้านที่มีผลไม้ขายแทน หรือถ้าเรื่องที่ยังค้างคือการตรวจ ให้จดวันที่จะโทรถามโรงพยาบาล แล้วทำแบบประเมินหลังเรียนตามที่เข้าใจ',
            'ถ้าทำตามแผนครั้งแรกไม่ได้ คุณอยากปรับอะไร?',
            'เปลี่ยนเป็นวันที่สะดวกกว่า',
            'ลดสิ่งที่จะทำให้เล็กลง',
            'ทั้งสองวิธีใช้ได้ เลือกวิธีที่เข้ากับชีวิตคุณ หรือขอให้คนใกล้ตัวช่วยเตือน',
        ],
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

function anemiaProgrammeState(PDO $db,string $account,string $programmeVersion=ANEMIA_PROGRAMME_VERSION): array
{
    $progress=$db->prepare('SELECT week_no FROM anemia_participant_programme_progress WHERE account_id=? AND programme_version=? ORDER BY week_no');
    $progress->execute([$account,$programmeVersion]);
    $assessment=$db->prepare('SELECT phase,score,max_score,submitted_at FROM anemia_participant_programme_assessment
        WHERE account_id=? AND programme_version=? AND instrument_version=?');
    $assessment->execute([$account,$programmeVersion,ANEMIA_PROGRAMME_INSTRUMENT_VERSION]);
    return ['completed'=>array_map('intval',$progress->fetchAll(PDO::FETCH_COLUMN)),
        'assessments'=>$assessment->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC)];
}

function anemiaSaveProgrammeAction(PDO $db,string $account,int $epoch,array $input,string $programmeVersion=ANEMIA_PROGRAMME_VERSION): void
{
    $action=$input['action']??'';
    if (($input['programme_version']??'')!==$programmeVersion ||
        ($input['instrument_version']??'')!==ANEMIA_PROGRAMME_INSTRUMENT_VERSION) {
        throw new DomainException('มีแบบประเมินหรือกิจกรรมฉบับใหม่ กรุณาเปิดหน้านี้อีกครั้ง');
    }
    $db->beginTransaction();
    try {
        $session=$db->prepare('SELECT 1 FROM anemia_participant_account WHERE id=? AND session_epoch=? AND suspended_at IS NULL FOR UPDATE');
        $session->execute([$account,$epoch]);
        if (!$session->fetchColumn()) throw new DomainException('เซสชันสิ้นสุดแล้ว กรุณาเข้าสู่ระบบใหม่');
        ['completed'=>$completed,'assessments'=>$assessments]=anemiaProgrammeState($db,$account,$programmeVersion);
        $next=anemiaProgrammeNextWeek($completed);
        if ($action==='week') {
            $week=filter_var($input['week']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>count(anemiaProgrammeWeeks())]]);
            if ($week===false || !isset($assessments['before']) || ($week!==$next && !in_array($week,$completed,true))) {
                throw new DomainException('กรุณาทำแบบประเมินก่อนเริ่มและทำกิจกรรมตามลำดับสัปดาห์');
            }
            if (!in_array($week,$completed,true)) {
                if ($week===1) {
                    $answers=$input['lesson_answers']??[];
                    foreach (anemiaWeekOneQuestions() as $id=>[,,$correct]) {
                        if (!is_array($answers) || ($answers[$id]??null)!==$correct) {
                            throw new DomainException('ลองทบทวนคำตอบสัปดาห์ที่ 1 แล้วส่งอีกครั้ง');
                        }
                    }
                } elseif (($input['reading_answer']??null)!=='week-'.$week.'-apply' && !($week===12 && ($input['reading_answer']??null)==='week-12-other')) {
                    throw new DomainException(anemiaReadingLessons()[$week][4].' ลองเลือกคำตอบอีกครั้ง');
                }
                $db->prepare('INSERT INTO anemia_participant_programme_progress (account_id,programme_version,week_no,completed_at)
                    VALUES (?,?,?,NOW())')->execute([$account,$programmeVersion,$week]);
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
                    VALUES (?,?,?,?,?,?,?,NOW())')->execute([$account,$programmeVersion,ANEMIA_PROGRAMME_INSTRUMENT_VERSION,
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
