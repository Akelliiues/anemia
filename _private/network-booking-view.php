<?php if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; } ?>
<?php if ($facilities): ?>
<div class="tablewrap"><table class="admin-table">
 <thead><tr><th scope="col">หน่วยบริการ</th><th scope="col">แม่ข่าย</th><th scope="col">การรับคำขอ</th><th scope="col">อัปเดตล่าสุด</th><th scope="col">จัดการ</th></tr></thead>
 <tbody><?php foreach ($facilities as $facility):
  $flags=anemiaBookingFlags($facility,date('Y-m-d'));
  $channels=array_filter(['ขอนัดตรวจ'=>$flags['appointment'],'ติดต่อกลับ'=>$flags['callback']]);
  $canEdit=!$ownFacility || $facility['code']===$grant['scope_code'];
 ?><tr>
  <th scope="row"><?= esc($facility['name']) ?><br><small><?= $facility['kind']==='primary'?'รพ.สต.':'โรงพยาบาล' ?> · <?= esc($facility['code']) ?></small></th>
  <td><?= $facility['kind']==='primary'?esc($facility['hub_name']??'รอกำหนดแม่ข่าย'):'—' ?></td>
  <td><?= $flags['paused']?'พักรับชั่วคราว':($channels?esc(implode(' · ',array_keys($channels))):'ติดต่อหน่วยบริการโดยตรง') ?></td>
  <td><?= $facility['updated_at']?esc(workspaceThaiShortDate($facility['updated_at'])):'—' ?></td>
  <td><?php if ($canEdit): ?>
   <details><summary class="action"><?= workspaceIcon('edit') ?> ตั้งค่า</summary>
    <form method="post" class="network-form">
     <input type="hidden" name="csrf" value="<?= esc($token) ?>"><input type="hidden" name="network_action" value="booking">
     <input type="hidden" name="facility" value="<?= esc($facility['code']) ?>">
     <label><span><input type="checkbox" name="appointment" value="1" <?= $facility['appointment']?'checked':'' ?>> รับขอนัดตรวจ</span></label>
     <label><span><input type="checkbox" name="callback" value="1" <?= $facility['callback']?'checked':'' ?>> รับคำขอติดต่อกลับ</span></label>
     <label>พักรับถึงวันที่เปิดอีกครั้ง<input type="date" name="resume" min="<?= date('Y-m-d',strtotime('+1 day')) ?>" value="<?= $flags['paused']?esc($facility['paused_until']):'' ?>"></label>
     <small>คำขอเดิมที่ยังเปิด <?= number_format((int)$facility['open_requests']) ?> รายการ</small>
     <button class="action primary"><?= workspaceIcon('save') ?> บันทึก</button>
    </form>
   </details>
  <?php else: ?>ดูสถานะ<?php endif; ?></td>
 </tr><?php endforeach; ?></tbody>
</table></div>
<?php else: ?><p class="notice">เลือกอำเภอเพื่อดูสถานะรับคำขอออนไลน์</p><?php endif; ?>
