<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$secure=!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
if (!$secure && PHP_SAPI!=='cli-server') { http_response_code(403); exit('เปิดหน้านี้ผ่าน HTTPS'); }
define('ANEMIA_STAFF_BOOT',true);
require __DIR__.'/_private/participant-account.php';
require __DIR__.'/_private/participant-programme.php';
require __DIR__.'/_private/icons.php';
anemiaParticipantSession($secure);
$account=$_SESSION['account']??null;
$epoch=(int)($_SESSION['epoch']??0);
$csrf=$_SESSION['csrf']??'';
if (($_SESSION['until']??0)<time()) $account=null;

function programmeEscape(string $value): string
{
    return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}

function programmeFormFields(string $csrf,string $action): void
{
    ?>
    <input type="hidden" name="csrf" value="<?= programmeEscape($csrf) ?>">
    <input type="hidden" name="programme_version" value="<?= programmeEscape(ANEMIA_PROGRAMME_VERSION) ?>">
    <input type="hidden" name="instrument_version" value="<?= programmeEscape(ANEMIA_PROGRAMME_INSTRUMENT_VERSION) ?>">
    <input type="hidden" name="action" value="<?= programmeEscape($action) ?>">
    <?php
}

$db=null;
$ready=false;
$notice='';
$success=false;
$completed=[];
$assessments=[];
$weeks=anemiaProgrammeWeeks();
if ($account!==null) {
    try {
        require_once __DIR__.'/_private/database.php';
        $config=require __DIR__.'/_private/config.php';
        $db=anemiaDatabase($config);
        initializeAnemia($db,$config);
        if (anemiaParticipantEpoch($db,$account)!==$epoch) {
            unset($_SESSION['account'],$_SESSION['epoch'],$_SESSION['until'],$_SESSION['oauth']);
            $account=null;
            throw new DomainException('เซสชันสิ้นสุดแล้ว กรุณาเข้าสู่ระบบใหม่');
        }
        ['completed'=>$completed,'assessments'=>$assessments]=anemiaProgrammeState($db,$account);
        $ready=true;
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            if ($csrf==='' || !is_string($_POST['csrf']??null) || !hash_equals($csrf,$_POST['csrf'])) {
                http_response_code(403);
                throw new DomainException('กรุณาเปิดหน้านี้ใหม่ก่อนบันทึก');
            }
            anemiaSaveProgrammeAction($db,$account,$epoch,$_POST);
            $_SESSION['programme_saved']=true;
            header('Location: participant-programme.php#programme-next',true,303);
            exit;
        }
        if (!empty($_SESSION['programme_saved'])) {
            unset($_SESSION['programme_saved']);
            $notice='บันทึกเรียบร้อย';
            $success=true;
        }
    } catch (DomainException $error) {
        $notice=$error->getMessage();
    } catch (Throwable $error) {
        error_log('Anemia participant programme: '.get_class($error));
        $notice='โหลดหรือบันทึกกิจกรรมไม่สำเร็จ กรุณาลองอีกครั้ง';
    }
} elseif ($_SERVER['REQUEST_METHOD']==='POST') {
    http_response_code(401);
    $notice='เซสชันสิ้นสุดแล้ว กรุณาเข้าสู่ระบบใหม่';
}
$next=anemiaProgrammeNextWeek($completed);
$phase=!isset($assessments['before'])?'before':($next===null && !isset($assessments['after'])?'after':null);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>กิจกรรม 12 สัปดาห์ · Anemia Portal</title>
<script src="theme.js?v=1"></script>
<link rel="stylesheet" href="style.css?v=portal-neumorphism-v71">
<script defer src="installed-update.js"></script>
<link rel="stylesheet" href="participant-programme.css?v=2">
<script defer src="participant-programme.js?v=1"></script>
</head>
<body class="participant-profile-page">
<main class="participant-account-main">
<article class="panel participant-account participant-programme">
  <nav class="programme-nav" aria-label="เมนู Anemia Portal">
    <a href="index.html"><?= workspaceIcon('back') ?> กลับหน้าหลัก</a>
    <?php if ($account!==null): ?><a href="participant-account.php?return=programme"><?= workspaceIcon('person') ?> บัญชีของฉัน</a><?php endif; ?>
  </nav>
  <header>
    <h1 class="title">กิจกรรมเรียนรู้ 12 สัปดาห์</h1>
    <p>ทำแบบประเมินก่อนเริ่ม บันทึกกิจกรรมตามลำดับ และตอบแบบประเมินหลังทำครบ คุณเลือกเวลาทำกิจกรรมแต่ละสัปดาห์ได้เอง</p>
    <p class="programme-draft"><strong>ฉบับทดลอง</strong> กิจกรรมและแบบประเมินนี้จัดทำเพื่อทดลองระบบ อยู่ระหว่างรอเครื่องมือที่โครงการอนุมัติ คะแนนใช้ทบทวนความรู้และไม่ใช้วินิจฉัยภาวะโลหิตจาง</p>
  </header>
  <?php if ($notice!==''): ?><p class="programme-notice" role="<?= $success?'status':'alert' ?>"><?= programmeEscape($notice) ?></p><?php endif; ?>
  <?php if ($account===null): ?>
    <section class="programme-card" aria-labelledby="programme-next">
      <h2 id="programme-next">เริ่มกิจกรรม</h2>
      <p>เข้าสู่ระบบเพื่อบันทึกความคืบหน้าไว้ในบัญชีของคุณ</p>
      <a class="action primary" href="participant-account.php?return=programme">เข้าสู่ระบบหรือสมัครบัญชี</a>
    </section>
  <?php elseif ($ready): ?>
    <section class="programme-card" aria-labelledby="programme-progress-label">
      <h2 id="programme-progress-label">ความคืบหน้าของฉัน</h2>
      <progress value="<?= count($completed) ?>" max="<?= count($weeks) ?>" aria-labelledby="programme-progress-label"></progress>
      <p>บันทึกแล้ว <?= count($completed) ?> จาก <?= count($weeks) ?> กิจกรรม</p>
      <p>ก่อนเริ่ม · <?= isset($assessments['before'])?'ส่งแล้ว':'ยังไม่ได้ส่ง' ?> <span aria-hidden="true"> | </span> หลังจบ · <?= isset($assessments['after'])?'ส่งแล้ว':'ยังไม่ได้ส่ง' ?></p>
    </section>
    <?php if ($phase!==null): ?>
      <section class="programme-card" aria-labelledby="programme-next">
        <h2 id="programme-next"><?= $phase==='before'?'แบบประเมินก่อนเริ่ม':'แบบประเมินหลังจบ' ?></h2>
        <p>เลือกคำตอบหนึ่งข้อในแต่ละคำถาม ระบบจะแสดงคะแนนทั้งสองครั้งหลังส่งแบบประเมินหลังจบ</p>
        <form method="post" action="participant-programme.php#programme-next">
          <?php programmeFormFields($csrf,$phase); ?>
          <?php foreach (anemiaProgrammeQuestions() as $number=>$question): ?>
            <fieldset class="programme-question">
              <legend><?= $number+1 ?>. <?= programmeEscape($question['text']) ?></legend>
              <?php foreach ($question['choices'] as $choice=>$label): ?>
                <label><input required type="radio" name="answers[<?= $number ?>]" value="<?= $choice ?>" <?= ($_POST['answers'][$number]??null)===$choice?'checked':'' ?>> <?= programmeEscape($label) ?></label>
              <?php endforeach; ?>
            </fieldset>
          <?php endforeach; ?>
          <?php if ($phase==='before'): ?>
            <label class="programme-acknowledge"><input required type="checkbox" name="acknowledge" value="1" <?= ($_POST['acknowledge']??null)==='1'?'checked':'' ?>> ฉันรับทราบว่าเป็นฉบับทดลอง และระบบจะบันทึกคำตอบและความคืบหน้าไว้กับบัญชีของฉัน</label>
          <?php endif; ?>
          <button class="action primary" type="submit"><?= $phase==='before'?'ส่งแบบประเมินก่อนเริ่ม':'ส่งแบบประเมินหลังจบ' ?></button>
        </form>
      </section>
    <?php elseif ($next!==null): ?>
      <section class="programme-card<?= $next===1?' programme-lesson':'' ?>" aria-labelledby="programme-next">
        <?php if ($next===1): ?><span class="programme-lesson-label">สัปดาห์ที่ 1 · คลิป 4 นาที</span><?php endif; ?>
        <h2 id="programme-next"><?= $next===1?'':'สัปดาห์ที่ '.$next.' · ' ?><?= programmeEscape($weeks[$next][0]) ?></h2>
        <p><?= programmeEscape($weeks[$next][1]) ?></p>
        <?php if ($next===1): ?>
         <video class="programme-video" controls playsinline preload="none" src="12weeks/week_01.mp4" aria-label="คลิปรู้จักโลหิตจาง"></video>
         <p data-lesson-progress role="status" class="programme-video-status">กดเล่นเพื่อเริ่มดูคลิป หรืออ่านสรุปด้านล่าง</p>
         <details class="programme-reading" data-lesson-reading><summary>อ่านสรุปบทเรียน</summary>
          <p>ฮีโมโกลบินอยู่ในเม็ดเลือดแดงและช่วยพาออกซิเจนไปทั่วร่างกาย อาการเหนื่อยง่ายหรือเวียนหัวเกิดได้จากหลายสาเหตุ การตรวจเลือดช่วยประเมินภาวะโลหิตจางได้ หากแบบประเมินออนไลน์แนะนำให้ตรวจเพิ่ม คุณติดต่อหน่วยบริการเพื่อปรึกษาเรื่องการตรวจได้เลย</p>
          <a class="programme-resource" href="index.html?view=learn" target="_blank" rel="noopener">อ่านต่อในคลังความรู้</a>
          <button class="action" type="button" data-lesson-read hidden>อ่านสรุปแล้ว</button>
         </details>
        <?php elseif ($weeks[$next][2]!==''): ?><a class="programme-resource" href="<?= programmeEscape($weeks[$next][2]) ?>" target="_blank" rel="noopener">เปิดหน้าที่ใช้ทำกิจกรรม (แท็บใหม่)</a><?php endif; ?>
        <form method="post" action="participant-programme.php#programme-next">
          <?php programmeFormFields($csrf,'week'); ?>
          <input type="hidden" name="week" value="<?= $next ?>">
          <?php if ($next===1): ?>
           <h3>ทบทวนหลังเรียน</h3>
           <?php foreach (anemiaWeekOneQuestions() as $id=>[$question,$choices,$correct,$feedback]): ?>
            <fieldset class="programme-question" data-lesson-question data-correct="<?= programmeEscape($correct) ?>">
             <legend><?= programmeEscape($question) ?></legend>
             <?php foreach ($choices as $value=>$label): ?>
              <label><input type="radio" name="lesson_answers[<?= programmeEscape($id) ?>]" value="<?= programmeEscape($value) ?>" required> <?= programmeEscape($label) ?></label>
             <?php endforeach; ?>
             <p class="programme-answer" data-lesson-answer role="status" hidden><?= programmeEscape($feedback) ?></p>
            </fieldset>
           <?php endforeach; ?>
          <?php endif; ?>
          <button class="action primary" type="submit"><?= $next===1?'บันทึกและไปสัปดาห์ถัดไป':'บันทึกว่าทำกิจกรรมแล้ว' ?></button>
        </form>
      </section>
    <?php else:
        $before=(int)$assessments['before']['score'];
        $after=(int)$assessments['after']['score'];
        $change=$after-$before; ?>
      <section class="programme-card" aria-labelledby="programme-next">
        <h2 id="programme-next">คะแนนแบบประเมินฉบับทดลอง</h2>
        <p>ก่อนเริ่ม <strong><?= $before ?> / <?= (int)$assessments['before']['max_score'] ?></strong></p>
        <p>หลังจบ <strong><?= $after ?> / <?= (int)$assessments['after']['max_score'] ?></strong></p>
        <p>ผลต่าง (หลังจบ − ก่อนเริ่ม) <strong><?= $change>0?'+':'' ?><?= $change ?> คะแนน</strong></p>
      </section>
    <?php endif; ?>
  <?php endif; ?>
  <section aria-labelledby="programme-plan">
    <h2 id="programme-plan">กิจกรรมรายสัปดาห์</h2>
    <ol class="programme-weeks">
      <?php foreach ($weeks as $week=>[$title,$activity,$link]): ?>
        <li <?= $ready && isset($assessments['before']) && $week===$next?'aria-current="step"':'' ?>>
          <details>
            <summary>
              <span>สัปดาห์ที่ <?= $week ?> · <?= programmeEscape($title) ?></span>
              <?php if (in_array($week,$completed,true)): ?><small>บันทึกแล้ว</small><?php endif; ?>
            </summary>
            <p><?= programmeEscape($activity) ?></p>
          </details>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
</article>
</main>
</body>
</html>
