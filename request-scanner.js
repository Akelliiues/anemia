function scannedRequestReference(value,origin,portalDirectory){
 const normalize=code=>/^(?:AN|DE)-[0-9A-F]{16}$/i.test(code)?code.toUpperCase():null;
 const raw=value.trim();
 const direct=normalize(raw);
 if(direct)return direct;
 try{
  const url=new URL(raw);
  if(url.origin!==origin||![portalDirectory,portalDirectory+'index.html'].includes(url.pathname))return null;
  const code=new URLSearchParams(url.hash.slice(1)).get('request');
  return code?normalize(code):null;
 }catch{return null;}
}

async function readRequestImage(file,decode){
 if(!file||file.type&&!file.type.startsWith('image/'))throw Error('กรุณาเลือกไฟล์ภาพใบรับคำขอ');
 if(file.size>15*1024*1024)throw Error('ภาพมีขนาดเกิน 15 MB กรุณาเลือกไฟล์ที่เล็กลง');
 const url=URL.createObjectURL(file);
 try{
  const image=new Image();
  await new Promise((resolve,reject)=>{
   image.onload=resolve;
   image.onerror=()=>reject(Error('เปิดรูปนี้ไม่ได้ กรุณาลองรูปอื่น'));
   image.src=url;
  });
  const code=decode(image,image.naturalWidth,image.naturalHeight);
  if(!code)throw Error('ไม่พบ QR ในภาพ กรุณาเลือกรูปที่ชัดขึ้น');
  return code;
 }finally{URL.revokeObjectURL(url);}
}
function decodeRequestFrame(canvas,context,frame,width,height){
 if(!width||!height||typeof jsQR!=='function'||!context)return null;
 const scale=Math.min(1,1000/Math.max(width,height));
 canvas.width=Math.max(1,Math.round(width*scale));
 canvas.height=Math.max(1,Math.round(height*scale));
 context.drawImage(frame,0,0,canvas.width,canvas.height);
 const pixels=context.getImageData(0,0,canvas.width,canvas.height);
 return jsQR(pixels.data,pixels.width,pixels.height)?.data??null;
}
const scannerForm=document.querySelector('#request-scanner-form');
if(scannerForm){
 const input=scannerForm.elements.receipt;
 const video=document.querySelector('#request-scan-video');
 const start=document.querySelector('#request-scan-start');
 const stop=document.querySelector('#request-scan-stop');
 const upload=document.querySelector('#request-scan-file');
 const drop=document.querySelector('#request-scan-drop');
 const message=document.querySelector('#request-scan-message');
 const canvas=document.createElement('canvas');
 const context=canvas.getContext('2d',{willReadFrequently:true});
 const portalDirectory=new URL('./',location.href).pathname;
 let stream,timer,operation=0,submitting=false;

 function stopCamera(){
  operation++;
  clearTimeout(timer);
  stream?.getTracks().forEach(track=>track.stop());
  stream=null;
  video.srcObject=null;
  video.hidden=true;
  stop.hidden=true;
  start.disabled=false;
 }
 const decode=(frame,width,height)=>decodeRequestFrame(canvas,context,frame,width,height);
 function submitCode(code){
  const receipt=scannedRequestReference(code,location.origin,portalDirectory);
  if(!receipt){
   message.textContent='QR นี้ไม่ใช่ใบรับคำขอของระบบ';
   return false;
  }
  input.value=receipt;
  scannerForm.requestSubmit();
  return true;
 }
 start.addEventListener('click',async()=>{
  if(!navigator.mediaDevices?.getUserMedia||typeof jsQR!=='function'||!context){
   message.textContent='เปิดกล้องไม่ได้บนอุปกรณ์นี้ กรุณาใช้รูป QR หรือกรอกเลขคำขอ';
   return;
  }
  const id=++operation;
  start.disabled=true;
  stop.hidden=false;
  message.textContent='กำลังขออนุญาตใช้กล้อง';
  try{
   const opened=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});
   if(id!==operation){opened.getTracks().forEach(track=>track.stop());return;}
   stream=opened;
   video.srcObject=stream;
   video.hidden=false;
   await video.play();
   if(id!==operation)return;
   message.textContent='จัด QR ให้อยู่กลางภาพ';
   const scan=()=>{
    if(id!==operation)return;
    try{
     const qr=decode(video,video.videoWidth,video.videoHeight);
     if(qr&&submitCode(qr))return;
     timer=setTimeout(scan,180);
    }catch{
     stopCamera();
     message.textContent='อ่านภาพจากกล้องไม่ได้ กรุณาใช้รูป QR หรือกรอกเลขคำขอ';
    }
   };
   scan();
  }catch{
   if(id!==operation)return;
   stopCamera();
   message.textContent='เปิดกล้องไม่สำเร็จ กรุณาตรวจสิทธิ์กล้องหรือกรอกเลขคำขอ';
  }
 });
 stop.addEventListener('click',()=>{stopCamera();message.textContent='ปิดกล้องแล้ว';});
 async function scanFile(file){
  stopCamera();
  const id=operation;
  message.textContent='กำลังอ่าน QR จากภาพ';
  try{
   const code=await readRequestImage(file,decode);
   if(id===operation)submitCode(code);
  }catch(error){if(id===operation)message.textContent=error.message;}
 }
 upload.addEventListener('change',()=>{if(upload.files[0])scanFile(upload.files[0]);upload.value='';});
 drop.addEventListener('dragover',event=>{
  event.preventDefault();
  event.dataTransfer.dropEffect='copy';
  drop.classList.add('is-dragging');
 });
 drop.addEventListener('dragleave',()=>drop.classList.remove('is-dragging'));
 drop.addEventListener('drop',event=>{
  event.preventDefault();
  drop.classList.remove('is-dragging');
  scanFile(event.dataTransfer.files[0]);
 });
 document.addEventListener('paste',event=>{
  const file=[...event.clipboardData.items].find(item=>item.kind==='file'&&item.type.startsWith('image/'))?.getAsFile();
  if(!file)return;
  event.preventDefault();
  scanFile(file);
 });
 scannerForm.addEventListener('submit',event=>{
  const receipt=scannedRequestReference(input.value,location.origin,portalDirectory);
  if(submitting||!receipt){
   event.preventDefault();
   if(!submitting)message.textContent='กรุณากรอกเลขคำขอหรือวางลิงก์จากใบรับคำขอ';
   return;
  }
  input.value=receipt;
  submitting=true;
  stopCamera();
  message.textContent='กำลังตรวจสอบใบรับคำขอ';
 });
 window.addEventListener('pagehide',stopCamera);
 window.addEventListener('pageshow',()=>{submitting=false;});
}
