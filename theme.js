(() => {
 const key = 'anemia-theme';
 const system = matchMedia('(prefers-color-scheme: dark)');
 const root = document.documentElement;
 let preference;
 let control;
 try { preference = localStorage.getItem(key) || localStorage.getItem('anemia-feedback-theme'); } catch {}
 const apply = () => {
  const mode = ['light','dark'].includes(preference) ? preference : system.matches ? 'dark' : 'light';
  root.dataset.anemiaTheme = mode;
  if (control) control.setAttribute('aria-checked',String(mode === 'dark'));
 };
 apply();
 system.addEventListener('change',apply);
 window.addEventListener('storage', event => {
  if (event.key !== key) return;
  preference = event.newValue;
  apply();
 });
 document.addEventListener('DOMContentLoaded', () => {
  const addSignalLines=(container,placement)=>{
   if(!container||container.querySelector(':scope > .portal-neural'))return;
   const art=document.createElement('span');
   art.className='portal-neural portal-neural-'+placement;
   art.setAttribute('aria-hidden','true');
   for(let i=0;i<6;i++)art.append(document.createElement('i'));
   container.append(art);
  };
  addSignalLines(document.body,'page');
  const headings='.hero,.page-heading,.result-summary,.question-scene,.feedback-hero,.programme-heading,.admin-heading,.staff-access-cover,.staff-app-bar,.participant-profile-hero,.staff-mobile-hero';
  const decorateHeadings=container=>container.querySelectorAll(headings).forEach(heading=>{
   heading.classList.add('portal-neural-surface');
   addSignalLines(heading,'heading');
  });
  decorateHeadings(document);
  const publicMain=document.querySelector('.public-site #main');
  if(publicMain)new MutationObserver(()=>decorateHeadings(publicMain)).observe(publicMain,{childList:true});
  control = document.createElement('button');
  control.type = 'button';
  control.className = 'theme-float';
  control.setAttribute('role','switch');
  control.setAttribute('aria-label','โหมดมืด');
  control.title = 'สลับโหมดสว่าง–มืด';
  control.innerHTML = '<span class="theme-thumb" aria-hidden="true"></span><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20 15A9 9 0 0 1 9 4a9 9 0 1 0 11 11Z"/></svg>';
  control.addEventListener('click', () => {
   preference = root.dataset.anemiaTheme === 'dark' ? 'light' : 'dark';
   try { localStorage.setItem(key,preference); } catch {}
   apply();
  });
  document.body.append(control);
  apply();
 },{once:true});
})();
