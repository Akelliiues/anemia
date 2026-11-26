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
 const portalManifest=document.querySelector('link[rel="manifest"][href="portal.webmanifest"]');
 const scannerKey='anemia-unit-scanner-installed';
 const scannerLinks=document.querySelectorAll('[data-scanner-install-link]');
 const copyPortal=document.querySelector('[data-copy-portal-install]');
 let promptEvent;
 let installationConfirmed=false;
 const standalone=window.matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
 const launch=new URLSearchParams(location.search).get('launch');
 let runningPortal=false;
 if(standalone){
  try{
   if(launch==='portal'||launch==='scanner'||launch==='staff')sessionStorage.setItem('anemia-running-app',launch);
   runningPortal=sessionStorage.getItem('anemia-running-app')==='portal';
  }catch{runningPortal=launch==='portal';}
 }
 const savedScanner=()=>{try{return localStorage.getItem(scannerKey)==='1';}catch{return false;}};
 const hideScannerLinks=()=>scannerLinks.forEach(link=>link.hidden=savedScanner());
 const rememberScanner=()=>{try{localStorage.setItem(scannerKey,'1');}catch{}hideScannerLinks();};
 hideScannerLinks();
 window.addEventListener('pageshow',hideScannerLinks);
 window.addEventListener('storage',event=>{if(event.key===scannerKey)hideScannerLinks();});
 if(scannerManifest&&standalone&&launch==='scanner')rememberScanner();
 function report(message){
  if(status){status.textContent=message;status.hidden=false;}
 }
 if(copyPortal && navigator.clipboard?.writeText){
  copyPortal.hidden=false;
  copyPortal.addEventListener('click',async()=>{
   try{
    await navigator.clipboard.writeText(new URL('portal-install.html',location.href).href);
    report('คัดลอกลิงก์แล้ว วางลิงก์ใน Chrome หรือ Safari เพื่อเปิดหน้าติดตั้ง');
   }catch{report('คัดลอกลิงก์ไม่ได้ กรุณาใช้เมนูเปิดในเบราว์เซอร์');}
  });
 }
 function installed(){
  installationConfirmed=true;
  if(invite?.open)invite.close();
  controls.forEach(control=>control.hidden=true);
  if(help)help.hidden=true;
  report('ติดตั้งแล้ว · ค้นหา '+(status?.dataset.appName||'Anemia Staff')+' บนหน้าจอหลักหรือในรายการแอป');
 }
 const otherApp=document.querySelector('[data-install-other-app]');
 if(standalone&&((!scannerManifest&&!portalManifest)||(portalManifest&&runningPortal))){installed();return;}
 if(scannerManifest&&((standalone&&!otherApp)||(otherApp&&savedScanner())))installed();
 if(!standalone&&invite?.showModal && matchMedia('(max-width: 767px) and (pointer: coarse)').matches){
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
  if(portalManifest&&typeof navigator.install==='function'){
   event.preventDefault();
   if(control.getAttribute('aria-disabled')==='true')return;
   control.setAttribute('aria-disabled','true');
   try{
    await navigator.install({manifest:portalManifest.href});
    installed();
   }catch{
    if(help)report('หากยังไม่เห็นแอป ให้เปิดหน้านี้ในเบราว์เซอร์แล้วใช้เมนูติดตั้งด้านล่าง');
    else location.assign(control.href);
   }finally{control.removeAttribute('aria-disabled');}
   return;
  }
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
