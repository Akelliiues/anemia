<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

const ANEMIA_REQUEST_STATES = [
    'pending' => 'รอเจ้าหน้าที่รับเรื่อง',
    'contacted' => 'เจ้าหน้าที่ติดต่อแล้ว',
    'scheduled' => 'ยืนยันวันนัดแล้ว',
    'attended' => 'เข้ารับบริการแล้ว',
    'test_completed' => 'ตรวจยืนยันแล้ว',
    'follow_up' => 'อยู่ระหว่างติดตามผล',
    'closed' => 'ปิดคำขอแล้ว',
];
