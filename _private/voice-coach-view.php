<?php
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }
$query=$db->prepare('SELECT edition,display_text,audio IS NOT NULL AS has_audio,updated_by,updated_at FROM anemia_voice_coach WHERE slot=?');
$query->execute([$slot]);
$editions=array_column($query->fetchAll(PDO::FETCH_ASSOC),null,'edition');
$draft=$editions['draft']??null;
$published=$editions['published']??null;
?>
<section class="card voice-coach-admin">
 <nav class="actions voice-coach-tabs" aria-label="จุดใช้งาน Voice Coach">
  <?php foreach (anemiaVoiceSlots() as $key=>$label): ?>
   <a class="action<?= $slot===$key?' primary':'' ?>" href="dashboard.php?view=voice-coach&amp;slot=<?= esc($key) ?>" <?= $slot===$key?'aria-current="page"':'' ?>><?= esc($label) ?></a>
  <?php endforeach; ?>
 </nav>
 <?php if ($voiceNotice!==''): ?><p class="notice" role="status"><?= esc($voiceNotice) ?></p><?php endif; ?>
 <div class="voice-coach-editor">
  <section>
   <h2><?= workspaceIcon('edit') ?> ฉบับร่าง</h2>
   <form method="post" enctype="multipart/form-data" class="network-form">
    <input type="hidden" name="csrf" value="<?= esc($token) ?>">
    <label>ข้อความที่แสดง<textarea name="display_text" rows="5" maxlength="1500" required><?= esc($draft['display_text']??'') ?></textarea></label>
    <label>ไฟล์เสียง MP3 (ไม่เกิน 2 MB)<input type="file" name="audio" accept="audio/mpeg,.mp3"></label>
    <?php if (!empty($draft['has_audio'])): ?>
     <audio controls preload="none" src="dashboard.php?view=voice-coach&amp;slot=<?= esc($slot) ?>&amp;draft_audio=1" aria-label="ฟังไฟล์ฉบับร่าง"></audio>
     <label><span><input type="checkbox" name="remove_audio" value="1"> นำเสียงออกจากฉบับร่าง</span></label>
    <?php endif; ?>
    <button class="action" name="voice_action" value="save"><?= workspaceIcon('save') ?> บันทึกฉบับร่าง</button>
   </form>
   <?php if ($draft): ?>
    <form method="post" class="actions">
     <input type="hidden" name="csrf" value="<?= esc($token) ?>">
     <button class="action primary" name="voice_action" value="publish"><?= workspaceIcon('send') ?> เผยแพร่ฉบับร่างที่บันทึกแล้ว</button>
    </form>
   <?php endif; ?>
  </section>
  <section>
   <h2><?= workspaceIcon('ready') ?> ฉบับเผยแพร่</h2>
   <?php if ($published): ?>
    <p class="voice-coach-copy"><?= esc($published['display_text']) ?></p>
    <?php if ($published['has_audio']): ?><audio controls preload="none" src="voice-coach.php?audio=<?= esc($slot) ?>" aria-label="ฟังเสียงที่เผยแพร่"></audio>
    <?php else: ?><p class="notice">แสดงข้อความอย่างเดียว</p><?php endif; ?>
    <p class="notice">เผยแพร่ <?= esc(workspaceThaiShortDate($published['updated_at'])) ?> โดย <?= esc($published['updated_by']) ?></p>
   <?php else: ?><p class="notice">ยังไม่มีฉบับเผยแพร่</p><?php endif; ?>
  </section>
 </div>
</section>
