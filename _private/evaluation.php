<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }
const EVALUATION_VERSION = 'satisfaction-v2';
const EVALUATION_LEGACY_VERSION = 'satisfaction-v1';
const EVALUATION_AUDIENCES = ['public'=>'ผู้ใช้งานทั่วไป','student'=>'นักเรียน / นักศึกษา','staff'=>'เจ้าหน้าที่'];
const EVALUATION_DOMAINS = [
    'design'=>'หน้าตาและเมนู','ease'=>'การใช้งาน','results'=>'ผลและคำแนะนำ',
    'data'=>'ข้อมูลและรายงาน','usefulness'=>'ประโยชน์ที่ได้รับ',
];
const EVALUATION_TOPICS = [
    'design'=>'ตัวหนังสือ ภาพ และการจัดหน้า','ease'=>'ขั้นตอนและปุ่มบนเว็บ',
    'results'=>'คำอธิบายผลและคำแนะนำ','data'=>'การค้นข้อมูลหรือรายงาน',
    'usefulness'=>'เนื้อหาความรู้และการติดตาม',
];
function evaluationItems(string $audience): array
{
    if ($audience === 'staff') return [
        'design_screen'=>['design','หน้าจอจัดเป็นระเบียบและอ่านง่าย'],
        'design_menu'=>['design','เข้าใจเมนูและรู้ว่าจะไปขั้นตอนไหนต่อ'],
        'ease_device'=>['ease','เปิดใช้ระบบได้สะดวกบนโทรศัพท์หรือคอมพิวเตอร์ที่ฉันใช้'],
        'ease_flow'=>['ease','ทำรายการคัดกรองและบันทึกข้อมูลได้สะดวก'],
        'results_level'=>['results','หน้าผลแสดงระดับความเสี่ยงได้ชัดเจน'],
        'results_next'=>['results','คำแนะนำบนหน้าผลเหมาะกับระดับความเสี่ยงที่แสดง'],
        'data_find'=>['data','ค้นหาและตรวจสอบข้อมูลในพื้นที่ที่รับผิดชอบได้สะดวก'],
        'data_report'=>['data','ใช้แดชบอร์ดและรายงานเพื่อสรุปข้อมูลที่รับผิดชอบได้'],
        'usefulness_read'=>['usefulness','ระบบช่วยให้ประชาชนเข้าถึงข้อมูลสุขภาพได้สะดวกขึ้น'],
        'usefulness_follow'=>['usefulness','ระบบช่วยให้ฉันติดตามและดูแลผู้ที่มีความเสี่ยงได้ต่อเนื่อง'],
    ];
    return [
        'design_screen'=>['design','ตัวหนังสือและหน้าจออ่านง่าย'],
        'design_menu'=>['design','หาเมนูที่ต้องการได้ง่าย'],
        'ease_device'=>['ease','ใช้งานบนอุปกรณ์ของฉันได้สะดวก'],
        'ease_flow'=>['ease','ตอบแบบประเมินได้โดยไม่ติดขัด'],
        'results_level'=>['results','เข้าใจผลประเมินที่แสดงบนหน้าจอ'],
        'results_next'=>['results','คำแนะนำช่วยให้รู้ว่าควรทำอะไรต่อ'],
        'data_find'=>['data','ค้นหาบทความหรือสถานที่ตรวจได้ง่าย'],
        'data_report'=>['data','ข้อมูลภาพรวมใน Open Data อ่านเข้าใจง่าย'],
        'usefulness_read'=>['usefulness','เว็บช่วยให้เข้าถึงข้อมูลสุขภาพได้สะดวก'],
        'usefulness_follow'=>['usefulness','เว็บช่วยให้รู้ว่าจะดูแลตัวเองหรือไปตรวจอย่างไร'],
    ];
}
function evaluationInput(array $post): ?array
{
    $audience = $post['audience'] ?? null;
    $province = $post['province'] ?? '';
    $answers = $post['answers'] ?? null;
    $topics = $post['topics'] ?? [];
    $overall = $post['overall'] ?? null;
    $comment = $post['comment'] ?? '';
    if (!is_string($comment) || !preg_match('/^.{0,500}$/usD',$comment)) return null;
    $comment = trim($comment);
    if (!is_string($audience) || !isset(EVALUATION_AUDIENCES[$audience]) ||
        !is_string($province) || ($province !== ''&&!preg_match('/^[0-9]{2}$/D',$province)) ||
        !is_string($overall) || !preg_match('/^[1-5]$/D',$overall) || !is_array($answers) || !is_array($topics)) return null;
    $items = evaluationItems($audience);
    if (count($answers) !== count($items)) return null;
    $ratings = [];
    foreach ($items as $key => $item) {
        $value = $answers[$key] ?? null;
        if (!is_string($value) || !preg_match('/^(?:[1-5]|na)$/D',$value)) return null;
        if ($value !== 'na') $ratings[$key] = (int)$value;
    }
    foreach ($topics as $topic) if (!is_string($topic) || !isset(EVALUATION_TOPICS[$topic])) return null;
    return ['audience'=>$audience,'province'=>$province?:null,'overall'=>(int)$overall,'ratings'=>$ratings,'topics'=>array_values(array_unique($topics)),'comment'=>$comment];
}
function storeEvaluation(PDO $db,string $id,array $input): void
{
    $db->beginTransaction();
    try {
        if ($input['province'] !== null) {
            $check=$db->prepare('SELECT code FROM anemia_province WHERE code=?');
            $check->execute([$input['province']]);
            if (!$check->fetchColumn()) throw new DomainException('กรุณาเลือกจังหวัดจากรายการ');
        }
        $insert=$db->prepare('INSERT INTO anemia_evaluation(submission_id,form_version,audience,province_code,overall,created_at) VALUES (?,?,?,?,?,?)');
        $insert->execute([$id,EVALUATION_VERSION,$input['audience'],$input['province'],$input['overall'],date('Y-m-d H:i:s')]);
        $insert=$db->prepare('INSERT INTO anemia_evaluation_answer(submission_id,item_key,rating) VALUES (?,?,?)');
        foreach ($input['ratings'] as $key=>$value) $insert->execute([$id,$key,$value]);
        $insert=$db->prepare('INSERT INTO anemia_evaluation_suggestion(submission_id,topic_key) VALUES (?,?)');
        foreach ($input['topics'] as $topic) $insert->execute([$id,$topic]);
        if (($input['comment'] ?? '') !== '') {
            $insert=$db->prepare('INSERT INTO anemia_evaluation_comment(submission_id,comment_text) VALUES (?,?)');
            $insert->execute([$id,$input['comment']]);
        }
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        if ($error instanceof PDOException && ($error->errorInfo[1] ?? null) === 1062) {
            $check=$db->prepare('SELECT submission_id FROM anemia_evaluation WHERE submission_id=?');
            $check->execute([$id]);
            if ($check->fetchColumn()) return;
        }
        throw $error;
    }
}
function evaluationReport(PDO $db,array $identity,int $region,string $audience,string $month,bool $includeComments=false,string $version=EVALUATION_VERSION): array
{
    [$scope,$params]=anemiaProvinceScope($identity,$region);
    $conditions=['e.form_version=?'];
    array_unshift($params,$version);
    if ($scope !== '') $conditions[]='p.'.$scope;
    if (isset(EVALUATION_AUDIENCES[$audience])) { $conditions[]='e.audience=?'; $params[]=$audience; }
    if ($month !== '') {
        $conditions[]='e.created_at>=? AND e.created_at<?';
        $start=new DateTimeImmutable($month.'-01');
        $params[]=$start->format('Y-m-d');
        $params[]=$start->modify('+1 month')->format('Y-m-d');
    }
    $base=' FROM anemia_evaluation e LEFT JOIN anemia_province p ON p.code=e.province_code';
    $where=' WHERE '.implode(' AND ',$conditions);
    $q=$db->prepare('SELECT COUNT(*) AS n,AVG(e.overall) AS average,STDDEV_SAMP(e.overall) AS sd,COALESCE(SUM(e.overall>=4),0) AS satisfied'.$base.$where);
    $q->execute($params);
    $overall=$q->fetch(PDO::FETCH_ASSOC);
    $perResponse = 'SELECT SUBSTRING_INDEX(a.item_key,\'_\',1) AS domain,e.submission_id,COUNT(*) AS n,AVG(a.rating) AS score'
        .$base.' JOIN anemia_evaluation_answer a ON a.submission_id=e.submission_id'.$where.' GROUP BY domain,e.submission_id';
    $q=$db->prepare('SELECT domain,SUM(n) AS n,COUNT(*) AS respondents,AVG(score) AS average,STDDEV_SAMP(score) AS sd FROM ('.$perResponse.') scores GROUP BY domain');
    $q->execute($params);
    $domains=$q->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);
    $q=$db->prepare('SELECT s.topic_key,COUNT(*) AS n'.$base.' JOIN anemia_evaluation_suggestion s ON s.submission_id=e.submission_id'.$where.' GROUP BY s.topic_key');
    $q->execute($params);
    $topics=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    $comments=[];
    if ($includeComments) {
        $q=$db->prepare('SELECT c.comment_text'.$base.' JOIN anemia_evaluation_comment c ON c.submission_id=e.submission_id'.$where.' ORDER BY e.created_at DESC,e.submission_id LIMIT 30');
        $q->execute($params);
        $comments=$q->fetchAll(PDO::FETCH_COLUMN);
    }
    return compact('overall','domains','topics','comments');
}
