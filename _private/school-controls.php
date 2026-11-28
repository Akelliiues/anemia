<?php if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; } ?>
<?php if ($canAppoint): ?>
<details class="school-disclosure"><?php schoolDisclosureHeading('แต่งตั้งผู้ประสานงานโรงเรียน','person'); ?>
 <p>บัญชีปัจจุบัน <?= schoolEscape($school['coordinator']??'ยังไม่ได้แต่งตั้ง') ?></p>
 <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>" class="network-form school-fields">
  <?php schoolFields($csrf,'coordinator',(int)$school['id']); ?>
  <label>ชื่อบัญชีครู<input name="teacher" maxlength="40" required></label>
  <button class="action primary">บันทึกผู้ประสานงาน</button>
 </form>
</details>
<?php endif; ?>
<?php if ($admin): ?>
<details class="school-disclosure"><?php schoolDisclosureHeading('แก้ไขทะเบียนโรงเรียน','edit'); ?>
 <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>" class="network-form school-fields">
  <?php schoolFields($csrf,'school-edit',(int)$school['id']); ?>
  <label>ชื่อโรงเรียน<input name="name" maxlength="160" value="<?= schoolEscape($school['name']) ?>" required></label>
  <button class="action primary">บันทึกชื่อโรงเรียน</button>
 </form>
</details>
<?php endif; ?>
<?php if ($teacher): ?>
<div class="teacher-tool-groups">
<?php if ($manage):
 $hasYears=(bool)array_filter(array_column($cohorts,'academic_year'));
?>
 <nav class="teacher-tool-nav" aria-label="หมวดงานโรงเรียน" hidden>
  <button type="button" aria-controls="teacher-class-panel" aria-pressed="false"><?= workspaceIcon('book') ?> ห้องเรียน</button>
  <button type="button" aria-controls="teacher-people-panel" aria-pressed="false"><?= workspaceIcon('users') ?> ครู</button>
  <?php if ($hasYears): ?><button type="button" aria-controls="teacher-enrollment-panel" aria-pressed="false"><?= workspaceIcon('ready') ?> การรับนักเรียน</button><?php endif; ?>
 </nav>
<?php endif; ?>
<section class="teacher-tool-group" id="teacher-people-panel" aria-labelledby="teacher-people-tools">
 <h3 id="teacher-people-tools"><?= workspaceIcon('users') ?> ครูและผู้รับผิดชอบ</h3>
<?php endif; ?>
<?php if ($teacher && $school['enabled']): ?>
<details class="school-disclosure"><?php schoolDisclosureHeading('ข้อมูลครูของฉัน','person'); ?>
 <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>" class="network-form school-fields">
  <?php schoolFields($csrf,'teacher-profile',(int)$school['id']); ?>
  <label>ชื่อและนามสกุล<input name="full_name" maxlength="160" value="<?= schoolEscape($profile['full_name']??'') ?>" required></label>
  <label>โทรศัพท์หรืออีเมลประสานงาน (ไม่บังคับ)<input name="contact" maxlength="160" value="<?= schoolEscape($profile['contact']??'') ?>"></label>
  <button class="action primary">บันทึกข้อมูลครู</button>
 </form>
</details>
<?php endif; ?>
<?php if ($manage && ($school['enabled'] || $admin)): ?>
<details class="school-disclosure"><?php schoolDisclosureHeading('ครูผู้รับผิดชอบ','users'); ?>
 <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>" class="network-form school-fields">
  <?php schoolFields($csrf,'grant',(int)$school['id']); ?>
  <label>ชื่อบัญชีครู<input name="teacher" maxlength="40" required></label>
  <button class="action primary">เพิ่มครูในโรงเรียน</button>
 </form>
 <?php if (!$assignments): ?><p>ยังไม่มีครูในทะเบียนโรงเรียน</p><?php else: ?>
 <div class="tablewrap"><table class="admin-table"><thead><tr><th scope="col">ครู</th><th scope="col">บทบาท</th><th scope="col">ห้องที่ดูแล</th><th scope="col">สถานะ</th><th scope="col">จัดการ</th></tr></thead><tbody>
 <?php foreach ($assignments as $staffTeacher): $username=$staffTeacher['username']; ?>
  <tr><th scope="row"><?= schoolEscape($staffTeacher['full_name']) ?><br><small><?= schoolEscape($staffTeacher['contact']??'') ?></small></th>
  <td><?= $school['coordinator']===$username?'ผู้ประสานงาน':'ครูประจำห้อง' ?></td>
  <td><?php
   $responsibilities=[];
   foreach ($cohorts as $room) {
       foreach ($roomTeachers[$room['id']]??[] as $duty=>$assignment) {
           if ($assignment['username']===$username) $responsibilities[]=$room['classroom'].' ('.$room['academic_year'].' · '.($duty==='primary'?'ครูหลัก':'ครูร่วม').')';
       }
   }
   echo schoolEscape(implode(', ',$responsibilities)?:'—');
  ?></td>
  <td><?= $staffTeacher['enabled']?'ใช้งานได้':'ระงับ' ?></td>
  <td><?php if ($school['coordinator']!==$username): ?>
   <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>"><?php schoolFields($csrf,'revoke',(int)$school['id']); ?>
    <input type="hidden" name="teacher" value="<?= schoolEscape($username) ?>"><button class="action">ถอนสิทธิ์</button>
   </form>
  <?php endif; ?></td></tr>
 <?php endforeach; ?>
 </tbody></table></div>
 <?php endif; ?>
</details>
<?php if ($teacher): ?></section><?php endif; ?>
<?php if ($school['enabled']): ?>
<?php if ($teacher): ?>
<section class="teacher-tool-group" id="teacher-class-panel" aria-labelledby="teacher-class-tools">
 <h3 id="teacher-class-tools"><?= workspaceIcon('book') ?> ห้องเรียน</h3>
<?php endif; ?>
<details class="school-disclosure"><?php schoolDisclosureHeading('สร้างห้องเรียนตามปีการศึกษา','calendar'); ?>
 <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>" class="network-form school-fields">
  <?php schoolFields($csrf,'rooms-create',(int)$school['id']); ?>
  <label>ปีการศึกษา พ.ศ.<input type="number" name="year" min="2500" max="2642" value="<?= (int)date('Y')+543 ?>" required></label>
  <label>ชื่อกิจกรรม<input name="title" value="กิจกรรมเรียนรู้ 12 สัปดาห์" maxlength="100" required></label>
  <?php foreach ([4,5,6] as $grade): ?>
   <label>จำนวนห้อง ม.<?= $grade ?><input type="number" name="grade_<?= $grade ?>" min="0" max="50" value="0" required></label>
  <?php endforeach; ?>
  <label>วันเริ่มรับ<input type="date" name="opens" value="<?= date('Y-m-d') ?>" required></label>
  <label>วันสิ้นสุดรับ<input type="date" name="closes" value="<?= date('Y-m-d',strtotime('+12 weeks')) ?>" required></label>
  <button class="action primary">สร้างห้อง</button>
 </form>
</details>
<?php $years=array_values(array_unique(array_filter(array_column($cohorts,'academic_year')))); ?>
<?php if ($years): ?>
<details class="school-disclosure"><?php schoolDisclosureHeading('คัดลอกโครงสร้างห้องจากปีก่อน','copy'); ?>
 <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>" class="network-form school-fields">
  <?php schoolFields($csrf,'rooms-copy',(int)$school['id']); ?>
  <label>คัดลอกจากปี<select name="from_year"><?php foreach ($years as $year): ?><option><?= (int)$year ?></option><?php endforeach; ?></select></label>
  <label>ปีการศึกษาใหม่ พ.ศ.<input type="number" name="year" min="2500" max="2642" value="<?= (int)$years[0]+1 ?>" required></label>
  <label>ชื่อกิจกรรม<input name="title" value="กิจกรรมเรียนรู้ 12 สัปดาห์" maxlength="100" required></label>
  <label>วันเริ่มรับ<input type="date" name="opens" required></label>
  <label>วันสิ้นสุดรับ<input type="date" name="closes" required></label>
  <button class="action primary">คัดลอกห้อง</button>
 </form>
</details>
<details class="school-disclosure"><?php schoolDisclosureHeading('มอบหมายครูประจำห้อง','users'); ?>
 <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>" class="network-form school-fields">
  <?php schoolFields($csrf,'room-assign',(int)$school['id']); ?>
  <label>บทบาท<select name="duty"><option value="primary">ครูหลัก</option><option value="assistant">ครูร่วม</option></select></label>
  <label>ครู<select name="teacher"><option value="">เว้นตำแหน่งนี้ว่าง</option>
   <?php foreach ($assignments as $candidate): if (!$candidate['enabled']) continue; ?>
    <option value="<?= schoolEscape($candidate['username']) ?>"><?= schoolEscape($candidate['full_name']) ?></option>
   <?php endforeach; ?>
  </select></label>
  <fieldset><legend>เลือกห้องเรียน</legend>
   <?php foreach ($cohorts as $room): if (!$room['academic_year']) continue; ?>
    <div><label><input type="checkbox" name="rooms[]" value="<?= (int)$room['id'] ?>"> <?= schoolEscape($room['classroom'].' · '.$room['academic_year']) ?>
     <?= empty($roomTeachers[$room['id']]['primary']['enabled'])?' · ยังไม่มีครูหลักที่ใช้งานได้':'' ?>
    </label></div>
   <?php endforeach; ?>
  </fieldset>
  <button class="action primary">บันทึกการมอบหมาย</button>
 </form>
</details>
<?php endif; ?>
<?php if ($teacher): ?></section><?php endif; ?>
<?php if ($years): ?>
<?php if ($teacher): ?>
<section class="teacher-tool-group" id="teacher-enrollment-panel" aria-labelledby="teacher-enrollment-tools">
 <h3 id="teacher-enrollment-tools"><?= workspaceIcon('ready') ?> การรับนักเรียน</h3>
<?php endif; ?>
<details class="school-disclosure"><?php schoolDisclosureHeading('เปิดหรือปิดรับนักเรียนหลายห้อง','power'); ?>
 <form method="post" action="<?= schoolEscape($schoolPostUrl) ?>" class="network-form school-fields">
  <?php schoolFields($csrf,'rooms-accept',(int)$school['id']); ?>
  <fieldset><legend>เลือกห้องเรียน</legend>
  <?php foreach ($cohorts as $room): if (!$room['academic_year']) continue; ?>
    <div><label><input type="checkbox" name="rooms[]" value="<?= (int)$room['id'] ?>"> <?= schoolEscape($room['classroom'].' · '.$room['academic_year']) ?></label></div>
  <?php endforeach; ?>
  </fieldset>
  <div class="actions"><button class="action primary" name="accepting" value="1">เปิดรับตามช่วงวันที่</button><button class="action" name="accepting" value="0">ปิดรับ</button></div>
 </form>
</details>
<?php if ($teacher): ?></section><?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php elseif ($teacher): ?></section>
<?php endif; ?>
<?php if ($teacher): ?></div><?php endif; ?>
