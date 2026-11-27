<?php if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; } $publicNavSignedIn=$publicNavSignedIn??is_string($account??null); ?>
<header class="public-header public-subpage-header">
 <div class="bar">
  <a class="brand" href="index.html"><span class="mark"><?= workspaceIcon('drop') ?></span><span>Anemia Portal<small>ประเมินและดูแลภาวะโลหิตจาง</small></span></a>
  <button class="public-menu-toggle" type="button" aria-controls="public-navigation" aria-expanded="false" hidden><?= workspaceIcon('layout') ?>เมนู</button>
  <nav id="public-navigation" aria-label="เมนูหลัก">
   <a class="public-assessment-link" href="index.html?view=start"><?= workspaceIcon('ready') ?>ประเมินสุขภาพ</a>
   <details class="public-nav-group" name="subpage-navigation"><summary><?= workspaceIcon('book') ?>เรียนรู้<span class="public-nav-chevron" aria-hidden="true"></span></summary><div class="public-nav-links">
    <a href="index.html?view=learn">คลังความรู้</a><a href="participant-programme.php" <?= $publicNavPage==='programme'?'aria-current="page"':'' ?>>กิจกรรม 12 สัปดาห์</a>
   </div></details>
   <details class="public-nav-group" name="subpage-navigation"><summary><?= workspaceIcon('hospital') ?>ตรวจและติดตาม<span class="public-nav-chevron" aria-hidden="true"></span></summary><div class="public-nav-links">
    <a href="index.html?view=facility-directory">ค้นหาโรงพยาบาลและ รพ.สต.</a><a href="index.html?view=track">ติดตามคำขอ</a>
   </div></details>
   <details class="public-nav-group" name="subpage-navigation"><summary><?= workspaceIcon('info') ?>เกี่ยวกับโครงการ<span class="public-nav-chevron" aria-hidden="true"></span></summary><div class="public-nav-links">
    <a href="index.html?view=project">ข้อมูลโครงการ</a><a href="index.html?view=open-data">สถิติรายพื้นที่ (Open Data)</a><a href="feedback.php" <?= $publicNavPage==='feedback'?'aria-current="page"':'' ?>>ประเมินการใช้งาน</a>
   </div></details>
   <a class="public-account-link" data-public-account-guest <?= $publicNavSignedIn?'hidden':'' ?> href="participant-account.php?return=<?= $publicNavPage==='programme'?'programme':'portal' ?>"><?= workspaceIcon('person') ?>เข้าสู่ระบบ / สมัครสมาชิก</a>
   <details class="public-nav-group public-nav-account" name="subpage-navigation" <?= $publicNavSignedIn?'':'hidden' ?>>
    <summary class="public-account-link"><span><?= workspaceIcon('person') ?></span><img data-public-profile-photo width="24" height="24" alt="" hidden><span data-public-profile-name><?= htmlspecialchars(($publicNavDisplayName??'') ?: 'บัญชีของฉัน',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></span><span class="public-nav-chevron" aria-hidden="true"></span></summary>
    <div class="public-nav-links">
     <a href="participant-account.php?return=portal"><?= workspaceIcon('person') ?>โปรไฟล์</a>
     <a href="participant-account.php?return=portal#participant-activity-title"><?= workspaceIcon('star') ?>กิจกรรมและแต้ม</a>
     <a href="index.html?team=1"><?= workspaceIcon('users') ?>ทีมของฉัน</a>
     <a href="participant-account.php?return=portal#participant-settings"><?= workspaceIcon('lock') ?>ตั้งค่าบัญชี</a>
    </div>
   </details>
  </nav>
 </div>
</header>
