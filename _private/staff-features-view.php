<?php
if (!defined('ANEMIA_STAFF_BOOT') || empty($identity) || $identity['role']==='school_teacher') {
    http_response_code(404);
    exit;
}
$featureHighlights = [
    ['contact-queue', 'คำขอเดียว ติดตามต่อได้ตั้งแต่รับเรื่องถึงหลังตรวจ', 'เปิดคิวตามสิทธิ์ ประสานนัด สแกน QR เพื่อเปิดคำขอเมื่อผู้รับบริการมาถึง แล้วบันทึกผลและติดตามต่อจากรายการเดิม'],
    ['overview', 'เห็นภาพทั้งเขต ก่อนลงไปดูรายละเอียด', 'เลือกเขตสุขภาพบนแผนที่เพื่อดูยอดคัดกรองและส่งต่อทั้งปี แล้วเปิดรายงานรายเดือนเพื่อดูรายละเอียดตามพื้นที่ที่รับผิดชอบ'],
    ['schools', 'ดูแลหลายโรงเรียนได้จากหลังบ้านเดียว', 'ค้นหาโรงเรียนตามจังหวัดและติดตามการเข้าร่วม ครูจัดการห้องเรียนในพื้นที่ของโรงเรียน ส่วนเจ้าหน้าที่ดูภาพรวมตามสิทธิ์'],
    ['reports', 'ข้อมูลพร้อมส่งต่อในรูปแบบรายงาน', 'เลือกเขตและเดือนก่อนพิมพ์หรือดาวน์โหลด PDF ตารางภาษาไทยจัดตามตัวกรองที่เลือก'],
];
?>
<section class="card admin-report guide-catalog" aria-label="ฟีเจอร์เด่นสำหรับเจ้าหน้าที่">
 <div class="feature-pitch">
  <h2>ติดตามงานจากคำขอรายคนถึงภาพรวมเขตสุขภาพ</h2>
  <p>Anemia Portal รวมข้อมูลที่ใช้ประสานงาน ติดตามผล และออกรายงานไว้ในหลังบ้านตามสิทธิ์ของแต่ละบัญชี</p>
 </div>
 <div class="feature-grid">
 <?php foreach ($featureHighlights as [$destination,$title,$description]):
    if (!isset($tabs[$destination])) continue;
 ?>
  <article class="feature-card">
   <span class="feature-card-icon"><?= workspaceIcon($tabIcons[$destination]??'info') ?></span>
   <h3><?= esc($title) ?></h3>
   <p><?= esc($description) ?></p><a href="dashboard.php?view=<?= esc($destination) ?>"><?= esc($tabs[$destination]) ?> <?= workspaceIcon('next') ?></a>
  </article>
 <?php endforeach; ?>
 </div>
 <p><a href="dashboard.php?view=guide">ดูขั้นตอนในคู่มือการใช้งาน <?= workspaceIcon('next') ?></a></p>
</section>
