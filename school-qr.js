for(const link of document.querySelectorAll('[data-school-qr]')){
 const target=link.nextElementSibling;
 link.parentElement.addEventListener('toggle',()=>{
  if(!link.parentElement.open||target.firstChild)return;
  const qr=qrcode(0,'M');
  qr.addData(link.href);
  qr.make();
  target.innerHTML=qr.createImgTag(4,16,'QR สำหรับเข้าร่วมรุ่นกิจกรรม');
 });
}
