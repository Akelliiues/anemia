<?php
declare(strict_types=1);
http_response_code(410);
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=UTF-8');
echo 'พื้นที่ทดลองตามบทบาทปิดให้บริการแล้ว';
