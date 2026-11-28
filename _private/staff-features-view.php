<?php
if (!defined('ANEMIA_STAFF_BOOT') || empty($identity) || $identity['role']==='school_teacher') {
    http_response_code(404);
    exit;
}
$featureGroups = [
    ['คิวและการติดตาม', 'calendar', [
        ['contact-queue', 'จัดการคิวและติดตามคำขอ', 'เปิดคิวตามสิทธิ์ กรองตามสถานะ แล้วเปิดรายการเพื่อบันทึกการติดต่อ นัดหมาย หรือผลติดตาม'],
        ['scan-request', 'สแกน QR รับบริการ', 'ใช้กล้องสแกน QR จากใบรับคำขอ หรือกรอกเลขคำขอเมื่อสแกนไม่ได้'],
        ['network', 'จัดการเครือข่ายหน่วยบริการ', 'เปิดทะเบียนเครือข่ายเพื่อดูหน่วยบริการในพื้นที่และจัดการตามสิทธิ์'],
    ]],
    ['รายงานและกิจกรรม', 'chart', [
        ['overview', 'ภาพรวมเขตสุขภาพ', 'เปิดแดชบอร์ดเพื่อดูตัวเลขภาพรวมและเลือกพื้นที่ตามสิทธิ์'],
        ['reports', 'รายงานคัดกรองและส่งต่อ', 'เลือกเขตและเดือน แล้วเปิดเอกสารพิมพ์หรือดาวน์โหลด PDF'],
        ['evaluation', 'ผลประเมินการใช้งาน', 'เลือกช่วงเวลาและกลุ่มผู้ตอบเพื่อดูคะแนนและข้อเสนอแนะ'],
        ['schools', 'จัดการกิจกรรมโรงเรียน', 'เลือกพื้นที่และจังหวัดเพื่อดูทะเบียนโรงเรียนและดำเนินการตามสิทธิ์'],
        ['programme-report', 'รายงานการเรียนรู้ 12 สัปดาห์', 'เปิดรายงานการเรียนรู้เพื่อดูความคืบหน้าและผลการเข้าร่วม'],
    ]],
    ['จัดการระบบและตรวจสอบ', 'spark', [
        ['members', 'จัดการบัญชีสมาชิก', 'ค้นหาด้วยชื่อบัญชีหรือรหัสสมาชิก แล้วเลือกบัญชีที่ต้องการจัดการ'],
        ['access', 'จัดการสิทธิ์เจ้าหน้าที่', 'เปิดหน้าจัดการสิทธิ์เพื่อกำหนดบทบาทและขอบเขตพื้นที่ของบัญชี'],
        ['assessment', 'ตรวจสอบรุ่นแบบประเมิน', 'เปิดหน้าแบบประเมินเพื่อดูรุ่นและสถานะของแต่ละรุ่น'],
        ['voice-coach', 'จัดการข้อความและเสียง Voice Coach', 'เลือกหมวดและหัวข้อเพื่อแก้ข้อความหรืออัปโหลดไฟล์เสียงแยกกัน'],
        ['services', 'ตั้งค่า OTP และบริการเข้าสู่ระบบ', 'เปิดหน้าการเชื่อมต่อบริการแล้วเลือกบริการที่จะตั้งค่า'],
        ['sample-settings', 'จัดการข้อมูลทดสอบ', 'เปิดหน้าจัดการข้อมูลเพื่อดูตัวเลือกสำหรับข้อมูลจำลอง'],
        ['usage-audit', 'ตรวจประวัติการใช้งาน', 'กรองตามเหตุการณ์และพื้นที่ ระบบแสดงเฉพาะบันทึกตามสิทธิ์ของบัญชี'],
        ['audit', 'ตรวจประวัติการเปลี่ยนสิทธิ์', 'เปิดประวัติสิทธิ์เพื่อดูรายการเปลี่ยนแปลงบัญชีเจ้าหน้าที่'],
    ]],
];
?>
<section class="card admin-report guide-catalog" aria-label="ฟีเจอร์เด่นสำหรับเจ้าหน้าที่">
 <p class="notice">รายการแสดงตามสิทธิ์ของบัญชีนี้</p>
 <?php foreach ($featureGroups as [$heading,$symbol,$entries]):
    $entries=array_filter($entries, static fn(array $entry): bool => isset($tabs[$entry[0]]));
    if (!$entries) continue;
 ?>
  <section class="guide-section">
   <h2><?= workspaceIcon($symbol) ?> <?= esc($heading) ?></h2>
   <div class="feature-grid">
   <?php foreach ($entries as [$destination,$title,$description]): ?>
    <article class="feature-card">
     <span class="feature-card-icon"><?= workspaceIcon($tabIcons[$destination]??'info') ?></span>
     <h3><?= esc($title) ?></h3>
     <p><?= esc($description) ?></p><a href="dashboard.php?view=<?= esc($destination) ?>">เปิด<?= esc($tabs[$destination]) ?> <?= workspaceIcon('next') ?></a>
    </article>
   <?php endforeach; ?>
   </div>
  </section>
 <?php endforeach; ?>
 <p><a href="dashboard.php?view=guide">ดูขั้นตอนในคู่มือการใช้งาน <?= workspaceIcon('next') ?></a></p>
</section>
