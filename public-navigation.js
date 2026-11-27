(() => {
const publicProfilePhoto=document.querySelector('[data-public-profile-photo]');
if(publicProfilePhoto){
 const accountMenu=publicProfilePhoto.closest('.public-nav-account');
 const guestLink=document.querySelector('[data-public-account-guest]');
 publicProfilePhoto.addEventListener('load',()=>{
  publicProfilePhoto.hidden=false;
  publicProfilePhoto.previousElementSibling.hidden=true;
 });
 let sessionCheck=0;
 const refreshAccountMenu=async()=>{
  const check=++sessionCheck;
  let signedIn=!accountMenu.hidden;
  try{
   const response=await fetch('participant-photo.php?session=1',{cache:'no-store'});
   if(response.status===204||response.status===404)signedIn=response.status===204;
  }catch{}
  if(check!==sessionCheck)return;
  guestLink.hidden=signedIn;
  accountMenu.hidden=!signedIn;
  if(signedIn){
   if(!publicProfilePhoto.hasAttribute('src'))publicProfilePhoto.src='participant-photo.php';
  }else{
   accountMenu.open=false;
   publicProfilePhoto.removeAttribute('src');
   publicProfilePhoto.hidden=true;
   publicProfilePhoto.previousElementSibling.hidden=false;
  }
 };
 const resetAccountMenu=()=>{
  ++sessionCheck;
  accountMenu.open=false;
  accountMenu.hidden=true;
  guestLink.hidden=false;
 };
 window.addEventListener('pagehide',resetAccountMenu);
 window.addEventListener('pageshow',event=>{if(event.persisted)refreshAccountMenu();});
 document.addEventListener('visibilitychange',()=>{
  if(document.hidden)resetAccountMenu();else refreshAccountMenu();
 });
 if(accountMenu.hidden)refreshAccountMenu();
 else publicProfilePhoto.src='participant-photo.php';
}
const publicMenuToggle=document.querySelector('.public-menu-toggle');
const publicNavigation=document.querySelector('#public-navigation');
if(publicMenuToggle&&publicNavigation){
 const groups=[...publicNavigation.querySelectorAll('.public-nav-group')];
 const mobile=window.matchMedia('(max-width: 990px)');
 const closeGroups=()=>groups.forEach(group=>group.open=false);
 const setMenu=open=>{
  publicMenuToggle.setAttribute('aria-expanded',String(open));
  publicNavigation.classList.toggle('nav-collapsed',!open);
  if(!open)closeGroups();
 };
 publicMenuToggle.hidden=false;
 setMenu(false);
 publicMenuToggle.addEventListener('click',()=>setMenu(publicMenuToggle.getAttribute('aria-expanded')!=='true'));
 publicNavigation.addEventListener('click',event=>{
  const action=event.target.closest('button,a');
  if(!action)return;
  if(action.matches('button[data-go]')){
   const focusTarget=mobile.matches?publicMenuToggle:action.closest('details')?.querySelector('summary');
   focusTarget?.focus({preventScroll:true});
  }
  setMenu(false);
 });
 document.addEventListener('click',event=>{if(!event.target.closest('.public-header'))setMenu(false);});
 mobile.addEventListener('change',()=>setMenu(false));
 publicNavigation.closest('.public-header').addEventListener('keydown',event=>{
  if(event.key!=='Escape')return;
  const group=event.target.closest('.public-nav-group[open]');
  if(group){group.open=false;group.querySelector('summary').focus();return;}
  if(publicMenuToggle.getAttribute('aria-expanded')!=='true')return;
  setMenu(false);publicMenuToggle.focus();
 });
}
})();
