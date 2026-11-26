<?php
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/members.php';
$search=filter_input(INPUT_GET,'member_search')??'';
$search=is_string($search)?substr(strtolower(trim($search)),0,40):'';
if (!preg_match('/^[a-z0-9._-]*$/D',$search)) $search='';
$status=filter_input(INPUT_GET,'member_status')??'';
if (!in_array($status,['active','suspended'],true)) $status='';
$page=filter_input(INPUT_GET,'member_page',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]])?:1;
$offset=($page-1)*30;
$where=['1=1'];
$params=[];
if ($search!=='') {
    $where[]="(a.id LIKE ? OR p.username LIKE ? ESCAPE '!')";
    $params[]=$search.'%';
    $params[]=str_replace('_','!_',$search).'%';
}
if ($status!=='') $where[]=$status==='suspended'?'a.suspended_at IS NOT NULL':'a.suspended_at IS NULL';
$query=$db->prepare("SELECT a.id,a.connected_at,a.suspended_at,p.username,
    (SELECT GROUP_CONCAT(i.provider ORDER BY i.provider) FROM anemia_participant_identity i WHERE i.account_id=a.id) AS providers
    FROM anemia_participant_account a LEFT JOIN anemia_participant_password p ON p.account_id=a.id
    WHERE ".implode(' AND ',$where)." ORDER BY a.connected_at DESC,a.id DESC LIMIT 31 OFFSET $offset");
$query->execute($params);
$rows=$query->fetchAll(PDO::FETCH_ASSOC);
$hasNext=count($rows)>30;
if ($hasNext) array_pop($rows);
$summary=$db->query('SELECT COUNT(*) AS total,COUNT(suspended_at) AS suspended FROM anemia_participant_account')->fetch(PDO::FETCH_ASSOC);
$base=['view'=>'members','member_search'=>$search,'member_status'=>$status,'member_page'=>$page];
$memberUrl=static fn(array $add=[]): string=>'dashboard.php?'.http_build_query(array_replace($base,$add));
$member=filter_input(INPUT_GET,'member')??'';
$task=filter_input(INPUT_GET,'member_task')??'';
$detail=null;
$audit=[];
if (is_string($member)&&preg_match('/^[a-f0-9]{32}$/D',$member)) {
    $query=$db->prepare('SELECT a.id,a.connected_at,a.suspended_at,p.username,m.completed_at,
        (SELECT GROUP_CONCAT(i.provider ORDER BY i.provider) FROM anemia_participant_identity i WHERE i.account_id=a.id) AS providers,
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
$methods=static function(array $row): string {
    $labels=['line'=>'LINE','google'=>'Google','facebook'=>'Facebook'];
    $items=$row['username']?['PIN']:[];
    foreach (explode(',',$row['providers']??'') as $key) if (isset($labels[$key])) $items[]=$labels[$key];
    return implode(' · ',$items)?:'—';
};
?>
<section class="card admin-report stack">
 <h2><?= workspaceIcon('users') ?> จัดการบัญชีผู้เข้าร่วม</h2>
 <?php if ($memberNotice!==''): ?><p class="status" role="status"><?= esc($memberNotice) ?></p><?php endif; ?>
 <div class="contact-pending-summary">
  <span><?= workspaceIcon('users') ?> สมาชิกทั้งหมด <strong><?= number_format((int)$summary['total']) ?></strong></span>
  <span><?= workspaceIcon('ready') ?> ใช้งานได้ <strong><?= number_format((int)$summary['total']-(int)$summary['suspended']) ?></strong></span>
  <span><?= workspaceIcon('lock') ?> ระงับ <strong><?= number_format((int)$summary['suspended']) ?></strong></span>
 </div>
 <form method="get" class="admin-filters member-filters">
  <input type="hidden" name="view" value="members">
  <label>ค้นหาสมาชิก<input type="search" name="member_search" value="<?= esc($search) ?>" placeholder="ชื่อบัญชีหรือรหัสสมาชิก" maxlength="40"></label>
  <label>สถานะ<select name="member_status"><option value="">ทั้งหมด</option><option value="active" <?= $status==='active'?'selected':'' ?>>ใช้งานได้</option><option value="suspended" <?= $status==='suspended'?'selected':'' ?>>ระงับ</option></select></label>
  <button class="action" type="submit"><?= workspaceIcon('filter') ?>แสดงข้อมูล</button>
 </form>
 <?php if ($rows): ?>
 <div class="tablewrap"><table class="admin-table">
  <thead><tr><th>สมาชิก</th><th>วิธีเข้าสู่ระบบ</th><th>วันที่สมัคร</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
  <tbody><?php foreach ($rows as $row): ?>
   <tr><th scope="row"><?= esc(anemiaMemberListName($row['username'],$row['id'])) ?></th>
    <td><?= esc($methods($row)) ?></td><td><?= esc(workspaceThaiShortDate($row['connected_at'])) ?></td>
    <td><?= $row['suspended_at']?'ระงับ':'ใช้งานได้' ?></td>
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
 <?php else: ?><p>ไม่พบสมาชิกตามตัวกรอง</p><?php endif; ?>
 <?php if ($page>1||$hasNext): ?><nav class="actions" aria-label="หน้ารายชื่อสมาชิก">
  <?php if ($page>1): ?><a class="action" href="<?= esc($memberUrl(['member_page'=>$page-1])) ?>"><?= workspaceIcon('back') ?>ก่อนหน้า</a><?php endif; ?>
  <span>หน้า <?= $page ?></span>
  <?php if ($hasNext): ?><a class="action" href="<?= esc($memberUrl(['member_page'=>$page+1])) ?>">ถัดไป <?= workspaceIcon('next') ?></a><?php endif; ?>
 </nav><?php endif; ?>
</section>
<?php if ($detail): ?>
<dialog id="member-details" class="workspace-dialog" open aria-labelledby="member-details-title">
 <header class="workspace-dialog-heading"><h2 id="member-details-title"><?= $task==='rename'?'แก้ไขชื่อบัญชี':($task==='status'?($detail['suspended_at']?'เปิดบัญชีคืน':'ระงับบัญชี'):'รายละเอียดสมาชิก') ?></h2><a class="action" href="<?= esc($memberUrl()) ?>" aria-label="ปิด"><?= workspaceIcon('close') ?></a></header>
 <?php if ($memberNotice!==''): ?><p class="status" role="status"><?= esc($memberNotice) ?></p><?php endif; ?>
 <p><strong><?= esc($detail['username']??'บัญชีจากช่องทางภายนอก') ?></strong><br><small><?= esc($detail['id']) ?></small></p>
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
