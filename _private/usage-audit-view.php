<?php
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }
if (!$canUsageAudit) { http_response_code(403); exit; }
[$scope,$parameters]=usageAuditScope($identity);
$filters=['view'=>'usage-audit'];
$kinds=['teacher-preview'=>'ดูในมุมมองครู','school-change'=>'จัดการโรงเรียน','session'=>'เข้าและออกจากระบบ',
    'enrollment'=>'เข้าร่วมกิจกรรมโรงเรียน','data-change'=>'เพิ่ม แก้ไข หรือลบข้อมูล','access-change'=>'เปลี่ยนบัญชีหรือสิทธิ์'];
$scope.=" AND l.event_kind NOT IN ('workspace','submission')";
$kind=$_GET['event_kind']??'';
if (is_string($kind) && isset($kinds[$kind])) {
    $scope.=' AND l.event_kind=?';
    $parameters[]=$filters['event_kind']=$kind;
}
$auditRegion=filter_input(INPUT_GET,'audit_region',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>13]]);
if ($auditRegion) {
    $scope.=' AND l.region=?';
    $parameters[]=$filters['audit_region']=$auditRegion;
}
$province=$_GET['audit_province']??'';
if (is_string($province) && preg_match('/^[0-9]{2}$/D',$province)) {
    $scope.=' AND l.province_code=?';
    $parameters[]=$filters['audit_province']=$province;
}
[$provinceScope,$provinceParams]=anemiaProvinceScope($identity,(int)$auditRegion);
$areas=$db->prepare('SELECT code,name FROM anemia_province'.($provinceScope?' WHERE '.$provinceScope:'').' ORDER BY name');
$areas->execute($provinceParams);
$before=filter_input(INPUT_GET,'before',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
$after=filter_input(INPUT_GET,'after',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
[$rows,$hasNewer,$hasOlder]=usageAuditPage($db,$scope,$parameters,$before,$after);
$auditUrl=static fn(array $cursor=[]): string => 'dashboard.php?'.http_build_query($filters+$cursor);
$events=$tabs+[
    'teacher-workspace'=>'พื้นที่ครู','login'=>'เข้าสู่ระบบ','logout'=>'ออกจากระบบ','school-create'=>'เพิ่มโรงเรียน',
    'directory-add'=>'เพิ่มโรงเรียนจากทะเบียน','school-edit'=>'แก้ไขทะเบียนโรงเรียน','school-withdraw'=>'ถอนการเข้าร่วม',
    'school-archive'=>'ยุติการเข้าร่วม','school-reopen'=>'เปิดใช้งานโรงเรียน','coordinator'=>'แต่งตั้งผู้ประสานงาน',
    'grant'=>'เพิ่มครู','revoke'=>'ถอนสิทธิ์ครู','teacher-profile'=>'แก้ไขข้อมูลครู','rooms-create'=>'สร้างห้อง',
    'rooms-copy'=>'คัดลอกห้อง','room-assign'=>'มอบหมายครู','room-open'=>'เปิดรับนักเรียน','room-close'=>'ปิดรับนักเรียน',
    'room-target'=>'ตั้งเป้าหมาย','cohort-edit'=>'แก้ไขรุ่น','cohort-toggle'=>'เปลี่ยนสถานะรับสมัคร','school-join'=>'ยืนยันเข้าร่วมห้องเรียน',
    'access-create'=>'สร้างบัญชีเจ้าหน้าที่','access-update'=>'แก้สิทธิ์เจ้าหน้าที่','member-rename'=>'แก้ชื่อบัญชีสมาชิก',
    'member-suspend'=>'ระงับบัญชีสมาชิก','member-restore'=>'คืนสถานะบัญชีสมาชิก',
    'member-demo-create'=>'สร้างลูกทีมจำลอง','member-demo-clear'=>'ล้างลูกทีมจำลอง',
    'network-register'=>'เพิ่มทะเบียนหน่วยบริการ','network-grant'=>'เปลี่ยนสิทธิ์เครือข่าย','network-link'=>'เปลี่ยนแม่ข่าย',
    'queue-update'=>'เปลี่ยนสถานะคำขอ','queue-delete'=>'ลบคำขอ',
    'demo-queue-update'=>'เปลี่ยนสถานะคำขอทดสอบ','demo-queue-delete'=>'ลบคำขอทดสอบ',
    'service-otp'=>'บันทึกตั้งค่า OTP','service-google'=>'บันทึกตั้งค่า Google','service-line'=>'บันทึกตั้งค่า LINE',
    'service-facebook'=>'บันทึกตั้งค่า Facebook','service-thaid'=>'บันทึกตั้งค่า ThaID',
];
?>
<section class="card admin-report usage-audit" aria-labelledby="usage-audit-heading">
 <h2 id="usage-audit-heading"><?= workspaceIcon('history') ?> บันทึกตามสิทธิ์ของบัญชี</h2>
 <form method="get" class="network-form usage-audit-filters">
  <input type="hidden" name="view" value="usage-audit">
  <label>ประเภทเหตุการณ์<select name="event_kind"><option value="">ทุกประเภท</option><?php foreach ($kinds as $key=>$label): ?>
   <option value="<?= esc($key) ?>" <?= $key===($filters['event_kind']??'')?'selected':'' ?>><?= esc($label) ?></option>
  <?php endforeach; ?></select></label>
  <?php if ($isAdmin): ?><label>เขตสุขภาพ<select name="audit_region"><option value="">ทุกเขต</option><?php for ($zone=1;$zone<=13;$zone++): ?>
   <option value="<?= $zone ?>" <?= $zone===$auditRegion?'selected':'' ?>><?= esc(zoneLabel($zone)) ?></option>
  <?php endfor; ?></select></label><?php endif; ?>
  <?php if ($identity['role']!=='provincial_report'): ?><label>จังหวัด<select name="audit_province"><option value="">ทุกจังหวัดตามสิทธิ์</option><?php foreach ($areas->fetchAll(PDO::FETCH_ASSOC) as $area): ?>
   <option value="<?= esc($area['code']) ?>" <?= $area['code']===$province?'selected':'' ?>><?= esc($area['name']) ?></option>
  <?php endforeach; ?></select></label><?php endif; ?>
  <button class="action"><?= workspaceIcon('filter') ?> แสดงประวัติ</button>
 </form>
 <?php if (!$rows): ?><p>ไม่พบเหตุการณ์ตามตัวกรอง</p><?php else: ?>
 <div class="tablewrap usage-audit-scroll" tabindex="0" role="region" aria-label="ตารางประวัติการใช้งาน"><table class="admin-table usage-audit-table">
  <colgroup><col class="usage-audit-time"><col><col class="usage-audit-actor"><col class="usage-audit-area"><col></colgroup>
  <thead><tr><th scope="col">เวลา</th><th scope="col">เหตุการณ์</th><th scope="col">ผู้ดำเนินการ</th><th scope="col">พื้นที่</th><th scope="col">โรงเรียน / ครู</th></tr></thead>
  <tbody><?php foreach ($rows as $row):
      $localTime=(new DateTimeImmutable($row['created_at'],new DateTimeZone('UTC')))
          ->setTimezone(new DateTimeZone('Asia/Bangkok'))->format('Y-m-d H:i:s');
      $cells=[
          workspaceThaiShortDate($localTime).' '.substr($localTime,11,8),
          ($kinds[$row['event_kind']]??$row['event_kind']).' · '.($events[$row['event_name']]??$row['event_name']),
          ($row['actor_role']==='participant'?'ผู้เข้าร่วม':$row['actor']).' · '.
              (ANEMIA_REPORT_ROLES[$row['actor_role']]??($row['actor_role']==='participant'?'สมาชิก':'ผู้ดูแลระบบ')),
          implode(' · ',array_filter([$row['region']?zoneLabel((int)$row['region']):null,$row['province']]))?:'—',
          implode(' · ',array_filter([$row['school'],$row['target_teacher'],$row['cohort_id']?'รหัสห้อง '.(int)$row['cohort_id']:null]))?:'—',
      ];
  ?><tr>
   <?php foreach ($cells as $cell): ?><td><button type="button" class="usage-audit-cell" tabindex="-1" title="<?= esc($cell) ?>"><?= esc($cell) ?></button></td><?php endforeach; ?>
  </tr><?php endforeach; ?></tbody>
 </table></div>
 <div class="usage-audit-tooltip" popover="auto" aria-hidden="true"></div>
 <script defer src="usage-audit.js?v=1"></script>
 <?php endif; ?>
 <nav class="actions usage-audit-pagination" aria-label="แบ่งหน้าประวัติ">
  <span><?= count($rows) ?> รายการในหน้านี้ · หน้าละ 50 รายการ</span>
  <?php if ($before || $after): ?><a class="action" href="<?= esc($auditUrl()) ?>">ล่าสุด</a><?php endif; ?>
  <?php if ($rows && $hasNewer): ?><a class="action" href="<?= esc($auditUrl(['after'=>$rows[0]['id']])) ?>"><?= workspaceIcon('back') ?> ใหม่กว่า</a><?php endif; ?>
  <?php if ($rows && $hasOlder): ?><a class="action" href="<?= esc($auditUrl(['before'=>end($rows)['id']])) ?>">เก่ากว่า <?= workspaceIcon('next') ?></a><?php endif; ?>
 </nav>
</section>
