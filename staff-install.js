(() => {
 const controls=[...document.querySelectorAll('[data-staff-install]')];
 const help=document.querySelector('[data-install-help]');
 const status=document.querySelector('[data-install-result]');
 let promptEvent;
 const standalone=window.matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
 function installed(){
  controls.forEach(control=>control.hidden=true);
  if(help)help.hidden=true;
  if(status){status.textContent='เปิด Anemia Staff จากหน้าจอหลักได้แล้ว';status.hidden=false;}
 }
 if(standalone){installed();return;}
 window.addEventListener('beforeinstallprompt',event=>{
  event.preventDefault();
  promptEvent=event;
  controls.forEach(control=>control.hidden=false);
 });
 for(const control of controls)control.addEventListener('click',async event=>{
  if(!promptEvent)return;
  event.preventDefault();
  const pending=promptEvent;
  promptEvent=null;
  try{
   await pending.prompt();
   if((await pending.userChoice).outcome==='accepted')installed();
  }catch{
   if(help)help.hidden=false;
   else location.href=control.href;
  }finally{
   if(control.tagName==='BUTTON')control.hidden=true;
  }
 });
 window.addEventListener('appinstalled',installed);
})();
