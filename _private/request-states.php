<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

const ANEMIA_REQUEST_STATES = [
    'pending' => 'รอเจ้าหน้าที่รับเรื่อง',
    'accepted' => 'รับเรื่องแล้ว',
    'contacted' => 'เจ้าหน้าที่ติดต่อแล้ว',
    'retry_contact' => 'รอติดต่อซ้ำ',
    'scheduled' => 'ยืนยันวันนัดแล้ว',
    'attended' => 'เข้ารับบริการแล้ว',
    'test_completed' => 'ตรวจยืนยันแล้ว',
    'follow_up' => 'อยู่ระหว่างติดตามผล',
    'closed' => 'ปิดคำขอแล้ว',
];

const ANEMIA_REQUEST_ACTIONS = [
    'accepted'=>['รับเรื่อง',['pending']],
    'contacted'=>['บันทึกว่าติดต่อแล้ว',['pending','accepted','retry_contact']],
    'retry_contact'=>['นัดติดต่อซ้ำ',['pending','accepted','contacted','retry_contact']],
    'scheduled'=>['ยืนยันวันนัด',['pending','accepted','contacted','retry_contact','scheduled']],
    'attended'=>['ยืนยันเช็กอิน',['pending','accepted','contacted','retry_contact','scheduled']],
    'test_completed'=>['บันทึกว่าตรวจยืนยันแล้ว',['attended']],
    'follow_up'=>['กำหนดวันติดตาม',['test_completed','follow_up']],
    'closed'=>['ปิดคำขอ',['pending','accepted','contacted','retry_contact','scheduled','attended','test_completed','follow_up']],
];

function anemiaApplyRequestAction(PDO $db,string $table,string $receipt,string $action,string $actor,?string $when): bool
{
    $query=$db->prepare('SELECT state,appointment_at,next_contact_at FROM '.$table.' WHERE receipt=? FOR UPDATE');
    $query->execute([$receipt]);
    $row=$query->fetch(PDO::FETCH_ASSOC);
    if (!$row || !in_array($row['state'],ANEMIA_REQUEST_ACTIONS[$action][1] ?? [],true)) return false;
    $appointment=$action==='scheduled'?$when:$row['appointment_at'];
    $next=in_array($action,['retry_contact','follow_up'],true)?$when:null;
    if ($row['state']===$action && $appointment===$row['appointment_at'] && $next===$row['next_contact_at']) return false;
    $db->prepare('UPDATE '.$table.' SET state=?,appointment_at=?,next_contact_at=? WHERE receipt=?')
       ->execute([$action,$appointment,$next,$receipt]);
    $db->prepare('INSERT INTO anemia_request_history (receipt,state,actor,changed_at) VALUES (?,?,?,?)')
       ->execute([$receipt,$action,$actor,date('Y-m-d H:i:s')]);
    return true;
}
