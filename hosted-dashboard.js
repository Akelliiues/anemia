const icons={drop:'<path d="M12 2s7 8 7 13a7 7 0 0 1-14 0c0-5 7-13 7-13Z"/><path d="M9 16a3 3 0 0 0 3 3"/>',check:'<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',book:'<path d="M12 6v15M3 4c4-1 7 0 9 2 2-2 5-3 9-2v15c-4-1-7 0-9 2-2-2-5-3-9-2z"/>',sound:'<path d="M3 9v6h4l5 4V5L7 9H3m13 0a4 4 0 0 1 0 6m3-9a8 8 0 0 1 0 12"/>',hospital:'<path d="M4 21V4h16v17M2 21h20M9 21v-5h6v5m-3-14v6M9 10h6"/>',refresh:'<path d="M20 7v5h-5M4 17v-5h5m-4-1a7 7 0 0 1 12-5l3 6M4 12l3 6a7 7 0 0 0 12-5"/>',alert:'<path d="M12 3 2 21h20L12 3Zm0 6v5m0 3v1"/>',phone:'<path d="M7 3H4a1 1 0 0 0-1 1c0 9 8 17 17 17a1 1 0 0 0 1-1v-3l-5-2-2 2a14 14 0 0 1-7-7l2-2z"/>',arrow:'<path d="M19 12H5m6 6-6-6 6-6"/>'};
Object.assign(icons,{
 calendar:'<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 10h18m-13 5h2m4 0h2"/>',
 pulse:'<path d="M2 12h4l3-6 4 12 3-6h6"/><path d="M5 5a9 9 0 0 1 14 0M5 19a9 9 0 0 0 14 0"/>',
 history:'<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2m6 2V2M9 10h6m-6 5h6"/>',
 food:'<path d="M4 3v7a3 3 0 0 0 6 0V3M7 3v18m13-18c-4 1-5 5-5 9h5m0-9v18"/>',
 cup:'<path d="M4 9h13v4a6 6 0 0 1-6 6h-1a6 6 0 0 1-6-6V9Zm13 1h2a2 2 0 0 1 0 4h-2M3 21h16M8 3v3m5-3v3"/>',
 download:'<path d="M12 3v12m-4-4 4 4 4-4M4 16v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/>',
 chart:'<path d="M4 20h16M7 16v-4m5 4V8m5 8V4" stroke-width="2.4"/>',
 next:'<path d="m10 6 6 6-6 6"/>'
});
const icon=name=>`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${icons[name]}</svg>`;
const button=(label,go,symbol,primary=false)=>`<button class="action ${primary?'primary':''}" data-go="${go}">${icon(symbol)}${label}</button>`;
const box=name=>`<span class="iconbox">${icon(name)}</span>`;
const main=document.querySelector('#hosted-sample-dashboard');
let barChartObserver=null;
function observeBarCharts(){
 barChartObserver?.disconnect();
 if(!window.IntersectionObserver||!window.matchMedia||
    window.matchMedia('(prefers-reduced-motion: reduce)').matches)return;
 const charts=main.querySelectorAll('.public-columns,.admin-month-chart,.admin-region-chart');
 if(!charts.length)return;
 barChartObserver=new window.IntersectionObserver((entries,observer)=>{
  for(const entry of entries){
   if(!entry.isIntersecting)continue;
   const bounds=entry.boundingClientRect;
   const viewport=entry.rootBounds;
   const visible=entry.intersectionRect;
   const available=viewport
    ?Math.min(bounds.width,viewport.width)*Math.min(bounds.height,viewport.height)
    :bounds.width*bounds.height;
   if(!available||visible.width*visible.height/available<.9)continue;
   entry.target.classList.replace('chart-waiting','chart-visible');
   observer.unobserve(entry.target);
  }
 },{threshold:Array.from({length:101},(_,index)=>index/100)});
 for(const chart of charts){
  chart.classList.add('chart-waiting');
  barChartObserver.observe(chart);
 }
}
const percent=(count,total)=>total?(count/total*100).toLocaleString('th-TH',{maximumFractionDigits:1})+'%':'—';

function renderHostedSample(scope){
 const y=window.scrollY;
 const mode=document.querySelector('.workspace-data-mode');
 if(mode?.elements.overview_year){
  mode.elements.region.value=adminRegion;
  mode.elements.overview_year.value=adminYear;
  mode.elements.overview_month.value=adminPeriod;
 }
 main.innerHTML=reportView(scope,false);
 if(window.scrollY!==y)window.scrollTo({top:y,behavior:'instant'});
 main.setAttribute('aria-busy','false');
 observeBarCharts();
 if(adminView==='overview')animateAdminCounters();
}
main.addEventListener('click',event=>{
 const control=event.target.closest('[data-chart-region],[data-go],[data-admin-risk],[data-overview-metric]');
 if(!control)return;
 if(control.dataset.overviewMetric){
  selectedOverviewMetric=control.dataset.overviewMetric;
  adminView='reports';
  staff();
  main.querySelector('[data-overview-metric][aria-pressed=true]')?.focus({preventScroll:true});
  return;
 }
 if(control.dataset.adminRisk){pickAdminRisk(control);return;}
 if(control.dataset.chartRegion){selectChartRegion(Number(control.dataset.chartRegion));return;}
 switch(control.dataset.go){
 case 'admin-clear-region':adminRegion='';staff();document.querySelector('#admin-region')?.focus({preventScroll:true});break;
 case 'admin-reports':selectedOverviewMetric=null;adminView='reports';staff();break;
 case 'admin-csv':adminExport();break;
 case 'admin-print':if(canReport(adminScope())&&adminView==='reports')printDemoReport(adminScope());break;
 }
});
document.querySelector('[data-page-back]')?.addEventListener('click',event=>{
 if(adminView!=='reports'||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
 event.preventDefault();
 event.stopImmediatePropagation();
 selectedOverviewMetric=null;
 adminView='overview';
 staff();
},{capture:true});
staff();
