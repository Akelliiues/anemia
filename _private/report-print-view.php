<?php
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }
$provinceDocument=$view==='reports';
$reportTitle=$provinceDocument?'รายงานผลการคัดกรองและการส่งต่อรายจังหวัด':'รายงานผลการประเมินการใช้งาน';
$period=$provinceDocument?$reportMonth:$evaluationMonth;
$reportScope=$scopeName;
if (!$provinceDocument && !$region && $identity['role']!=='provincial_report') $reportScope='ทุกเขตและผู้ไม่ระบุจังหวัด';
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= esc($reportTitle) ?> · Anemia Portal</title>
<link rel="stylesheet" href="report-print.css?v=2">
</head><body<?= $provinceDocument?' class="report-landscape"':'' ?>>
<div class="report-print-toolbar"><button type="button" onclick="document.fonts.ready.then(()=>window.print())">พิมพ์ / บันทึก PDF</button></div>
<main class="report-paper">
 <header class="report-paper-header">
  <h1><?= esc($reportTitle) ?></h1>
  <p>พื้นที่ <?= esc($reportScope) ?> · ช่วงเวลา <?= esc($period?workspaceThaiMonth($period):'ทุกเดือน') ?></p>
  <?php if (!$provinceDocument): ?><p>กลุ่มผู้ตอบ <?= esc(EVALUATION_AUDIENCES[$evaluationAudience]??'ทุกกลุ่ม') ?> · รุ่นแบบประเมิน <?= esc($selectedVersion) ?></p><?php endif; ?>
  <p>แหล่งข้อมูล <?= $sampleMode?'ข้อมูลจำลอง':'ข้อมูลจากระบบ' ?> · จัดทำ <?= esc(workspaceThaiShortDate(date('c')).' เวลา '.date('H:i').' น.') ?></p>
 </header>
 <?php if ($provinceDocument): ?>
  <table class="report-province-table">
   <thead>
    <tr><th rowspan="2" scope="col">จังหวัด</th><th colspan="6" scope="colgroup">การคัดกรอง (ครั้ง)</th><th colspan="4" scope="colgroup">การส่งต่อ (คำขอ)</th></tr>
    <tr><?php foreach (ANEMIA_PROVINCE_METRICS as $label): ?><th scope="col"><?= esc($label) ?></th><?php endforeach; ?></tr>
   </thead>
   <tbody><?php foreach ($provinceReport as $row): ?>
    <tr><th scope="row"><?= esc($row['name']) ?></th><?php foreach (ANEMIA_PROVINCE_METRICS as $key=>$_): ?><td><?= number_format((int)$row[$key]) ?></td><?php endforeach; ?></tr>
   <?php endforeach; ?>
   <?php if (!$provinceReport): ?><tr><td colspan="11">ไม่มีจังหวัดในขอบเขตที่เลือก</td></tr><?php endif; ?></tbody>
  </table>
  <p class="report-note">ผลคัดกรองนับตามเดือนที่เริ่มและจังหวัดที่ผู้ใช้ระบุ ผลส่งต่อนับตามเดือนที่ส่งคำขอและจังหวัดของหน่วยบริการในทะเบียน โดยแสดงผลติดตามล่าสุดของคำขอในเดือนนั้น ยอดทั้งสองส่วนมาจากคนละชุดข้อมูล</p>
 <?php else:
  $total=(int)$evaluation['overall']['n'];
  $average=$total?number_format((float)$evaluation['overall']['average'],2):'—';
  $satisfied=$total?number_format((int)$evaluation['overall']['satisfied']*100/$total,1).'%':'—';
 ?>
  <h2>ภาพรวม</h2>
  <table><thead><tr><th>แบบประเมินที่ส่งแล้ว</th><th>เฉลี่ย / 5</th><th>S.D.</th><th>คะแนนระดับมากขึ้นไป</th></tr></thead>
   <tbody><tr><td><?= number_format($total) ?></td><td><?= esc($average) ?></td><td><?= isset($evaluation['overall']['sd'])?number_format((float)$evaluation['overall']['sd'],2):'—' ?></td><td><?= esc($satisfied) ?></td></tr></tbody>
  </table>
  <h2>คะแนนแยกตามด้าน</h2>
  <table><thead><tr><th>ด้าน</th><th>แบบประเมินที่ให้คะแนน</th><th>จำนวนข้อที่ให้คะแนน</th><th>เฉลี่ย / 5</th><th>S.D.</th></tr></thead>
   <tbody><?php foreach (EVALUATION_DOMAINS as $key=>$label): $item=$evaluation['domains'][$key]??null; ?>
    <tr><th scope="row"><?= esc($label) ?></th><td><?= (int)($item['respondents']??0) ?></td><td><?= (int)($item['n']??0) ?></td><td><?= $item?number_format((float)$item['average'],2):'—' ?></td><td><?= isset($item['sd'])?number_format((float)$item['sd'],2):'—' ?></td></tr>
   <?php endforeach; ?></tbody>
  </table>
  <p class="report-note">คะแนนรายด้านเว้นข้อที่ผู้ตอบเลือกว่ายังไม่ได้ใช้ และเว้นค่า S.D. เมื่อมีคะแนนน้อยกว่า 2 แบบ</p>
  <h2>เรื่องที่อยากให้ปรับ</h2>
  <table><thead><tr><th>เรื่อง (เลือกได้มากกว่าหนึ่งเรื่อง)</th><th>จำนวนแบบประเมินที่เลือก</th></tr></thead>
   <tbody><?php foreach(EVALUATION_TOPICS as $key=>$label): ?><tr><th scope="row"><?= esc($label) ?></th><td><?= (int)($evaluation['topics'][$key]??0) ?></td></tr><?php endforeach; ?></tbody>
  </table>
  <h2>ข้อเสนอแนะเพิ่มเติม</h2>
  <?php $comments=$sampleMode?['อยากให้หน้าผลประเมินสรุปขั้นตอนถัดไปให้เห็นทันที','อยากให้ค้นหาบทความตามเรื่องที่สนใจได้ง่ายขึ้น']:($evaluation['comments']??[]); ?>
  <?php if ($comments): ?><ul><?php foreach ($comments as $comment): ?><li><?= esc($comment) ?></li><?php endforeach; ?></ul>
  <?php else: ?><p>ยังไม่มีข้อเสนอแนะเพิ่มเติมในตัวกรองนี้</p><?php endif; ?>
 <?php endif; ?>
<footer class="report-paper-footer">
  <img src="portal-heart-clear.png" alt="" width="48" height="48">
  <p>ศูนย์อนามัยที่ ๑๐ จังหวัดอุบลราชธานี · Anemia Portal</p>
 </footer>
 </main></body></html>
