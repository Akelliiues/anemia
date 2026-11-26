(() => {
 const runningBuild='20261125221045';
 if(!window.matchMedia('(display-mode: standalone)').matches&&navigator.standalone!==true)return;
 const manifest=new URL('release.json',document.currentScript.src);
 let checking=false;
 let navigating=false;
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
   if(next.searchParams.get('app_build')===release.build)return;
   next.searchParams.set('app_build',release.build);
   navigating=true;
   location.replace(next.href);
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
 window.setInterval(checkBuild,60000);
 checkBuild();
})();
