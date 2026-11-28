<?php
declare(strict_types=1);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
define('ANEMIA_STAFF_BOOT',true);
require __DIR__.'/_private/icons.php';

ini_set('session.use_strict_mode','1');
session_name('anemia_practice_session');
session_set_cookie_params(['path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
session_start();

$roles=[
    'public'=>['ทั่วไป','person'],
    'teacher'=>['ครู','book'],
    'student'=>['นักเรียน','ready'],
    'region'=>['ผู้ดูแลเขตสุขภาพ','map'],
];
$role=$_GET['role']??'';
if (!is_string($role) || ($role!=='' && !isset($roles[$role]))) {
    http_response_code(404);
    exit('ไม่พบบทบาททดลองนี้');
}
$initial=['assessment'=>false,'appointment'=>'','accepting'=>true,'target'=>30,
    'joined'=>false,'before'=>false,'weeks'=>0,'after'=>false,'coordinator'=>false];
$state=$_SESSION['practice']??$initial;
$csrf=$_SESSION['csrf']??=bin2hex(random_bytes(32));
$url='practice.php'.($role!==''?'?role='.$role:'');

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!is_string($_POST['csrf']??null) || !hash_equals($csrf,$_POST['csrf'])) {
        http_response_code(403);
        exit('คำขอหมดอายุ กรุณาเปิดพื้นที่ทดลองใหม่');
    }
    $action=$_POST['action']??null;
    $allowed=[
        ''=>['reset'],
        'public'=>['reset','assessment','appointment','cancel-appointment'],
        'teacher'=>['reset','toggle-room','target'],
        'student'=>['reset','join','before','lesson','after'],
        'region'=>['reset','coordinator'],
    ];
    if (!in_array($action,$allowed[$role],true)) {
        http_response_code(403);
        exit('บทบาทนี้ทำรายการดังกล่าวไม่ได้');
    }
    switch ($action) {
        case 'reset': $state=$initial; break;
        case 'assessment': $state['assessment']=true; break;
        case 'appointment':
            if ($state['assessment'] && $state['appointment']==='') $state['appointment']='pending';
            break;
        case 'cancel-appointment':
            if ($state['appointment']==='pending') $state['appointment']='';
            break;
        case 'toggle-room':
            if (!$state['coordinator']) { http_response_code(403); exit('เฉพาะผู้ประสานงานโรงเรียนที่เปิดหรือปิดรับได้'); }
            $state['accepting']=!$state['accepting'];
            break;
        case 'target':
            $target=filter_var($_POST['target']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>150]]);
            if ($target===false) { http_response_code(422); exit('กรอกเป้าหมายระหว่าง 1 ถึง 150 คน'); }
            $state['target']=$target;
            break;
        case 'join': if ($state['accepting']) $state['joined']=true; break;
        case 'before': if ($state['joined']) $state['before']=true; break;
        case 'lesson': if ($state['before'] && $state['weeks']<12) $state['weeks']++; break;
        case 'after': if ($state['weeks']===12) $state['after']=true; break;
        case 'coordinator': $state['coordinator']=true; break;
    }
    $_SESSION['practice']=$state;
    header('Location: '.$url,true,303);
    exit;
}
session_write_close();

function practiceEscape(string $text): string { return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function practiceButton(string $csrf,string $action,string $label,string $icon,bool $primary=false): void
{
    ?><form method="post"><input type="hidden" name="csrf" value="<?= practiceEscape($csrf) ?>">
    <button class="action<?= $primary?' primary':'' ?>" name="action" value="<?= practiceEscape($action) ?>"><?= workspaceIcon($icon) ?> <?= practiceEscape($label) ?></button></form><?php
}
$status=$state['appointment']==='pending'?'รอหน่วยบริการรับเรื่อง':'ยังไม่มีคำขอ';
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>พื้นที่ทดลองใช้งาน · Anemia Portal</title>
<script src="theme.js?v=5"></script><script defer src="installed-update.js"></script>
<link rel="stylesheet" href="style.css?v=practice-v1"><link rel="stylesheet" href="practice.css?v=1">
</head><body class="staff-dashboard-page"><main class="practice-layout">
 <header class="practice-header">
  <a href="participant-account.php?return=portal"><?= workspaceIcon('back') ?> กลับไปเข้าสู่ระบบ</a>
  <h1><?= workspaceIcon('drop') ?> พื้นที่ทดลองใช้งาน</h1>
  <p>สลับบทบาทเพื่อดูผลจากงานที่ทำไว้ในเบราว์เซอร์นี้ ใช้ข้อมูลจำลองทั้งหมด</p>
 </header>
 <nav class="practice-roles" aria-label="เลือกบทบาททดลอง">
  <?php foreach ($roles as $id=>[$label,$icon]): ?>
   <a class="action" href="practice.php?role=<?= $id ?>" <?= $role===$id?'aria-current="page"':'' ?>><?= workspaceIcon($icon) ?> <?= $label ?></a>
  <?php endforeach; ?>
 </nav>
 <?php if ($role!==''): ?>
 <section class="card practice-panel">
  <?php if ($role==='public'): ?>
   <h2>ประเมินและขอนัดตรวจ</h2>
   <p>กรณีตัวอย่าง ผู้รับบริการสนใจเข้ารับการตรวจเพิ่มเติม</p>
   <?php if (!$state['assessment']): ?>
    <?php practiceButton($csrf,'assessment','ส่งแบบประเมินตัวอย่าง','ready',true); ?>
   <?php else: ?>
    <p class="practice-status">ผลตัวอย่าง · แนะนำให้นัดตรวจเพิ่มเติม</p>
    <p>สถานะคำขอ <strong><?= $status ?></strong></p>
    <?php if ($state['appointment']===''): practiceButton($csrf,'appointment','ส่งคำขอนัดตรวจจำลอง','calendar',true);
    elseif ($state['appointment']==='pending'): practiceButton($csrf,'cancel-appointment','ยกเลิกคำขอจำลอง','close'); endif; ?>
   <?php endif; ?>
  <?php elseif ($role==='teacher'): ?>
   <h2>ห้องที่รับผิดชอบ · ม.4/1</h2>
   <p class="practice-status"><?= $state['accepting']?'เปิดรับนักเรียน':'ปิดรับนักเรียน' ?></p>
   <dl class="practice-metrics"><div><dt>เป้าหมาย</dt><dd><?= $state['target'] ?> คน</dd></div>
    <div><dt>เข้าร่วมแล้ว</dt><dd><?= (int)$state['joined'] ?> คน</dd></div>
    <div><dt>ทำครบแล้ว</dt><dd><?= (int)$state['after'] ?> คน</dd></div></dl>
   <?php if ($state['joined']): ?><p>นักเรียนตัวอย่าง · ผ่าน <?= $state['weeks'] ?>/12 บท · <?= $state['after']?'ส่งแบบประเมินหลังเรียนแล้ว':($state['before']?'ส่งแบบประเมินก่อนเรียนแล้ว':'รอแบบประเมินก่อนเรียน') ?></p><?php endif; ?>
   <div class="practice-actions">
    <?php if ($state['coordinator']) practiceButton($csrf,'toggle-room',$state['accepting']?'ปิดรับนักเรียน':'เปิดรับนักเรียน','power'); ?>
    <form method="post" class="practice-target"><input type="hidden" name="csrf" value="<?= $csrf ?>">
     <label>เป้าหมาย (คน)<input type="number" name="target" required min="1" max="150" value="<?= $state['target'] ?>"></label>
     <button class="action primary" name="action" value="target"><?= workspaceIcon('save') ?> บันทึกเป้าหมาย</button>
    </form>
   </div>
   <?php if (!$state['coordinator']): ?><p class="notice">ทดลองเปิดหรือปิดรับได้หลังแต่งตั้งครูตัวอย่างเป็นผู้ประสานงานจากบทบาทผู้ดูแลเขตสุขภาพ</p><?php endif; ?>
  <?php elseif ($role==='student'): ?>
   <h2>กิจกรรมเรียนรู้ 12 สัปดาห์ · ม.4/1</h2>
   <?php if (!$state['joined']): ?>
    <?php if ($state['accepting']): practiceButton($csrf,'join','เข้าร่วมห้องตัวอย่าง','login',true);
    else: ?><p class="practice-status">ห้องนี้ปิดรับนักเรียน ลองสลับไปบทบาทครูเพื่อเปิดรับ</p><?php endif; ?>
   <?php else: ?>
    <p class="practice-status">ผ่าน <?= $state['weeks'] ?>/12 บท<?= $state['after']?' · ทำกิจกรรมครบแล้ว':'' ?></p>
    <?php if (!$state['before']): practiceButton($csrf,'before','ส่งแบบประเมินก่อนเรียนตัวอย่าง','layout',true);
    elseif ($state['weeks']<12): practiceButton($csrf,'lesson','บันทึกว่าผ่านบทที่ '.($state['weeks']+1),'book',true);
    elseif (!$state['after']): practiceButton($csrf,'after','ส่งแบบประเมินหลังเรียนตัวอย่าง','ready',true); endif; ?>
   <?php endif; ?>
  <?php else: ?>
   <h2>ภาพรวมเขตสุขภาพที่ 10</h2>
   <p>โรงเรียนตัวอย่าง · จังหวัดอุบลราชธานี</p>
   <dl class="practice-metrics"><div><dt>ส่งแบบประเมิน</dt><dd><?= (int)$state['assessment'] ?> ครั้ง</dd></div>
    <div><dt>คำขอนัด</dt><dd><?= (int)($state['appointment']!=='') ?> รายการ</dd></div>
    <div><dt>นักเรียนทำครบ</dt><dd><?= (int)$state['after'] ?> คน</dd></div></dl>
   <p>ผู้ประสานงานโรงเรียน <strong><?= $state['coordinator']?'ครูตัวอย่าง':'ยังไม่แต่งตั้ง' ?></strong></p>
   <?php if (!$state['coordinator']) practiceButton($csrf,'coordinator','แต่งตั้งครูตัวอย่าง','users',true); ?>
   <p>สถานะคำขอนัด <strong><?= $status ?></strong></p>
  <?php endif; ?>
 </section>
 <?php else: ?>
 <section class="card practice-panel"><h2>เลือกบทบาทเพื่อเริ่มทดลอง</h2>
 <p>ลองเข้าห้องจากบทบาทนักเรียน แล้วสลับไปดูความคืบหน้าที่ห้องของครูหรือภาพรวมเขตสุขภาพ</p></section>
 <?php endif; ?>
 <footer class="practice-footer">
  <?php practiceButton($csrf,'reset','เริ่มข้อมูลจำลองใหม่','refresh'); ?>
 </footer>
</main></body></html>
