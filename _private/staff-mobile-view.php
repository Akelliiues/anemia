<?php
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }
?>
<section class="staff-mobile-home" aria-labelledby="staff-mobile-title">
 <header class="staff-mobile-hero">
  <span class="staff-mobile-mark"><?= workspaceIcon('drop') ?></span>
  <div><h1 id="staff-mobile-title">งานของฉัน</h1><small><?= esc($roleLabel) ?></small></div>
 </header>
 <nav class="staff-app-dock" aria-label="เมนูแอปเจ้าหน้าที่">
  <a href="dashboard.php?view=mobile" aria-current="page"><?= workspaceIcon('ready') ?>งานของฉัน</a>
  <a href="dashboard.php?view=overview"><?= workspaceIcon('chart') ?>ภาพรวม</a>
 </nav>
</section>
