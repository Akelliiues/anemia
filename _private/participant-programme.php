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
        1=>['รู้จักโลหิตจาง เริ่มจากเรื่องใกล้ตัว','ดูคลิปหรืออ่านสรุป แล้วลองตอบ 3 คำถามเพื่อทบทวนสิ่งที่ได้เรียนรู้','index.html?view=learn'],
        2=>['ดูที่มาของข้อมูล','เลือกบทความสุขภาพหนึ่งชิ้น ลองหาชื่อผู้เผยแพร่ วันที่เผยแพร่ และเอกสารอ้างอิง',''],
        3=>['อ่านข้อมูลบนฉลากอาหาร','เลือกฉลากอาหารหนึ่งชิ้น ลองหาขนาดหน่วยบริโภคและจำนวนหน่วยบริโภคต่อบรรจุภัณฑ์',''],
        4=>['เปรียบเทียบข้อมูลอาหาร','ลองเปรียบเทียบฉลากสองชิ้นโดยเริ่มจากการตรวจว่าปริมาณอ้างอิงของทั้งสองฉลากเท่ากันหรือไม่',''],
        5=>['เตรียมคำถามสำหรับบุคลากรสุขภาพ','เขียนคำถามเกี่ยวกับภาวะโลหิตจางที่อยากถามสักหนึ่งข้อ เก็บคำถามไว้กับตัวเอง',''],
        6=>['ค้นหาสถานบริการ','เปิดเมนูค้นหาโรงพยาบาล เลือกพื้นที่ที่สนใจ แล้วดูช่องทางติดต่อของสถานบริการหนึ่งแห่ง','index.html?view=facility-directory'],
        7=>['เตรียมข้อมูลก่อนติดต่อ','สมมติว่าต้องการนัดหมาย ลองทำรายการสิ่งที่อยากสอบถาม เช่น เวลาทำการและเอกสารที่ต้องนำไป',''],
        8=>['แยกผลแบบประเมินออกจากผลตรวจ','เปิดหน้าแรกแล้วอ่านคำอธิบายของแบบประเมิน สรุปกับตัวเองว่าแบบประเมินออนไลน์กับผลตรวจมีที่มาต่างกันอย่างไร','index.html'],
        9=>['คิดก่อนส่งต่อข้อมูลสุขภาพ','ลองอ่านข้อความสุขภาพที่เคยได้รับ แล้วคิดว่าจะตรวจที่มาจากที่ไหนก่อนส่งต่อ',''],
        10=>['จัดมื้ออาหารที่มีธาตุเหล็ก','ลองจัดมื้อกลางวันหนึ่งมื้อ แล้วจดชื่ออาหารที่เลือกกับผลไม้ที่หาได้',''],
        11=>['อธิบายด้วยคำของตัวเอง','เลือกหนึ่งเรื่องที่ได้เรียนรู้ แล้วลองอธิบายให้คนใกล้ตัวฟังหรือสรุปไว้กับตัวเอง',''],
        12=>['ทบทวนสิ่งที่ได้เรียนรู้','ทบทวนสิ่งที่ทำมาตลอดกิจกรรม แล้วเลือกหนึ่งเรื่องที่อยากนำไปใช้หรือศึกษาเพิ่ม',''],
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
            'เวลาเห็นข้อความว่า “เหนื่อยง่าย ต้องกินธาตุเหล็ก” ลองหยุดดูว่าใครเขียน เขียนเมื่อไร และอ้างข้อมูลจากไหน ภาวะโลหิตจางเกิดได้หลายสาเหตุ คนสองคนที่เหนื่อยคล้ายกันอาจต้องดูแลต่างกัน',
            'เพื่อนส่งภาพคำแนะนำที่ตัดชื่อผู้เขียนออก ควรทำอะไรก่อนแชร์?',
            'ถามหาต้นฉบับแล้วอ่านบริบท',
            'ดูว่ามีคนกดถูกใจกี่คน',
            'ต้นฉบับช่วยให้ตรวจได้ว่าคำแนะนำนั้นใช้กับใคร',
        ],
        3 => [
            'ลองพลิกกล่องอาหารไปด้านหลัง หาคำว่า “หนึ่งหน่วยบริโภค” ก่อนอ่านตัวเลข ถ้าหนึ่งกล่องมีสองหน่วยและกินหมดกล่อง ปริมาณที่ได้รับคือสองเท่าของตัวเลขต่อหน่วย หากฉลากไม่ได้ระบุธาตุเหล็ก จะใช้ฉลากนั้นสรุปปริมาณธาตุเหล็กไม่ได้',
            'หนึ่งกล่องมีสองหน่วย กินครึ่งกล่องจะเท่ากับกี่หน่วย?',
            'หนึ่งหน่วย',
            'สองหน่วย',
            'ครึ่งหนึ่งของกล่องที่มีสองหน่วยเท่ากับหนึ่งหน่วย',
        ],
        4 => [
            'ถ้าจะเทียบอาหารเช้าสองชนิด ให้ดูปริมาณที่จะกินจริง สมมติชนิดแรกระบุธาตุเหล็กต่อ 30 กรัม ส่วนอีกชนิดระบุต่อ 60 กรัม การเทียบตัวเลขข้างกล่องทันทีอาจทำให้เลือกผิดได้ อาหารในมื้อปกติก็มีแหล่งธาตุเหล็ก เช่น เนื้อสัตว์หรือถั่ว',
            'ถ้าฉลากใช้หน่วยบริโภคต่างกัน ควรเทียบอย่างไร?',
            'เทียบที่ปริมาณอาหารเท่ากัน',
            'เลือกกล่องที่พิมพ์ตัวเลขใหญ่กว่า',
            'การใช้ปริมาณเท่ากันทำให้เทียบสิ่งเดียวกัน',
        ],
        5 => [
            'ลองนึกถึงสิ่งที่เปลี่ยนไปจากปกติ เช่น เหนื่อยจนต้องพักระหว่างทำกิจวัตร หรือประจำเดือนมามากจนกระทบการเรียนหรือการทำงาน จดว่าเริ่มเมื่อไรและรบกวนอะไร ข้อมูลสั้น ๆ เหล่านี้ช่วยให้เล่าเรื่องกับเจ้าหน้าที่ได้ง่ายขึ้น',
            'ข้อความไหนช่วยเล่าอาการได้ชัดกว่า?',
            'ช่วงนี้เดินขึ้นบันไดหนึ่งชั้นแล้วต้องหยุดพัก ต่างจากเดิม',
            'รู้สึกว่าไม่เหมือนคนอื่น',
            'เล่าสิ่งที่เปลี่ยนจากปกติพร้อมตัวอย่างที่เกิดขึ้นจริง',
        ],
        6 => [
            'เปิดแผนที่แล้วเลือกหน่วยบริการที่เดินทางสะดวก ดูชื่อหน่วยและอำเภอให้ตรงกันก่อนโทรถามวันให้บริการ จุดติดต่อ และขั้นตอนนัดหมาย ระยะทางใกล้ช่วยวางแผนเดินทาง แต่เวลาที่ให้บริการตรวจอาจต่างกัน',
            'ก่อนเดินทางไปตรวจ ควรเช็กเรื่องใดกับหน่วยบริการ?',
            'วันให้บริการและวิธีนัด',
            'จำนวนดาวรีวิวอย่างเดียว',
            'ข้อมูลจากหน่วยบริการช่วยให้ไปถูกวันและถูกจุด',
        ],
        7 => [
            'ก่อนโทร ลองเตรียมประโยคสั้น ๆ ว่า “ทำแบบคัดกรองแล้วอยากสอบถามเรื่องตรวจเพิ่มเติม ต้องนัดอย่างไรคะ” หากส่งคำขอไว้แล้ว ให้เตรียมเลขคำขอและดูว่าสถานะระบุวันนัดหรือยัง',
            'ส่งคำขอแล้ว แต่ยังไม่มีวันนัด ควรทำอย่างไร?',
            'ติดตามสถานะหรือติดต่อหน่วยบริการ',
            'ถือว่านัดสำเร็จในวันที่เลือกทันที',
            'วันนัดที่ยืนยันแล้วช่วยให้วางแผนเดินทางได้ตรงกัน',
        ],
        8 => [
            'ผลคัดกรองมาจากคำตอบของคุณ ส่วนผลตรวจเลือดมาจากการตรวจที่หน่วยบริการ แม้คะแนนคัดกรองต่ำ หากยังเหนื่อยผิดปกติหรือมีประจำเดือนมากจนกังวล คุณเล่าอาการให้เจ้าหน้าที่ฟังได้ เก็บผลทั้งสองอย่างแยกกันเพื่อใช้ตอนติดตาม',
            'คะแนนต่ำแต่ยังมีอาการรบกวนชีวิต ควรเลือกทางใด?',
            'เล่าอาการและปรึกษาหน่วยบริการ',
            'รอให้คะแนนสูงก่อนทุกครั้ง',
            'คะแนนช่วยบอกแนวทางเบื้องต้น เจ้าหน้าที่ใช้ข้อมูลอาการร่วมด้วย',
        ],
        9 => [
            'เมื่อเห็นโพสต์ชวนซื้ออาหารเสริม ลองแยกคำบอกเล่าของผู้ใช้สินค้าออกจากหลักฐานที่ตรวจสอบได้ หากข้อความชวนให้ทุกคนซื้อธาตุเหล็กกินเอง ให้ตรวจที่มาและปรึกษาบุคลากรสุขภาพก่อน การใช้ธาตุเหล็กเกินความจำเป็นอาจเกิดอันตรายได้',
            'เพื่อนขอให้แชร์ข้อความแนะนำยี่ห้อและขนาดยา ควรทำอย่างไร?',
            'ตรวจต้นทางและให้เพื่อนปรึกษาผู้ดูแลเรื่องยา',
            'แชร์ต่อเพราะคนโพสต์บอกว่าได้ผล',
            'คำแนะนำเรื่องอาหารเสริมต้องเหมาะกับแต่ละคน',
        ],
        10 => [
            'ลองเลือกมื้อที่จัดเองได้ เช่น มื้อกลางวันที่โรงเรียนหรือที่ทำงาน ถ้ามีถั่วเป็นส่วนหนึ่งของมื้อ อาจเลือกผลไม้ที่มีวิตามินซี เช่น ส้ม กินร่วมมื้อ วิตามินซีช่วยให้ร่างกายดูดซึมธาตุเหล็กจากพืชได้ดีขึ้น ลองเลือกจากอาหารที่หาได้ใกล้ตัว',
            'มื้อที่มีถั่ว อยากเพิ่มอาหารที่มีวิตามินซี เลือกอะไรได้?',
            'ส้ม',
            'น้ำเปล่า',
            'ส้มเป็นตัวอย่างผลไม้ที่มีวิตามินซี',
        ],
        11 => [
            'ลองเล่าให้เพื่อนฟังในหนึ่งนาทีว่าแบบคัดกรองบอกอะไร แล้วชวนเพื่อนเล่ากลับด้วยคำของเขาเอง ถ้าเพื่อนเข้าใจว่าคะแนนสูงหมายถึงได้รับการวินิจฉัยแล้ว ลองอธิบายใหม่ว่าขั้นต่อไปคือการปรึกษาหน่วยบริการเรื่องตรวจเพิ่มเติม',
            'ถ้าเพื่อนได้คะแนนสูง คุณจะชวนอย่างไร?',
            'ลองติดต่อหน่วยบริการเพื่อประเมินเพิ่มเติมด้วยกัน',
            'บอกเพื่อนว่าเป็นโลหิตจางแน่นอน',
            'การชวนไปตรวจช่วยให้เพื่อนได้รับคำตอบตามผลประเมินจริง',
        ],
        12 => [
            'ย้อนดูสิ่งที่ลองทำตลอดหลักสูตร เลือกหนึ่งเรื่องที่ทำต่อได้ เช่น เตรียมผลไม้ไว้กินกับมื้อที่มีถั่ว เขียนว่าจะทำวันไหนและรู้ได้อย่างไรว่าทำแล้ว หากยังมีเรื่องสงสัยเกี่ยวกับอาการ ให้จดไว้ถามเจ้าหน้าที่ จากนั้นตอบแบบประเมินหลังเรียนตามความเข้าใจของคุณ',
            'แผนใดกลับมาทบทวนได้ชัดกว่า?',
            'วันจันทร์จะเตรียมส้มไว้กินกับมื้อกลางวันแล้วจดว่าทำได้ไหม',
            'ตั้งใจว่าจะสุขภาพดีขึ้น',
            'การระบุกิจกรรมและเวลาช่วยให้ตรวจดูได้ว่าได้ลองทำจริงหรือยัง',
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
                if ($week===1) {
                    $answers=$input['lesson_answers']??[];
                    foreach (anemiaWeekOneQuestions() as $id=>[,,$correct]) {
                        if (!is_array($answers) || ($answers[$id]??null)!==$correct) {
                            throw new DomainException('ลองทบทวนคำตอบสัปดาห์ที่ 1 แล้วส่งอีกครั้ง');
                        }
                    }
                } elseif (($input['reading_answer']??null)!=='week-'.$week.'-apply') {
                    throw new DomainException(anemiaReadingLessons()[$week][4].' ลองเลือกคำตอบอีกครั้ง');
                }
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
