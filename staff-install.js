(() => {
 const controls=[...document.querySelectorAll('[data-staff-install],[data-public-install]')];
 const help=document.querySelector('[data-install-help]');
 const status=document.querySelector('[data-install-result]');
 const invite=document.querySelector('[data-install-invite]');
 let promptEvent;
 let installationConfirmed=false;
 const standalone=window.matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
 function report(message){
  if(status){status.textContent=message;status.hidden=false;}
 }
 function installed(){
  installationConfirmed=true;
  if(invite?.open)invite.close();
  controls.forEach(control=>control.hidden=true);
  if(help)help.hidden=true;
  report('ติดตั้งแล้ว · ค้นหา '+(status?.dataset.appName||'Anemia Staff')+' บนหน้าจอหลักหรือในรายการแอป');
 }
 if(standalone&&!document.querySelector('[data-install-other-app]')){installed();return;}
 if(invite?.showModal && matchMedia('(max-width: 767px) and (pointer: coarse)').matches){
  let seen=false;
  try{seen=sessionStorage.getItem('anemia-staff-install-invite')==='1';}catch{}
  if(!seen){
   invite.showModal();
   try{sessionStorage.setItem('anemia-staff-install-invite','1');}catch{}
  }
 }
 window.addEventListener('beforeinstallprompt',event=>{
  event.preventDefault();
  promptEvent=event;
  controls.forEach(control=>control.hidden=false);
 });
 for(const control of controls)control.addEventListener('click',async event=>{
  if(invite?.open)invite.close();
  if(!promptEvent){
   if(help)report('ยังไม่ได้ติดตั้ง กรุณาเลือกติดตั้งจากเมนูเบราว์เซอร์ตามขั้นตอนด้านล่าง');
   return;
  }
  event.preventDefault();
  const pending=promptEvent;
  promptEvent=null;
  try{
   await pending.prompt();
   const {outcome}=await pending.userChoice;
   if(!installationConfirmed)report(outcome==='accepted'?'ยืนยันติดตั้งแล้ว กำลังรอเบราว์เซอร์แจ้งผล':'ยังไม่ได้ติดตั้ง คุณติดตั้งจากเมนูเบราว์เซอร์ได้');
  }catch{
   if(help){help.hidden=false;report('เปิดหน้าต่างติดตั้งไม่ได้ กรุณาใช้เมนูเบราว์เซอร์');}
   else location.href=control.href;
  }finally{
   if(control.tagName==='BUTTON')control.hidden=true;
  }
 });
 window.addEventListener('appinstalled',installed);
})();
