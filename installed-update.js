(() => {
 const runningBuild='20261125221045';
 if(!window.matchMedia('(display-mode: standalone), (display-mode: fullscreen)').matches&&navigator.standalone!==true)return;
 const manifest=new URL('release.json',document.currentScript.src);
 let checking=false;
 let navigating=false;
 function requireUpdate(next,retry=false){
  const notice=document.createElement('dialog');
  notice.setAttribute('aria-label','อัปเดตแอป');
  notice.addEventListener('cancel',event=>event.preventDefault());
  const text=document.createElement('p');
  text.textContent=retry?'ยังโหลดเวอร์ชันล่าสุดไม่ได้ กรุณาลองอีกครั้ง':'กำลังอัปเดต Anemia Portal';
  notice.append(text);
  if(retry){
   const button=document.createElement('button');
   button.type='button';
   button.textContent='ลองโหลดใหม่';
   button.addEventListener('click',()=>{next.searchParams.set('app_retry',Date.now());location.replace(next.href);});
   notice.append(button);
  }
  document.body.append(notice);
  if(typeof notice.showModal==='function')notice.showModal();
  if(!retry)location.replace(next.href);
 }
 async function checkBuild(){
  if(checking||navigating||document.hidden)return;
  checking=true;
  const controller=new AbortController();
  const timeout=window.setTimeout(()=>controller.abort(),8000);
  try {
   manifest.searchParams.set('_',Date.now());
   const response=await fetch(manifest,{cache:'no-store',credentials:'same-origin',signal:controller.signal});
   if(!response.ok)return;
   const release=await response.json();
   if(release.format!==1||typeof release.build!=='string'||!/^\d{14}$/.test(release.build)||release.build<=runningBuild)return;
   const next=new URL(location.href);
   if(next.searchParams.has('state')&&(next.searchParams.has('code')||next.searchParams.has('error')))return;
   if(next.searchParams.get('app_build')===release.build){
    // Reload once more with a fresh URL if an intermediary served older files.
    if(next.searchParams.has('app_retry')){
     navigating=true;
     requireUpdate(next,true);
     return;
    }
    next.searchParams.set('app_retry','1');
   }else next.searchParams.delete('app_retry');
   next.searchParams.set('app_build',release.build);
   navigating=true;
   requireUpdate(next);
  } catch {
   // Retry on the next check when the connection is available.
  } finally {
   window.clearTimeout(timeout);
   checking=false;
  }
 }
 document.addEventListener('visibilitychange',checkBuild);
 window.addEventListener('pageshow',checkBuild);
 window.addEventListener('online',checkBuild);
 window.setInterval(checkBuild,15000);
 checkBuild();
})();
