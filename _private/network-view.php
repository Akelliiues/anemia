<?php
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }
[$networkScope,$networkParams]=anemiaManageNetworkScope($isAdmin,$grant);
$ownFacility=!$isAdmin && ($grant['scope_kind']??'')==='facility';
$bookingTab=$ownFacility || ($_GET['network_tab']??'')==='booking';
if ($ownFacility) {
    $networkScope="(f.code=? OR (f.kind='primary' AND ".anemiaEffectiveHub('f')."=?))";
    $networkParams=[$grant['scope_code'],$grant['scope_code']];
}
if ($bookingTab) anemiaEnsureBooking($db);
$districtFilter=filter_input(INPUT_GET,'district',FILTER_UNSAFE_RAW)??'';
if (!preg_match('/^\d{4}$/D',$districtFilter)) $districtFilter='';
$query=$db->prepare("SELECT DISTINCT district FROM anemia_network_facility f WHERE $networkScope ORDER BY district");
$query->execute($networkParams);
$districts=$query->fetchAll(PDO::FETCH_COLUMN);
$provinceFilter=$_GET['booking_province']??'';
$provinceCodes=array_unique(array_map(static fn(string $district): string=>substr($district,0,2),$districts));
$bookingProvinces=[];
if ($bookingTab && !$ownFacility && $provinceCodes) {
    $provinceQuery=$db->prepare('SELECT code,name FROM anemia_province WHERE code IN ('.implode(',',array_fill(0,count($provinceCodes),'?')).') ORDER BY name');
    $provinceQuery->execute(array_values($provinceCodes));
    $bookingProvinces=$provinceQuery->fetchAll(PDO::FETCH_KEY_PAIR);
}
if (!is_string($provinceFilter) || !isset($bookingProvinces[$provinceFilter])) $provinceFilter='';
if ($provinceFilter!=='') $districts=array_values(array_filter($districts,static fn(string $district): bool=>str_starts_with($district,$provinceFilter)));
if (!in_array($districtFilter,$districts,true)) $districtFilter=$bookingTab && count($districts)>1?'':($districts[0]??'');
$bookingFields=$bookingTab?',b.appointment,b.callback,b.paused_until,b.updated_at,'.anemiaBookingOwnerSql().' AS queue_owner':'';
$bookingJoin=$bookingTab?' LEFT JOIN anemia_network_booking b ON b.facility_code=f.code':'';
$query=$db->prepare("SELECT f.code,f.name,f.kind,f.hub,h.name AS hub_name,".anemiaEffectiveHub('f')." AS effective_hub$bookingFields,
 (SELECT COUNT(*) FROM anemia_contact_queue q WHERE q.facility_key=f.facility_key AND q.state<>'closed') AS open_requests
 FROM anemia_network_facility f LEFT JOIN anemia_network_facility h ON h.code=".anemiaEffectiveHub('f')."
 $bookingJoin WHERE $networkScope AND f.district=? ORDER BY f.name,f.code");
$query->execute([...$networkParams,$districtFilter]);
$facilities=$query->fetchAll(PDO::FETCH_ASSOC);
$hospitals=array_column(array_filter($facilities,static fn($facility)=>$facility['kind']==='hospital'),'name','code');
?>
<section class="card admin-report stack network-panel">
 <h2><?= workspaceIcon('hospital') ?> เครือข่ายโรงพยาบาลและ รพ.สต.</h2>
 <?php if (!$ownFacility): ?><nav class="actions" aria-label="จัดการเครือข่าย">
  <a class="action<?= !$bookingTab?' primary':'' ?>" href="dashboard.php?view=network&amp;district=<?= esc($districtFilter) ?>" <?= !$bookingTab?'aria-current="page"':'' ?>><?= workspaceIcon('map') ?> แม่ข่าย</a>
  <a class="action<?= $bookingTab?' primary':'' ?>" href="dashboard.php?view=network&amp;network_tab=booking&amp;district=<?= esc($districtFilter) ?>" <?= $bookingTab?'aria-current="page"':'' ?>><?= workspaceIcon('calendar') ?> การรับจองออนไลน์</a>
 </nav><?php endif; ?>
 <?php if ($networkNotice!==''): ?><p role="status" class="status"><?= esc($networkNotice) ?></p><?php endif; ?>
 <?php if ($isAdmin && !$bookingTab): ?>
 <div class="network-tools">
 <details class="network-tool">
  <summary><?= workspaceIcon('hospital') ?> <span>เพิ่มหน่วยบริการ</span></summary>
  <div class="network-tool-body">
  <p>ใส่หนึ่งรายการต่อบรรทัด เรียงรหัสหน่วยบริการ รหัสอำเภอ ลำดับจากไฟล์อ้างอิง และค่าโรงพยาบาลประจำอำเภอ (1 หรือ 0)</p>
  <form method="post" class="network-form">
   <input type="hidden" name="csrf" value="<?= esc($token) ?>"><input type="hidden" name="network_action" value="register">
   <label>รายการหน่วยบริการ<textarea name="registry" rows="4" required></textarea></label>
   <div class="network-actions">
  <a class="action" href="dashboard.php?view=network&amp;directory=1"><?= workspaceIcon('download') ?>ดาวน์โหลดลำดับหน่วยบริการ</a>
    <button class="action primary" type="submit"><?= workspaceIcon('save') ?>เพิ่มเข้าทะเบียน</button>
   </div>
  </form>
  </div>
 </details>
 <details class="network-tool">
  <summary><?= workspaceIcon('users') ?> <span>กำหนดสิทธิ์เจ้าหน้าที่</span></summary>
  <form method="post" class="network-form network-tool-body">
   <input type="hidden" name="csrf" value="<?= esc($token) ?>"><input type="hidden" name="network_action" value="grant">
   <label>ชื่อบัญชี<input name="username" maxlength="40" required></label>
   <label>ขอบเขต<select name="scope_kind"><option value="province">จัดการในจังหวัด</option><option value="district">จัดการในอำเภอ</option><option value="facility">คิวนัดของหน่วยบริการ</option><option value="remove">ถอนสิทธิ์เครือข่าย</option></select></label>
   <label>รหัสจังหวัด อำเภอ หรือหน่วยบริการ<input name="scope_code" maxlength="12" inputmode="numeric"></label>
   <div class="network-actions"><button type="submit" class="action primary"><?= workspaceIcon('save') ?>บันทึกสิทธิ์</button></div>
  </form>
 </details>
 </div>
 <?php endif; ?>
 <?php if (!$districts): ?>
  <p class="network-empty"><?= workspaceIcon('map') ?> ยังไม่มีหน่วยบริการในขอบเขตที่รับผิดชอบ</p>
 <?php else: ?>
 <form method="get" class="admin-filters">
  <input type="hidden" name="view" value="network">
  <?php if ($bookingTab): ?><input type="hidden" name="network_tab" value="booking"><?php endif; ?>
  <?php if ($bookingProvinces): ?><label>จังหวัด<select name="booking_province" onchange="this.form.elements.district.value='';this.form.submit()">
   <option value="">ทุกจังหวัดตามสิทธิ์</option><?php foreach ($bookingProvinces as $code=>$name): ?><option value="<?= esc((string)$code) ?>" <?= (string)$code===$provinceFilter?'selected':'' ?>><?= esc($name) ?></option><?php endforeach; ?>
  </select></label><?php endif; ?>
  <label>อำเภอ<select name="district" <?= !$bookingTab?'required':'' ?>><?php if ($bookingTab): ?><option value="">เลือกอำเภอ</option><?php endif; ?><?php foreach ($districts as $district): ?><option <?= $district===$districtFilter?'selected':'' ?>><?= esc($district) ?></option><?php endforeach; ?></select></label>
  <button type="submit" class="action"><?= workspaceIcon('map') ?>แสดงหน่วยบริการ</button>
 </form>
 <?php if ($bookingTab): ?>
 <?php require __DIR__.'/network-booking-view.php'; ?>
 <?php else: ?>
 <div class="tablewrap"><table class="admin-table">
  <thead><tr><th>รพ.สต.</th><th>แม่ข่ายปัจจุบัน</th><th>คำขอที่ยังเปิด</th><th>เปลี่ยนแม่ข่าย</th></tr></thead>
  <tbody>
  <?php foreach ($facilities as $facility): if ($facility['kind']!=='primary') continue; ?>
   <tr><th scope="row"><?= esc($facility['name']) ?><br><small><?= esc($facility['code']) ?></small></th>
   <td><?= esc($hospitals[$facility['effective_hub']]??'รอตรวจสอบ') ?></td><td><?= (int)$facility['open_requests'] ?></td>
   <td><form method="post" class="contact-queue-state">
    <input type="hidden" name="csrf" value="<?= esc($token) ?>"><input type="hidden" name="network_action" value="link"><input type="hidden" name="facility" value="<?= esc($facility['code']) ?>">
    <select name="hub" aria-label="แม่ข่ายของ <?= esc($facility['name']) ?>">
     <option value="">ใช้ค่าเริ่มต้นของอำเภอ</option>
     <?php foreach ($hospitals as $code=>$name): ?><option value="<?= esc((string)$code) ?>" <?= (string)$code===$facility['hub']?'selected':'' ?>><?= esc($name) ?></option><?php endforeach; ?>
    </select>
    <button class="action" type="submit"><?= workspaceIcon('save') ?>บันทึก</button>
   </form></td></tr>
  <?php endforeach; ?>
  </tbody>
 </table></div>
 <?php endif; ?>
 <?php endif; ?>
</section>
