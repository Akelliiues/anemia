<?php
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }
?>
<section class="staff-mobile-home" aria-labelledby="staff-mobile-title">
 <header class="staff-mobile-hero">
  <span class="staff-mobile-mark"><?= workspaceIcon('drop') ?></span>
  <div><h1 id="staff-mobile-title">งานของฉัน</h1><small><?= esc($roleLabel) ?></small></div>
 </header>
 <?php if ($canQueue): ?>
 <div class="staff-mobile-metrics" aria-label="คำขอรอประสาน">
  <?php foreach (['appointment'=>['calendar','รอรับเรื่องนัดตรวจ'],'callback'=>['phone','รอรับเรื่องติดต่อกลับ']] as $kind=>[$icon,$label]): ?>
   <a class="card staff-mobile-metric" href="dashboard.php?view=contact-queue&amp;attention=1">
    <span class="iconbox"><?= workspaceIcon($icon) ?></span><span><small><?= esc($label) ?></small><strong data-pending-count="live.<?= $kind ?>"><?= number_format((int)$pendingContactCounts[$kind]) ?></strong></span>
   </a>
  <?php endforeach; ?>
 </div>
 <nav class="staff-mobile-tools" aria-label="เครื่องมือเจ้าหน้าที่">
  <a class="staff-mobile-scan" href="dashboard.php?view=scan-request&amp;app=unit"><span class="staff-mobile-mark"><?= workspaceIcon('scan') ?></span><span><strong>สแกน QR คำขอ</strong><small>เปิดกล้องหรือเลือกภาพใบรับคำขอ</small></span><?= workspaceIcon('next') ?></a>
  <a class="card staff-mobile-tool" href="dashboard.php?view=contact-queue"><span class="iconbox"><?= workspaceIcon('calendar') ?></span><span><strong>คิวนัดหมาย</strong><small>เปิดและจัดการคำขอ</small></span><?= workspaceIcon('next') ?></a>
  <a class="card staff-mobile-tool" href="dashboard.php?view=contact-queue&amp;attention=1"><span class="iconbox"><?= workspaceIcon('history') ?></span><span><strong>ติดตามงาน</strong><small>ดูรายการที่ต้องดำเนินการ</small></span><?= workspaceIcon('next') ?></a>
 </nav>
 <a class="action staff-mobile-overview" data-scanner-install-link href="dashboard.php?view=scanner-install"><?= workspaceIcon('download') ?>ติดตั้งแอปสแกน QR ประจำหน่วย</a>
 <?php endif; ?>
 <nav class="staff-app-dock" aria-label="เมนูแอปเจ้าหน้าที่">
  <a href="dashboard.php?view=mobile" aria-current="page"><?= workspaceIcon('ready') ?>งานของฉัน</a>
  <a href="dashboard.php?view=overview"><?= workspaceIcon('chart') ?>ภาพรวม</a>
 </nav>
</section>
