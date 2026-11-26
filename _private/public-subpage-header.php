<?php if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; } ?>
<header class="public-header public-subpage-header">
 <div class="bar">
  <a class="brand" href="index.html"><span class="mark"><?= workspaceIcon('drop') ?></span><span>Anemia Portal<small>ประเมินและดูแลภาวะโลหิตจาง</small></span></a>
  <nav aria-label="เมนูหลัก">
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
   <a class="public-account-link" href="participant-account.php?return=<?= $publicNavPage==='programme'?'programme':'portal' ?>"><?= workspaceIcon('person') ?><?= ($publicNavPage==='feedback'||$account!==null)?'บัญชีของฉัน':'เข้าสู่ระบบ' ?></a>
  </nav>
 </div>
</header>
