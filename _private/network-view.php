<?php
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }
[$networkScope,$networkParams]=anemiaManageNetworkScope($isAdmin,$grant);
$districtFilter=filter_input(INPUT_GET,'district',FILTER_UNSAFE_RAW)??'';
if (!preg_match('/^\d{4}$/D',$districtFilter)) $districtFilter='';
$query=$db->prepare("SELECT DISTINCT district FROM anemia_network_facility f WHERE $networkScope ORDER BY district");
$query->execute($networkParams);
$districts=$query->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($districtFilter,$districts,true)) $districtFilter=$districts[0]??'';
$query=$db->prepare("SELECT f.code,f.name,f.kind,f.hub,".anemiaEffectiveHub('f')." AS effective_hub,
 (SELECT COUNT(*) FROM anemia_contact_queue q WHERE q.facility_key=f.facility_key AND q.state<>'closed') AS open_requests
 FROM anemia_network_facility f WHERE $networkScope AND f.district=? ORDER BY f.name,f.code");
$query->execute([...$networkParams,$districtFilter]);
$facilities=$query->fetchAll(PDO::FETCH_ASSOC);
$hospitals=array_column(array_filter($facilities,static fn($facility)=>$facility['kind']==='hospital'),'name','code');
?>
<section class="card admin-report stack">
 <h2><?= workspaceIcon('hospital') ?> เครือข่ายโรงพยาบาลและ รพ.สต.</h2>
 <?php if ($networkNotice!==''): ?><p role="status" class="status"><?= esc($networkNotice) ?></p><?php endif; ?>
 <?php if ($isAdmin): ?>
 <details>
  <summary>เพิ่มหน่วยบริการจากทะเบียนที่ตรวจสอบแล้ว</summary>
  <p>ใส่หนึ่งรายการต่อบรรทัด เรียงรหัสหน่วยบริการ รหัสอำเภอ ลำดับจากไฟล์อ้างอิง และค่าโรงพยาบาลประจำอำเภอ (1 หรือ 0)</p>
  <a class="action" href="dashboard.php?view=network&amp;directory=1"><?= workspaceIcon('download') ?>ดาวน์โหลดลำดับหน่วยบริการ</a>
  <form method="post" class="stack">
   <input type="hidden" name="csrf" value="<?= esc($token) ?>"><input type="hidden" name="network_action" value="register">
   <label>รายการหน่วยบริการ<textarea name="registry" rows="4" required></textarea></label>
   <button class="action primary" type="submit"><?= workspaceIcon('save') ?>เพิ่มเข้าทะเบียน</button>
  </form>
 </details>
 <details>
  <summary>กำหนดสิทธิ์ให้บัญชีเจ้าหน้าที่</summary>
  <form method="post" class="admin-filters">
   <input type="hidden" name="csrf" value="<?= esc($token) ?>"><input type="hidden" name="network_action" value="grant">
   <label>ชื่อบัญชี<input name="username" maxlength="40" required></label>
   <label>ขอบเขต<select name="scope_kind"><option value="province">จัดการในจังหวัด</option><option value="district">จัดการในอำเภอ</option><option value="facility">คิวนัดของหน่วยบริการ</option><option value="remove">ถอนสิทธิ์เครือข่าย</option></select></label>
   <label>รหัสจังหวัด อำเภอ หรือหน่วยบริการ<input name="scope_code" maxlength="12" inputmode="numeric"></label>
   <button type="submit" class="action primary"><?= workspaceIcon('save') ?>บันทึกสิทธิ์</button>
  </form>
 </details>
 <?php endif; ?>
 <?php if (!$districts): ?>
  <p>ยังไม่มีหน่วยบริการในขอบเขตที่รับผิดชอบ</p>
 <?php else: ?>
 <form method="get" class="admin-filters">
  <input type="hidden" name="view" value="network">
  <label>อำเภอ<select name="district"><?php foreach ($districts as $district): ?><option <?= $district===$districtFilter?'selected':'' ?>><?= esc($district) ?></option><?php endforeach; ?></select></label>
  <button type="submit" class="action"><?= workspaceIcon('map') ?>แสดงเครือข่าย</button>
 </form>
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
</section>
