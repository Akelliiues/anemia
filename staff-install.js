(() => {
 const controls=[...document.querySelectorAll('[data-staff-install],[data-public-install]')];
 const help=document.querySelector('[data-install-help]');
 const status=document.querySelector('[data-install-result]');
 const invite=document.querySelector('[data-install-invite]');
 let promptEvent;
 const standalone=window.matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
 function installed(){
  if(invite?.open)invite.close();
  controls.forEach(control=>control.hidden=true);
  if(help)help.hidden=true;
  if(status){status.textContent='เปิด '+(status.dataset.appName||'Anemia Staff')+' จากหน้าจอหลักได้แล้ว';status.hidden=false;}
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
