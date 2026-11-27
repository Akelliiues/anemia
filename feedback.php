<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
if (!$secure && PHP_SAPI !== 'cli-server') { http_response_code(403); exit('กรุณาเปิดหน้าแบบประเมินผ่าน HTTPS'); }
ini_set('session.use_strict_mode','1');
session_name('anemia_feedback');
session_set_cookie_params(['secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);
session_start();
date_default_timezone_set('Asia/Bangkok');
define('ANEMIA_STAFF_BOOT',true);
require __DIR__ . '/_private/icons.php';
require __DIR__.'/_private/evaluation.php';
require __DIR__.'/_private/database.php';
function escFeedback(string $value): string { return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
const FEEDBACK_DOMAIN_ICONS = ['design'=>'layout','ease'=>'pointer','results'=>'report','data'=>'chart','usefulness'=>'spark'];

function ratings(string $name, string $question, string $selected = '', bool $allowSkip = true): void
{
    $levels = ['1'=>'น้อยที่สุด','2'=>'น้อย','3'=>'ปานกลาง','4'=>'มาก','5'=>'มากที่สุด'];
    $orbit = '<svg class="feedback-orbit" aria-hidden="true">' . str_repeat('<rect x="3" y="3" rx="9"/>', 5) . '</svg>';
    ?>
    <fieldset class="feedback-question">
     <legend><?php if (!$allowSkip): ?><span class="feedback-heading-icon"><?= workspaceIcon('rating-4') ?></span><?php endif; ?><?= escFeedback($question) ?></legend>
     <div class="feedback-ratings">
      <label class="feedback-tile feedback-skip"><input type="radio" name="<?= escFeedback($name) ?>" value="na" <?= $allowSkip ? ($selected === 'na' ? 'checked' : '') : 'disabled' ?>>
       <span><span class="feedback-face" aria-hidden="true">−</span><span>ยังไม่ได้ใช้ส่วนนี้</span><?= $orbit ?></span>
      </label>
      <?php foreach ($levels as $value=>$label): ?>
       <label class="feedback-tile"><input type="radio" name="<?= escFeedback($name) ?>" value="<?= $value ?>" required <?= (string)$value===$selected?'checked':'' ?>>
        <span><span class="feedback-face" aria-hidden="true"><?= workspaceIcon('rating-' . $value) ?></span><span><?= escFeedback($label) ?></span><?= $orbit ?></span>
       </label>
      <?php endforeach; ?>
     </div>
    </fieldset>
    <?php
}
$audience=$_GET['group']??'public';
if (!is_string($audience)||!isset(EVALUATION_AUDIENCES[$audience])) $audience='public';
$sent=($_GET['sent']??'')==='1' && ($_SESSION['feedback_sent']??false);
$problem='';
$unavailable=false;
$provinces=[];
$_SESSION['feedback_token']??=bin2hex(random_bytes(32));
$token=$_SESSION['feedback_token'];
try {
    $config=require __DIR__.'/_private/config.php';
    $db=anemiaDatabase($config);
    initializeAnemia($db,$config);
    $provinces=$db->query('SELECT code,name FROM anemia_province ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $csrf=$_POST['token']??null;
        if (!is_string($csrf)||!hash_equals($token,$csrf)) {
            http_response_code(403);
            $problem='แบบประเมินหมดอายุ กรุณาเปิดหน้าใหม่';
        } else {
            $input=evaluationInput($_POST);
            if (!$input || $input['audience'] !== $audience) {
                http_response_code(422);
                $problem='กรุณาเลือกคะแนนรวมและตอบแต่ละข้อ หากยังไม่ได้ใช้ส่วนนั้นให้เลือกตามจริง';
            } else {
                try {
                    storeEvaluation($db,hash('sha256',$token),$input);
                    $_SESSION['feedback_sent']=true;
                    $_SESSION['feedback_token']=bin2hex(random_bytes(32));
                    header('Location: feedback.php?sent=1',true,303);
                    exit;
                } catch (DomainException $error) {
                    http_response_code(422);
                    $problem=$error->getMessage();
                }
            }
        }
    }
} catch (Throwable $error) {
    http_response_code(503);
    error_log('Anemia feedback unavailable ('.get_class($error).')');
    $unavailable=true;
    $problem='ตอนนี้ยังบันทึกแบบประเมินไม่ได้ กรุณาลองใหม่ภายหลัง';
}
function feedbackOld(string $key): string {
    $value=$_POST[$key]??'';
    return is_string($value)?$value:'';
}
?>
<!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ประเมินการใช้งาน · Anemia Portal</title>

<script src="theme.js?v=5"></script><script defer src="installed-update.js"></script><link rel="stylesheet" href="style.css?v=portal-ui-v106"></head>
<body class="feedback-page"><?php $publicNavPage='feedback'; require __DIR__.'/_private/public-subpage-header.php'; ?><main><section class="panel">

<header class="knowledge-hero feedback-hero">
 <div class="feedback-hero-copy"><h1 class="title">บอกเราว่าใช้เว็บเป็นอย่างไร</h1><p>ตอบตามประสบการณ์ของคุณเพื่อช่วยปรับปรุงเว็บไซต์</p></div>
 <div class="feedback-hero-art" aria-hidden="true"><span class="feedback-hero-orbit"></span><span class="feedback-hero-mark"><?= workspaceIcon('message') ?></span></div>
</header>
<?php if ($problem!==''): ?><p class="staff-access-alert" role="alert"><?= escFeedback($problem) ?></p><?php endif; ?>
<?php if ($unavailable): ?>
<?php elseif ($sent): ?>
<p class="status" role="status">ขอบคุณครับ บันทึกแบบประเมินเรียบร้อยแล้ว</p>
<?php else: ?>
<p class="notice">ร่วมตอบได้ตามสมัครใจ แบบประเมินนี้ไม่ขอชื่อ เบอร์โทร หรือผลสุขภาพ คำตอบใช้รายงานภาพรวมของโครงการ</p>
<nav class="feedback-groups" aria-label="เลือกกลุ่มผู้ตอบ">
<?php foreach (EVALUATION_AUDIENCES as $key=>$label): ?>
<a class="action <?= $key===$audience?'primary':'' ?>" href="feedback.php?group=<?= escFeedback($key) ?>" <?= $key===$audience?'aria-current="page"':'' ?>><?= workspaceIcon('users') ?><?= escFeedback($label) ?></a>
<?php endforeach; ?>
</nav>
<?php if ($audience === 'staff'): ?><p class="notice">ตอบจากงานที่คุณได้ลองทำจริง หากยังไม่ได้ใช้ฟีเจอร์นั้น ให้เลือก “ยังไม่ได้ใช้ส่วนนี้”</p><?php endif; ?>
<form class="feedback-form" method="post" action="feedback.php?group=<?= escFeedback($audience) ?>">
<input type="hidden" name="token" value="<?= escFeedback($token) ?>">
<input type="hidden" name="audience" value="<?= escFeedback($audience) ?>">
<?php
 $selectedProvince = feedbackOld('province');
 $provinceNames = array_column($provinces,'name','code');
?>
<details class="feedback-province">
 <summary><?= workspaceIcon('map') ?><span>จังหวัดที่ใช้งาน <small>ไม่บังคับ</small></span><strong id="feedback-province-label"><?= escFeedback($provinceNames[$selectedProvince] ?? 'ไม่ระบุจังหวัด') ?></strong></summary>
 <label for="feedback-province-search">ค้นหาชื่อจังหวัด</label>
 <input id="feedback-province-search" type="search" placeholder="เช่น อุบลราชธานี" autocomplete="off">
 <div class="feedback-province-list" role="group" aria-label="เลือกจังหวัด">
  <label class="feedback-tile"><input type="radio" name="province" value="" <?= !isset($provinceNames[$selectedProvince])?'checked':'' ?>><span>ไม่ระบุจังหวัด</span></label>
  <?php foreach ($provinces as $province): ?>
   <label class="feedback-tile" data-province-name="<?= escFeedback($province['name']) ?>">
    <input type="radio" name="province" value="<?= escFeedback($province['code']) ?>" <?= $selectedProvince===$province['code']?'checked':'' ?>><span><?= escFeedback($province['name']) ?></span>
   </label>
  <?php endforeach; ?>
 </div>
 <p id="feedback-province-empty" role="status" hidden>ไม่พบจังหวัดที่ค้นหา</p>
</details>
<div class="card feedback-overall"><?php ratings('overall','โดยรวมแล้ว คุณพอใจกับเว็บแค่ไหน?',feedbackOld('overall'),false); ?></div>
<?php foreach(EVALUATION_DOMAINS as $domain=>$label): ?>
<fieldset class="card feedback-group"><legend><span class="feedback-heading-icon"><?= workspaceIcon(FEEDBACK_DOMAIN_ICONS[$domain]) ?></span><?= escFeedback($label) ?></legend>
 <?php foreach(evaluationItems($audience) as $key=>[$itemDomain,$question]): if($domain!==$itemDomain)continue;
 $old=is_array($_POST['answers']??null)&&is_string($_POST['answers'][$key]??null)?$_POST['answers'][$key]:''; ?>
 <?php ratings('answers['.$key.']',$question,$old); ?>
 <?php endforeach; ?>
</fieldset>
<?php endforeach; ?>
<fieldset class="card feedback-group feedback-topics"><legend><span class="feedback-heading-icon"><?= workspaceIcon('message') ?></span>อยากให้ปรับเรื่องไหน? <small>ไม่บังคับ · เลือกได้หลายเรื่อง</small></legend>
<?php foreach(EVALUATION_TOPICS as $key=>$label): ?><label><input type="checkbox" name="topics[]" value="<?= escFeedback($key) ?>" <?= is_array($_POST['topics']??null)&&in_array($key,$_POST['topics'],true)?'checked':'' ?>> <?= escFeedback($label) ?></label><?php endforeach; ?>
<div class="feedback-comment">
 <label for="feedback-comment">ข้อเสนอแนะเพิ่มเติม <small>เว้นว่างได้</small></label>
 <textarea id="feedback-comment" name="comment" rows="3" maxlength="500" aria-describedby="feedback-comment-help" placeholder="เช่น อยากให้ปรับขั้นตอนไหน หรืออยากให้เพิ่มอะไร"><?= escFeedback(feedbackOld('comment')) ?></textarea>
 <p id="feedback-comment-help" class="notice">พิมพ์ได้ไม่เกิน 500 ตัวอักษร กรุณาเว้นชื่อ เบอร์โทร และข้อมูลสุขภาพส่วนตัว</p>
</div>
</fieldset>
<button class="action primary" type="submit"><?= workspaceIcon('send') ?>ส่งแบบประเมิน</button>
</form>
<?php endif; ?>
</section></main>
<script>
const orbitAnimations = new WeakMap();
function runFeedbackOrbit(input) {
 if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
 const question = input.closest('.feedback-question');
 const previous = orbitAnimations.get(question);
 if (previous) {
  cancelAnimationFrame(previous.frame);
  previous.svg.classList.remove('is-running');
 }
 const svg = input.nextElementSibling.querySelector('.feedback-orbit');
 svg.classList.add('is-running');
 const strokes = [...svg.querySelectorAll('rect')];
 const perimeter = strokes[0].getTotalLength();
 if (!perimeter) { svg.classList.remove('is-running'); return; }
 const lengths = [.32,.25,.17,.10,.04].map(part => perimeter * part);
 strokes.forEach((stroke, index) => {
  const length = lengths[index];
  stroke.setAttribute('stroke-dasharray', length + ' ' + (perimeter - length));
  stroke.setAttribute('stroke-dashoffset', length);
 });
 const lapDuration = 760;
 const fadeInDuration = 130;
 const fadeOutDuration = 220;
 const start = performance.now();
 const animation = {svg,frame:0};
 const draw = now => {
  const elapsed = now - start;
  const progress = Math.min(elapsed / lapDuration, 1);
  const fadeIn = Math.min(elapsed / fadeInDuration, 1);
  const fadeOut = Math.max(0, 1 - Math.max(0, elapsed - lapDuration) / fadeOutDuration);
  strokes.forEach((stroke,index) => stroke.setAttribute('stroke-dashoffset', lengths[index] - perimeter * progress));
  svg.style.opacity = String(fadeIn * fadeOut);
  if (fadeOut > 0) animation.frame = requestAnimationFrame(draw);
  else { svg.classList.remove('is-running'); orbitAnimations.delete(question); }
 };
 svg.style.opacity = '0';
 orbitAnimations.set(question,animation);
 animation.frame = requestAnimationFrame(draw);
}
document.querySelector('.feedback-form')?.addEventListener('click', event => {
 if (event.target.matches('.feedback-question input[type="radio"]')) runFeedbackOrbit(event.target);
});
document.querySelector('.feedback-form')?.addEventListener('change', event => {
 const input = event.target;
 if (input.matches('.feedback-question input[type="radio"]') && !input.nextElementSibling.querySelector('.feedback-orbit.is-running')) runFeedbackOrbit(input);
});
const provincePicker = document.querySelector('.feedback-province');
if (provincePicker) {
 const search = document.getElementById('feedback-province-search');
 const choices = [...provincePicker.querySelectorAll('[data-province-name]')];
 search.addEventListener('input', () => {
  const query = search.value.trim();
  choices.forEach(choice => { choice.hidden = !choice.dataset.provinceName.includes(query); });
  document.getElementById('feedback-province-empty').hidden = choices.some(choice => !choice.hidden);
 });
 provincePicker.addEventListener('change', event => {
  if (event.target.name !== 'province') return;
  document.getElementById('feedback-province-label').textContent = event.target.closest('label').textContent.trim();
 });
}
</script></body></html>
