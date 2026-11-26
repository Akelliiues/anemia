(() => {
 const publicInvite=document.body.dataset.publicInstallInvite;
 if(publicInvite){
  const dialog=document.createElement('dialog');
  dialog.className='workspace-dialog staff-install-invite';
  dialog.dataset.installInvite='portal-'+publicInvite;
  dialog.setAttribute('aria-labelledby','portal-install-invite-title');
  dialog.innerHTML='<header class="workspace-dialog-heading"><h2 id="portal-install-invite-title">ติดตั้ง Anemia Portal</h2><form method="dialog"><button type="submit" aria-label="ปิด">✕</button></form></header><p>เพิ่มไว้บนหน้าจอหลัก เพื่อกลับมาประเมินและเรียนรู้ได้ง่ายขึ้น</p><div class="actions"><a class="action primary" href="portal-install.html" data-public-install>ติดตั้งแอป</a><form method="dialog"><button class="action" type="submit">ไว้ภายหลัง</button></form></div>';
  document.body.append(dialog);
 }
 const controls=[...document.querySelectorAll('[data-staff-install],[data-public-install]')];
 const help=document.querySelector('[data-install-help]');
 const status=document.querySelector('[data-install-result]');
 const invite=document.querySelector('[data-install-invite]');
 const scannerManifest=document.querySelector('link[rel="manifest"][href="staff-scanner.webmanifest"]');
 const scannerKey='anemia-unit-scanner-installed';
 const scannerLinks=document.querySelectorAll('[data-scanner-install-link]');
 let promptEvent;
 let installationConfirmed=false;
 const standalone=window.matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
 const savedScanner=()=>{try{return localStorage.getItem(scannerKey)==='1';}catch{return false;}};
 const hideScannerLinks=()=>scannerLinks.forEach(link=>link.hidden=savedScanner());
 const rememberScanner=()=>{try{localStorage.setItem(scannerKey,'1');}catch{}hideScannerLinks();};
 hideScannerLinks();
 window.addEventListener('pageshow',hideScannerLinks);
 window.addEventListener('storage',event=>{if(event.key===scannerKey)hideScannerLinks();});
 if(scannerManifest&&standalone&&new URLSearchParams(location.search).get('launch')==='scanner')rememberScanner();
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
 const otherApp=document.querySelector('[data-install-other-app]');
 if(standalone&&!scannerManifest){installed();return;}
 if((standalone&&!otherApp)||(scannerManifest&&otherApp&&savedScanner()))installed();
 if(invite?.showModal && matchMedia('(max-width: 767px) and (pointer: coarse)').matches){
  const inviteKey='anemia-'+(invite.dataset.installInvite||'staff')+'-install-invite';
  let seen=false;
  try{seen=sessionStorage.getItem(inviteKey)==='1';}catch{}
  if(!seen){
   invite.showModal();
   try{sessionStorage.setItem(inviteKey,'1');}catch{}
  }
 }
 window.addEventListener('beforeinstallprompt',event=>{
  event.preventDefault();
  promptEvent=event;
  if(scannerManifest){
   try{localStorage.removeItem(scannerKey);}catch{}
   installationConfirmed=false;
   if(help)help.hidden=false;
   if(status)status.hidden=true;
  }
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
 window.addEventListener('appinstalled',()=>{
  if(scannerManifest)rememberScanner();
  installed();
 });
})();
