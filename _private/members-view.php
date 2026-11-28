<?php
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/members.php';
$search=$_GET['member_search']??'';
$search=is_string($search)?substr(strtolower(trim($search)),0,40):'';
if (!preg_match('/^[a-z0-9._-]*$/D',$search)) $search='';
$status=$_GET['member_status']??'';
if (!in_array($status,['active','suspended'],true)) $status='';
$providerLabels=['line'=>'LINE','google'=>'Google','facebook'=>'Facebook'];
$provider=$_GET['member_provider']??'';
if (!is_string($provider) || !isset($providerLabels[$provider])) $provider='';
$cursor=$_GET['member_cursor']??'';
if (!is_string($cursor) || !preg_match('/^(older|newer)\|(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\|([a-f0-9]{32})$/D',$cursor,$cursorParts)
    || !checkdate((int)substr($cursorParts[2],5,2),(int)substr($cursorParts[2],8,2),(int)substr($cursorParts[2],0,4))) {
    $cursor='';
}
$newer=$cursor!=='' && $cursorParts[1]==='newer';
$order=$newer?'ASC':'DESC';
$where=['1=1'];
$params=[];
if ($search!=='') {
    $where[]="(a.id LIKE ? ESCAPE '!' OR p.username LIKE ? ESCAPE '!')";
    $pattern=str_replace('_','!_',$search).'%';
    array_push($params,$pattern,$pattern);
}
if ($status!=='') $where[]=$status==='suspended'?'a.suspended_at IS NOT NULL':'a.suspended_at IS NULL';
if ($provider!=='') {
    $where[]='EXISTS (SELECT 1 FROM anemia_participant_identity i WHERE i.account_id=a.id AND i.provider=?)';
    $params[]=$provider;
}
if ($cursor!=='') {
    $operator=$newer?'>':'<';
    $where[]="(a.connected_at $operator ? OR (a.connected_at=? AND a.id $operator ?))";
    array_push($params,$cursorParts[2],$cursorParts[2],$cursorParts[3]);
}
anemiaEnsureMemberListIndex($db);
$query=$db->prepare("SELECT a.id,a.connected_at,a.suspended_at,p.username,
    (SELECT GROUP_CONCAT(i.provider ORDER BY i.provider) FROM anemia_participant_identity i WHERE i.account_id=a.id) AS providers
    FROM anemia_participant_account a LEFT JOIN anemia_participant_password p ON p.account_id=a.id
    WHERE ".implode(' AND ',$where)." ORDER BY a.connected_at $order,a.id $order LIMIT 31");
$query->execute($params);
$rows=$query->fetchAll(PDO::FETCH_ASSOC);
$hasMore=count($rows)>30;
if ($hasMore) array_pop($rows);
if ($newer) $rows=array_reverse($rows);
$hasNewer=$newer?$hasMore:$cursor!=='';
$hasOlder=$newer || $hasMore;
$cached=$_SESSION['member_summary']??null;
if (!$cached || $cached['until']<time()) {
    $cached=['until'=>time()+30,'counts'=>$db->query('SELECT COUNT(*) AS total,COUNT(suspended_at) AS suspended FROM anemia_participant_account')->fetch(PDO::FETCH_ASSOC)];
    $_SESSION['member_summary']=$cached;
}
$summary=$cached['counts'];
$base=['view'=>'members','member_search'=>$search,'member_status'=>$status,'member_provider'=>$provider,'member_cursor'=>$cursor];
$memberUrl=static fn(array $add=[]): string=>'dashboard.php?'.http_build_query(array_replace($base,$add));
$memberCursor=static fn(string $direction,array $row): string=>$direction.'|'.$row['connected_at'].'|'.$row['id'];
$member=filter_input(INPUT_GET,'member')??'';
$task=filter_input(INPUT_GET,'member_task')??'';
$detail=null;
$audit=[];
if (is_string($member)&&preg_match('/^[a-f0-9]{32}$/D',$member)) {
    $query=$db->prepare('SELECT a.id,a.connected_at,a.suspended_at,p.username,m.completed_at,
        EXISTS(SELECT 1 FROM anemia_demo_member_network d WHERE d.account_id=a.id) AS has_demo_network,
        (SELECT GROUP_CONCAT(i.provider ORDER BY i.provider) FROM anemia_participant_identity i WHERE i.account_id=a.id) AS providers,
        (SELECT i.connected_at FROM anemia_participant_identity i WHERE i.account_id=a.id AND i.provider=\'facebook\') AS facebook_connected_at,
        (SELECT COUNT(*) FROM anemia_team_bonus b WHERE b.inviter=m.id) AS invited_completions
        FROM anemia_participant_account a LEFT JOIN anemia_participant_password p ON p.account_id=a.id
        LEFT JOIN anemia_team_member m ON m.account_id=a.id WHERE a.id=?');
    $query->execute([$member]);
    $detail=$query->fetch(PDO::FETCH_ASSOC)?:null;
    if ($detail) {
        $query=$db->prepare('SELECT actor,action_name,reason,created_at FROM anemia_member_audit WHERE account_id=? ORDER BY id DESC LIMIT 20');
        $query->execute([$member]);
        $audit=$query->fetchAll(PDO::FETCH_ASSOC);
    }
}
$methods=static function(array $row) use ($providerLabels): string {
    $items=$row['username']?['PIN']:[];
    foreach (explode(',',$row['providers']??'') as $key) if (isset($providerLabels[$key])) $items[]=$providerLabels[$key];
    return implode(' · ',$items)?:'—';
};
?>
<section class="card admin-report member-directory" aria-labelledby="member-directory-title">
 <h2 id="member-directory-title"><?= workspaceIcon('users') ?> จัดการบัญชีผู้เข้าร่วม</h2>
 <?php if ($memberNotice!==''): ?><p class="status" role="status"><?= esc($memberNotice) ?></p><?php endif; ?>
 <nav class="member-summary" aria-label="กรองรายชื่อตามสถานะ สมาชิกทั้งหมดในระบบ">
  <?php foreach ([
    ['users','สมาชิกทั้งหมด',(int)$summary['total'],''],
    ['ready','ใช้งานได้',(int)$summary['total']-(int)$summary['suspended'],'active'],
    ['lock','ระงับ',(int)$summary['suspended'],'suspended'],
  ] as [$icon,$label,$count,$cardStatus]): ?>
   <a class="member-summary-item member-summary-<?= $icon ?>" href="<?= esc($memberUrl(['member_status'=>$cardStatus,'member_cursor'=>''])) ?>" <?= $status===$cardStatus?'aria-current="true"':'' ?>>
    <span class="member-summary-icon"><?= workspaceIcon($icon) ?></span>
    <span class="member-summary-label"><?= $label ?></span><strong><?= number_format($count) ?></strong>
   </a>
  <?php endforeach; ?>
 </nav>
 <form method="get" class="network-form member-filters">
  <input type="hidden" name="view" value="members">
  <label>ค้นหาสมาชิก<input type="search" name="member_search" value="<?= esc($search) ?>" placeholder="ชื่อบัญชีหรือรหัสสมาชิก" maxlength="40"></label>
  <label>สถานะ<select name="member_status"><option value="">ทั้งหมด</option><option value="active" <?= $status==='active'?'selected':'' ?>>ใช้งานได้</option><option value="suspended" <?= $status==='suspended'?'selected':'' ?>>ระงับ</option></select></label>
  <label>ช่องทางที่เชื่อม<select name="member_provider"><option value="">ทุกช่องทาง</option>
   <?php foreach ($providerLabels as $key=>$label): ?><option value="<?= esc($key) ?>" <?= $provider===$key?'selected':'' ?>><?= esc($label) ?></option><?php endforeach; ?>
  </select></label>
  <button class="action primary" type="submit"><?= workspaceIcon('filter') ?>แสดงข้อมูล</button>
 </form>
 <?php if ($rows): ?>
 <div class="tablewrap member-tablewrap" tabindex="0" role="region" aria-label="รายชื่อสมาชิก"><table class="admin-table member-table">
  <thead><tr><th scope="col">สมาชิก</th><th scope="col">วิธีเข้าสู่ระบบ</th><th scope="col">วันที่สมัคร</th><th scope="col">สถานะ</th><th scope="col">จัดการ</th></tr></thead>
  <tbody><?php foreach ($rows as $row): ?>
   <tr><th scope="row"><?= esc(anemiaMemberListName($row['username'],$row['id'])) ?></th>
    <td class="member-login-method"><?= esc($methods($row)) ?></td><td><?= esc(workspaceThaiShortDate($row['connected_at'])) ?></td>
    <td><span class="member-state <?= $row['suspended_at']?'is-suspended':'is-active' ?>"><?= workspaceIcon($row['suspended_at']?'lock':'ready') ?><?= $row['suspended_at']?'ระงับ':'ใช้งานได้' ?></span></td>
    <td>
     <?php $detailUrl=['member'=>$row['id']]; $menuId='member-menu-'.$row['id']; ?>
     <a class="action member-menu-trigger" href="<?= esc($memberUrl($detailUrl)) ?>" data-member-menu="<?= $menuId ?>" aria-label="จัดการสมาชิก <?= esc(anemiaMemberListName($row['username'],$row['id'])) ?>">⋯</a>
     <div id="<?= $menuId ?>" class="member-menu" popover hidden>
      <a href="<?= esc($memberUrl($detailUrl)) ?>"><?= workspaceIcon('info') ?>ดูรายละเอียด</a>
      <?php if ($row['username']): ?><a href="<?= esc($memberUrl($detailUrl+['member_task'=>'rename'])) ?>"><?= workspaceIcon('person') ?>แก้ไขชื่อบัญชี</a><?php endif; ?>
      <a href="<?= esc($memberUrl($detailUrl+['member_task'=>'status'])) ?>"><?= workspaceIcon($row['suspended_at']?'ready':'lock') ?><?= $row['suspended_at']?'เปิดบัญชีคืน':'ระงับบัญชี' ?></a>
     </div>
    </td>
   </tr>
  <?php endforeach; ?></tbody>
 </table></div>
 <?php else: ?><p class="member-empty"><?= workspaceIcon('users') ?> ไม่พบสมาชิกตามตัวกรอง</p><?php endif; ?>
 <?php if ($cursor!==''||$hasOlder): ?><nav class="actions member-pagination" aria-label="หน้ารายชื่อสมาชิก">
  <?php if ($cursor!==''): ?><a class="action" href="<?= esc($memberUrl(['member_cursor'=>''])) ?>">ล่าสุด</a><?php endif; ?>
  <?php if ($rows && $hasNewer): ?><a class="action" href="<?= esc($memberUrl(['member_cursor'=>$memberCursor('newer',$rows[0])])) ?>"><?= workspaceIcon('back') ?>ใหม่กว่า</a><?php endif; ?>
  <?php if ($rows && $hasOlder): ?><a class="action" href="<?= esc($memberUrl(['member_cursor'=>$memberCursor('older',end($rows))])) ?>">เก่ากว่า <?= workspaceIcon('next') ?></a><?php endif; ?>
 </nav><?php endif; ?>
</section>
<?php if ($detail): ?>
<dialog id="member-details" class="workspace-dialog" open aria-labelledby="member-details-title">
 <header class="workspace-dialog-heading"><h2 id="member-details-title"><?= $task==='rename'?'แก้ไขชื่อบัญชี':($task==='status'?($detail['suspended_at']?'เปิดบัญชีคืน':'ระงับบัญชี'):'รายละเอียดสมาชิก') ?></h2><a class="action" href="<?= esc($memberUrl()) ?>" aria-label="ปิด"><?= workspaceIcon('close') ?></a></header>
 <?php if ($memberNotice!==''): ?><p class="status" role="status"><?= esc($memberNotice) ?></p><?php endif; ?>
 <p><strong><?= esc($detail['username']??'บัญชีจากช่องทางภายนอก') ?></strong><br><small><?= esc($detail['id']) ?></small></p>
 <?php if ($detail['facebook_connected_at']): ?><p>เชื่อม Facebook เมื่อ <?= esc(workspaceThaiShortDate($detail['facebook_connected_at'])) ?></p><?php endif; ?>
 <p><?= esc($methods($detail)) ?> · <?= $detail['suspended_at']?'ระงับ':'ใช้งานได้' ?></p>
 <p>แต้มที่บันทึก <?= ($detail['providers']?5:0)+($detail['completed_at']?10:0)+5*(int)$detail['invited_completions'] ?></p>
 <?php if ($task==='status'||($task==='rename'&&$detail['username'])): ?>
 <?php $action=$task==='rename'?'rename':($detail['suspended_at']?'restore':'suspend'); ?>
 <form method="post" class="participant-signup">
  <input type="hidden" name="csrf" value="<?= esc($token) ?>">
  <input type="hidden" name="member" value="<?= esc($detail['id']) ?>">
  <?php if ($action==='rename'): ?><label>ชื่อบัญชีใหม่<input name="new_username" value="<?= esc($detail['username']) ?>" maxlength="40" required></label><?php endif; ?>
  <label>เหตุผล <textarea name="reason" rows="2" maxlength="<?= $action==='rename'?400:500 ?>" required></textarea></label>
  <button class="action primary" type="submit" name="member_action" value="<?= $action ?>"><?= workspaceIcon($action==='suspend'?'lock':'save') ?> <?= ['suspend'=>'ระงับบัญชี','restore'=>'เปิดบัญชีคืน','rename'=>'บันทึกชื่อบัญชี'][$action] ?></button>
 </form>
 <?php else: ?><div class="actions">
  <?php if ($detail['username']): ?><a class="action" href="<?= esc($memberUrl(['member'=>$detail['id'],'member_task'=>'rename'])) ?>"><?= workspaceIcon('person') ?>แก้ไขชื่อบัญชี</a><?php endif; ?>
  <a class="action" href="<?= esc($memberUrl(['member'=>$detail['id'],'member_task'=>'status'])) ?>"><?= workspaceIcon($detail['suspended_at']?'ready':'lock') ?><?= $detail['suspended_at']?'เปิดบัญชีคืน':'ระงับบัญชี' ?></a>
 </div><?php endif; ?>
 <?php if ($task===''): ?>
 <form method="post" class="actions">
  <input type="hidden" name="csrf" value="<?= esc($token) ?>"><input type="hidden" name="member" value="<?= esc($detail['id']) ?>">
  <?php if ($detail['has_demo_network']): ?><button class="action" name="member_action" value="demo-clear"><?= workspaceIcon('close') ?>ล้างลูกทีมจำลอง</button>
  <?php else: ?><button class="action" name="member_action" value="demo-create"><?= workspaceIcon('users') ?>สร้างลูกทีมจำลอง</button><?php endif; ?>
 </form>
 <?php endif; ?>
 <?php if ($audit): ?><details><summary>ประวัติเปลี่ยนสถานะ</summary><ul>
  <?php foreach ($audit as $event): ?><li><?= esc(workspaceThaiShortDate($event['created_at'])) ?> · <?= esc($event['actor']) ?> · <?= ['suspend'=>'ระงับ','restore'=>'เปิดคืน','rename'=>'แก้ชื่อ'][$event['action_name']]??'เปลี่ยนข้อมูล' ?><br><small><?= esc($event['reason']) ?></small></li><?php endforeach; ?>
 </ul></details><?php endif; ?>
</dialog>
<script>
const memberDialog=document.getElementById('member-details');
if(typeof memberDialog.showModal==='function'){
 memberDialog.removeAttribute('open');
 memberDialog.showModal();
 memberDialog.addEventListener('close',()=>location.assign(memberDialog.querySelector('header a').href));
}
</script>
<?php endif; ?>
<script>
for(const trigger of document.querySelectorAll('[data-member-menu]')){
 const menu=document.getElementById(trigger.dataset.memberMenu);
 if(typeof menu.showPopover!=='function')continue;
 menu.hidden=false;
 trigger.setAttribute('aria-expanded','false');
 trigger.addEventListener('click',event=>{
  if(event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
  event.preventDefault();
  if(menu.matches(':popover-open')){menu.hidePopover();return;}
  menu.showPopover();
  const rect=trigger.getBoundingClientRect();
  menu.style.left=Math.max(8,Math.min(rect.right-menu.offsetWidth,innerWidth-menu.offsetWidth-8))+'px';
  menu.style.top=(rect.bottom+menu.offsetHeight+8<innerHeight?rect.bottom+4:Math.max(8,rect.top-menu.offsetHeight-4))+'px';
 });
 menu.addEventListener('toggle',()=>trigger.setAttribute('aria-expanded',String(menu.matches(':popover-open'))));
}
</script>
