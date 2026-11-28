<?php
if (!defined('ANEMIA_STAFF_BOOT') || empty($identity) || $identity['role']==='school_teacher') {
    http_response_code(404);
    exit;
}
$featureHighlights = [
    ['contact-queue', 'คำขอเดียว ตั้งแต่รับเรื่องถึงติดตามผล', 'รับเรื่อง นัดหมาย สแกน QR และบันทึกผลต่อในรายการเดียว'],
    ['overview', 'เห็นภาพทั้งเขตบนแผนที่', 'ดูยอดทั้งปีตามเขต แล้วเปิดรายงานรายเดือนเพื่อดูรายละเอียด'],
    ['schools', 'ดูแลหลายโรงเรียนได้จากหลังบ้านเดียว', 'เลือกจังหวัดเพื่อดูโรงเรียนที่เข้าร่วมและติดตามภาพรวมตามสิทธิ์'],
    ['reports', 'ส่งต่อข้อมูลเป็นรายงาน PDF', 'พิมพ์หรือดาวน์โหลดตารางภาษาไทยตามเขตและเดือนที่เลือก'],
    ['voice-coach', 'Voice Coach', 'จัดการข้อความและไฟล์เสียงที่เผยแพร่แยกกันในแต่ละหัวข้อ'],
];
?>
<section class="card admin-report guide-catalog" aria-label="ฟีเจอร์เด่นสำหรับเจ้าหน้าที่">
 <div class="feature-pitch">
  <h2>ติดตามงานจากคำขอรายคนถึงภาพรวมเขตสุขภาพ</h2>
  <p>Anemia Portal รวมข้อมูลที่ใช้ประสานงาน ติดตามผล และออกรายงานไว้ในหลังบ้านตามสิทธิ์ของแต่ละบัญชี</p>
 </div>
 <div class="feature-grid feature-orbit">
  <svg class="feature-orbit-links" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
   <path d="M0 16.67L50 50L100 16.67M0 50H50" vector-effect="non-scaling-stroke"/>
   <path class="feature-orbit-link-fourth" d="M100 83.33L50 50" vector-effect="non-scaling-stroke"/>
   <path class="feature-orbit-link-fifth" d="M0 83.33L50 50" vector-effect="non-scaling-stroke"/>
  </svg>
  <div class="feature-hub"><?= workspaceIcon('drop') ?><strong>Anemia Portal</strong><span>ประสานงาน · ติดตาม · รายงาน</span></div>
 <?php foreach ($featureHighlights as [$destination,$title,$description]):
    if (!isset($tabs[$destination])) continue;
 ?>
  <article class="feature-card<?= $destination==='voice-coach'?' feature-card-voice':'' ?>">
   <span class="feature-card-icon"><?= workspaceIcon($tabIcons[$destination]??'info') ?></span>
   <h3><?= esc($title) ?></h3>
   <p><?= esc($description) ?></p><a href="dashboard.php?view=<?= esc($destination) ?>"><?= esc($tabs[$destination]) ?> <?= workspaceIcon('next') ?></a>
  </article>
 <?php endforeach; ?>
 </div>
 <p><a href="dashboard.php?view=guide">ดูขั้นตอนในคู่มือการใช้งาน <?= workspaceIcon('next') ?></a></p>
</section>
