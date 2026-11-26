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

const ANEMIA_APPOINTMENT_SLOTS = ['morning'=>'รอบเช้า','afternoon'=>'รอบบ่าย'];
const ANEMIA_CONFIRMATION_RESULTS = ['confirmed'=>'ผลยืนยันภาวะโลหิตจาง','not_confirmed'=>'ผลไม่ยืนยันภาวะโลหิตจาง','inconclusive'=>'ยังสรุปผลไม่ได้'];

function anemiaAppointmentLabel(string $appointment,?string $slot): string
{
    $months=['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    $date=new DateTimeImmutable($appointment);
    return $date->format('j').' '.$months[(int)$date->format('n')-1].' '.((int)$date->format('Y')+543).' · '.(ANEMIA_APPOINTMENT_SLOTS[$slot] ?? $date->format('H:i').' น.');
}

function anemiaApplyRequestAction(PDO $db,string $table,string $receipt,string $action,string $actor,?string $when,?string $slot=null): bool
{
    $query=$db->prepare('SELECT state,appointment_at,appointment_slot,next_contact_at FROM '.$table.' WHERE receipt=? FOR UPDATE');
    $query->execute([$receipt]);
    $row=$query->fetch(PDO::FETCH_ASSOC);
    if (!$row || !in_array($row['state'],ANEMIA_REQUEST_ACTIONS[$action][1] ?? [],true)) return false;
    $appointment=$action==='scheduled'?$when:$row['appointment_at'];
    $appointmentSlot=$action==='scheduled'?$slot:$row['appointment_slot'];
    $next=in_array($action,['retry_contact','follow_up'],true)?$when:null;
    if ($row['state']===$action && $appointment===$row['appointment_at'] && $appointmentSlot===$row['appointment_slot'] && $next===$row['next_contact_at']) return false;
    $db->prepare('UPDATE '.$table.' SET state=?,appointment_at=?,appointment_slot=?,next_contact_at=? WHERE receipt=?')
       ->execute([$action,$appointment,$appointmentSlot,$next,$receipt]);
    $db->prepare('INSERT INTO anemia_request_history (receipt,state,actor,changed_at) VALUES (?,?,?,?)')
       ->execute([$receipt,$action,$actor,date('Y-m-d H:i:s')]);
    return true;
}
