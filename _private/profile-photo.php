<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

function anemiaProfilePhoto(array $file): ?string
{
    if (($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
    if (($file['error']??null)!==UPLOAD_ERR_OK || ($file['size']??0)>5*1024*1024 ||
        !is_string($file['tmp_name']??null) || !is_uploaded_file($file['tmp_name'])) {
        throw new DomainException('เลือกรูปขนาดไม่เกิน 5 MB แล้วลองใหม่');
    }
    if (!function_exists('imagecreatefromstring')) throw new DomainException('โฮสต์ยังไม่รองรับการอัปโหลดรูปโปรไฟล์');
    $bytes=file_get_contents($file['tmp_name']);
    $size=is_string($bytes)?@getimagesizefromstring($bytes):false;
    if (!$size || !in_array($size[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true) ||
        min($size[0],$size[1])<1 || $size[0]*$size[1]>16000000) {
        throw new DomainException('ใช้รูป JPG, PNG หรือ WebP ที่มีความละเอียดไม่เกิน 16 ล้านพิกเซล');
    }
    $image=@imagecreatefromstring($bytes);
    if (!$image) throw new DomainException('เปิดรูปนี้ไม่ได้ กรุณาเลือกรูปใหม่');
    $side=min($size[0],$size[1]);
    $preview=imagecreatetruecolor(256,256);
    imagefill($preview,0,0,imagecolorallocate($preview,250,247,252));
    imagecopyresampled($preview,$image,0,0,intdiv($size[0]-$side,2),intdiv($size[1]-$side,2),256,256,$side,$side);
    ob_start();
    $encoded=imagejpeg($preview,null,85);
    $photo=ob_get_clean();
    if (!$encoded || !is_string($photo) || strlen($photo)>65535) throw new DomainException('บันทึกรูปนี้ไม่ได้ กรุณาเลือกรูปใหม่');
    return $photo;
}
