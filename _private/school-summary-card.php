<?php if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; } ?>
<section class="card admin-report hosted-live-summary staff-detail-card" aria-labelledby="school-summary-title"
 data-school-summary-url="<?= esc('dashboard.php?'.http_build_query(['view'=>'overview','region'=>$region,'school_summary'=>1]+($view==='programme-report'?['weekly'=>1]:[]))) ?>">
 <div class="hosted-registry-heading"><span class="iconbox"><?= workspaceIcon('school') ?></span><div>
  <h2 id="school-summary-title">กิจกรรมโรงเรียน</h2>
  <p>ข้อมูลจากระบบ · <?= esc($scopeName) ?> · ทุกปีการศึกษา</p>
 </div></div>
 <dl>
 <?php foreach (['schools'=>'โรงเรียนในทะเบียน','accepting'=>'ห้องที่เปิดรับ','members'=>'นักเรียนเข้าร่วม (คน)','started'=>'ส่งแบบประเมินก่อนเรียน (คน)','completed'=>'นักเรียนทำครบ (คน)'] as $metric=>$label): ?>
  <div><dt><?= esc($label) ?></dt><dd data-school-summary-metric="<?= esc($metric) ?>"><?= $schoolSummary===null?'—':number_format($schoolSummary[$metric]) ?></dd></div>
 <?php endforeach; ?>
 </dl>
 <a class="action staff-detail-link" href="<?= esc('dashboard.php?'.http_build_query(
  ['view'=>'schools','region'=>$region,'school_tab'=>'tracking']+($identity['role']==='provincial_report'?['province'=>$identity['province_code']]:[])
 )) ?>"><?= workspaceIcon('chart') ?>ติดตามกิจกรรมโรงเรียน</a>
 <p class="notice" data-school-summary-status role="status" <?= $schoolSummary!==null?'hidden':'' ?>>ยังโหลดข้อมูลกิจกรรมโรงเรียนไม่ได้</p>
</section>
