<?php
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }
$services=anemiaServiceDefinitions();
$serviceTab=is_string($_GET['service']??null) && isset($services[$_GET['service']]) ? $_GET['service'] : 'otp';
$serviceNotice=$_SESSION['service_notice']??'';
unset($_SESSION['service_notice']);
[$serviceName,$serviceIcon,$serviceFields]=$services[$serviceTab];
$settings=anemiaServiceSettings($serviceTab);
$ready=anemiaServiceReady($serviceTab,$settings);
$mode=$settings['mode']??($ready && $serviceTab!=='thaid'?'live':'off');
?>
<section class="card admin-report service-panel">
 <nav class="service-tabs" aria-label="บริการภายนอก">
 <?php foreach ($services as $id=>[$label,$icon]): ?>
  <a class="service-tab service-tab--<?= esc($id) ?>" href="dashboard.php?view=services&amp;service=<?= esc($id) ?>" <?= $serviceTab===$id?'aria-current="page"':'' ?>><span class="service-tab-icon" aria-hidden="true">
    <?php if ($id==='otp'): ?><?= workspaceIcon('message') ?>
    <?php elseif ($id==='facebook'): ?><span class="participant-provider-mark" data-provider="facebook">f</span>
    <?php else: ?><img src="service-brand-<?= esc($id) ?>.png" alt="">
    <?php endif; ?>
   </span>
   <span><?= esc($label) ?></span></a>
 <?php endforeach; ?>
 </nav>
 <div class="service-heading"><h2><?= esc($serviceName) ?></h2>
 <span class="service-status"><?= $ready?'ตั้งค่าครบ':'รอตั้งค่า' ?></span></div>
 <?php if ($serviceNotice!==''): ?><p class="notice" role="status"><?= esc($serviceNotice) ?></p><?php endif; ?>
 <?php if (isset($settings['updated_at'])): ?><p class="service-meta">อัปเดตล่าสุด <?= esc(workspaceThaiShortDate($settings['updated_at'])) ?> โดย <?= esc($settings['updated_by']) ?></p><?php endif; ?>
 <?php if (anemiaServiceKey()===null): ?><p class="notice">ตั้งค่า ANEMIA_SERVICE_SETTINGS_KEY บนโฮสต์เพื่อเปิดการบันทึก</p><?php endif; ?>
 <?php if ($serviceTab==='thaid'): ?>
  <p>บันทึกสถานะการขอเชื่อมต่อและเลขอ้างอิงได้ การเข้าสู่ระบบผ่าน ThaID จะพร้อมเมื่อมีข้อมูลและสิทธิ์เชื่อมต่อจากกรมการปกครอง</p>
  <p><a href="https://digitalid.bora.dopa.go.th/pr">เอกสารและช่องทางลงทะเบียน ThaID</a></p>
 <?php elseif ($serviceTab!=='otp'): ?>
  <div class="network-form"><label>Callback URL <input id="service-callback" readonly value="<?= esc(anemiaServiceCallback()) ?>"></label></div>
  <button type="button" class="action" onclick="const field=document.getElementById('service-callback');field.select();navigator.clipboard?.writeText(field.value).catch(()=>{});"><?= workspaceIcon('layout') ?>คัดลอก Callback URL</button>
  <p>คัดลอก URL นี้ไปตั้งค่าที่ผู้ให้บริการ หน้าเข้าสู่ระบบจะแสดงช่องทางนี้เมื่อเปิดใช้จริง</p>
  <?php if ($serviceTab==='facebook'):
      $facebookMembers=(int)$db->query("SELECT COUNT(*) FROM anemia_participant_identity WHERE provider='facebook'")->fetchColumn();
  ?>
   <p>ใช้ App ID, App Secret และเวอร์ชัน Graph API จากแอปใน Meta for Developers แล้วเพิ่ม Callback URL ใน Valid OAuth Redirect URIs</p>
   <div class="actions">
    <a class="action" href="dashboard.php?view=members&amp;member_provider=facebook"><?= workspaceIcon('users') ?>สมาชิกที่เชื่อม Facebook (<?= number_format($facebookMembers) ?>)</a>
   </div>
  <?php endif; ?>
  <?php if ($mode==='live' && $ready): ?><p><a href="participant-account.php?return=portal" target="_blank" rel="noopener">เปิดหน้าเข้าสู่ระบบเพื่อทดสอบ</a></p><?php endif; ?>
 <?php endif; ?>
 <form class="network-form service-form" method="post">
  <input type="hidden" name="csrf" value="<?= esc($token) ?>">
  <input type="hidden" name="service" value="<?= esc($serviceTab) ?>">
  <?php foreach ($serviceFields as $field=>[$label,$secret]): ?>
   <?php $configured=anemiaServiceValue($settings,$field)!==''; ?>
   <label><?= esc($label) ?><?= $secret && $configured?' · ตั้งค่าแล้ว':'' ?>
    <input name="<?= esc($field) ?>" type="<?= $secret?'password':'text' ?>" maxlength="512" autocomplete="off"
      value="<?= $secret?'':esc(anemiaServiceValue($settings,$field)) ?>" placeholder="<?= $secret&&$configured?'เว้นว่างเพื่อใช้ค่าเดิม':'' ?>">
   </label>
  <?php endforeach; ?>
  <label>สถานะบริการ
   <select name="mode">
    <?php foreach (($serviceTab==='thaid'?['off'=>'ปิด','pending'=>'รอการเชื่อมต่อ']:['off'=>'ปิด','test'=>'ทดสอบ','live'=>'เปิดใช้จริง']) as $value=>$label): ?>
     <option value="<?= esc($value) ?>" <?= $mode===$value?'selected':'' ?>><?= esc($label) ?></option>
    <?php endforeach; ?>
   </select>
  </label>
  <div class="network-actions"><button class="action primary" type="submit" name="service_action" value="save" <?= anemiaServiceKey()===null?'disabled':'' ?>><?= workspaceIcon('save') ?>บันทึกการตั้งค่า</button></div>
 </form>
 <?php if ($serviceTab==='facebook' && $ready): ?>
  <form class="network-actions" method="post">
   <input type="hidden" name="csrf" value="<?= esc($token) ?>">
   <input type="hidden" name="service" value="facebook">
   <button class="action" type="submit" name="service_action" value="facebook-check"><?= workspaceIcon('check') ?>ตรวจค่าที่บันทึกกับ Meta</button>
  </form>
 <?php endif; ?>
 <?php if ($serviceTab==='otp' && $ready && in_array($mode,['test','live'],true)): ?>
  <h3>ทดสอบส่งและตรวจรหัส SMS</h3>
  <?php if (isset($_SESSION['service_otp_verified_at'])): ?><p>ยืนยันรหัสทดสอบล่าสุด <?= esc(workspaceThaiShortDate(date('c',$_SESSION['service_otp_verified_at']))) ?> เวลา <?= esc(date('H:i',$_SESSION['service_otp_verified_at'])) ?> น.</p><?php endif; ?>
  <form class="network-form service-test-form" method="post">
   <input type="hidden" name="csrf" value="<?= esc($token) ?>">
   <input type="hidden" name="service" value="otp">
   <?php if (!empty($_SESSION['service_otp_test']['until']) && $_SESSION['service_otp_test']['until']>=time()): ?>
    <label>รหัสจาก SMS<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{4,10}" required></label>
    <button class="action" type="submit" name="service_action" value="otp-check">ตรวจรหัสทดสอบ</button>
   <?php else: ?>
    <label>เบอร์มือถือทดสอบ<input name="phone" type="tel" inputmode="numeric" placeholder="08XXXXXXXX" pattern="0[689][0-9]{8}" required></label>
    <button class="action" type="submit" name="service_action" value="otp-send">ส่ง OTP ทดสอบ</button>
   <?php endif; ?>
  </form>
 <?php endif; ?>
</section>
