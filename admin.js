const riskChartColors = {low:'#29b895',medium:'#f2b544',high:'#e85d96',unknown:'#9d88e4'};
const regionLabel = region => region === 13 ? 'เขตสุขภาพที่ 13 กรุงเทพมหานคร' : 'เขตสุขภาพที่ ' + region;
const adminRegions = Array.from({length:13}, (_, index) => index + 1);
const demoMonthNames = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
 'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
const overviewData=JSON.parse(document.getElementById('hosted-overview-data')?.textContent||'null');
const liveOverview=Array.isArray(overviewData?.rows);
const overviewPeriodLabel=period=>demoMonthNames[Number(period.slice(5))-1]+' '+(Number(period.slice(0,4))+543);
const adminPeriods = liveOverview?overviewData.periods.map(overviewPeriodLabel):Array.from({length:36},(_,index)=>{
 const monthIndex=index+11;
 return demoMonthNames[monthIndex%12]+' '+(2566+Math.floor(monthIndex/12));
});
const latestAdminPeriod=adminPeriods.at(-1);
const overviewYears=new Set(adminPeriods.map(period=>period.slice(-4)));
const overviewSelectedYear=overviewData?.year===''||overviewYears.has(overviewData?.year)?overviewData.year:latestAdminPeriod.slice(-4);
const overviewSelectedMonth=adminPeriods.includes(overviewData?.month)&&overviewData.month.slice(-4)===overviewSelectedYear?overviewData.month:'';
const adminFacilities = ['รพ.สต. ตัวอย่างประชาร่วมใจ', 'โรงพยาบาลชุมชนตัวอย่าง'];
const roles = {
 central:{label:'ผู้บริหารส่วนกลาง',region:null,description:'ดูยอดรวมและรายงานครบ 13 เขต'},
 regional:{label:'ผู้บริหารระดับเขต',region:10,description:'ดูยอดรวมและรายงานเฉพาะเขตที่รับผิดชอบ'},
 provincial:{label:'ผู้ประสานระดับจังหวัด',region:10,description:'ดูยอดรวมและรายงานเฉพาะจังหวัดอุบลราชธานี'},
 primary:{label:'เจ้าหน้าที่ รพ.สต.',region:10,facility:adminFacilities[0],description:'ดูและอัปเดตคำขอของหน่วยตัวอย่างที่รับผิดชอบ'},
 hospital:{label:'เจ้าหน้าที่โรงพยาบาล',region:10,facility:adminFacilities[1],description:'ดูและอัปเดตคำขอของโรงพยาบาลตัวอย่าง'},
 administrator:{label:'ผู้ดูแลสิทธิ์',region:null,description:'จัดการบัญชีและขอบเขตสิทธิ์ในเดโม่'}
};
const accounts = [
 {id:'DEMO-01',name:'บัญชีส่วนกลาง',role:'central',active:true},
 {id:'DEMO-02',name:'บัญชีผู้บริหารเขต',role:'regional',region:10,active:true},
 {id:'DEMO-03',name:'บัญชีผู้ประสานจังหวัด',role:'provincial',region:10,active:true},
 {id:'DEMO-04',name:'บัญชี รพ.สต.',role:'primary',region:10,active:true},
 {id:'DEMO-05',name:'บัญชีโรงพยาบาล',role:'hospital',region:10,active:true},
 {id:'DEMO-06',name:'บัญชีผู้ดูแลสิทธิ์',role:'administrator',active:true}
];
let adminAccount='DEMO-01', adminView='overview', adminRegion=overviewData?.region?String(overviewData.region):'', adminPeriod=overviewSelectedMonth, adminYear=overviewSelectedYear, adminMessage='';
// Synthetic monthly aggregates for chart and report testing.
const demoRegionVolumes = [4150, 2360, 2840, 5460, 3660, 4710, 3380, 3140, 3820, 3020, 3440, 2880, 6050];
const demoMonthFactors = [.76, .94, .85, 1.04, .97, 1.12];
function createDemoAggregate(region,period,index,baseline){
 const variation=((region*7+index*11)%13-6)/100;
 const screened=Math.round(baseline*(demoMonthFactors[index%demoMonthFactors.length]+variation)*(1+index*.009));
 const unknown=Math.round(screened*(.035+((region+index)%5)*.009));
 const medium=Math.round(screened*(.19+((region*3+index)%7)*.015));
 const high=Math.round(screened*(.09+((region+index*2)%6)*.015));
 const referred=Math.round(high*(.61+((region+index)%5)*.06));
 const tested=Math.round(referred*(.59+((region*2+index)%5)*.065));
 return {region,period,screened,high,referred,tested,unknown,medium,low:screened-high-unknown-medium};
}
const demoAggregates = liveOverview?[]:adminRegions.flatMap(region=>adminPeriods.map((period,index)=>
 createDemoAggregate(region,period,index,demoRegionVolumes[region-1])
));
const liveOverviewRows=liveOverview?overviewData.rows.map(row=>({...row,period:overviewPeriodLabel(row.period)})):[];
const demoProvinceAggregates = adminPeriods.map((period,index)=>createDemoAggregate(10,period,index,110));
const adminAccountInfo=()=>accounts.find(account=>account.id===adminAccount&&account.active);
function adminScope(){
 const account=adminAccountInfo();
 if(!account)return null;
 const role=roles[account.role];
 return {...role,key:account.role,region:account.region??role.region};
}
const canReport=scope=>scope&&['central','regional','provincial'].includes(scope.key);
function reportRows(scope,period=adminPeriod){
 if(!canReport(scope))return [];
 const dataset=liveOverview?liveOverviewRows:scope.key==='provincial'?demoProvinceAggregates:demoAggregates;
 return dataset.filter(row=>(scope.region==null?(!adminRegion||row.region===Number(adminRegion)):row.region===scope.region)&&(period?row.period===period:(!adminYear||demoPeriodYear(row.period)===adminYear)));
}
const countLabel=value=>value.toLocaleString('th-TH');
const scopeLabel=scope=>scope.key==='provincial'?'จังหวัดอุบลราชธานี':scope.facility??(scope.region?regionLabel(scope.region):'ทั่วประเทศ');
const adminIcons={overview:'chart',reports:'history',queue:'hospital',access:'check',rollout:'history'};
const adminTabs={overview:'แดชบอร์ด',reports:'รายงาน',queue:'คิวประสานตรวจ',access:'จัดการสิทธิ์',rollout:'ความพร้อมทั่วประเทศ'};
function adminHeading(title,summary){
 return `<div class="admin-heading"><h1 class="title">${title}</h1><p>${summary}</p></div>`;
}
function adminTab(key){
 return `<button class="admin-tab" data-admin-view="${key}" aria-current="${adminView===key?'page':'false'}">${icon(adminIcons[key])}<span>${adminTabs[key]}</span></button>`;
}
const demoPeriodYear=period=>period.slice(-4);
const demoPeriodYears=[...new Set(adminPeriods.map(demoPeriodYear))].reverse();
const latestDemoPeriodForYear=year=>adminPeriods.filter(period=>demoPeriodYear(period)===year).at(-1)??'';
function demoPeriodFilters(id,selected,label='เดือน',year=demoPeriodYear(selected),allowAllMonths=false){
 return `<label class="admin-month-filter">ปี พ.ศ.<select id="${id}-year">
 <option value="">ทุกปี</option>${demoPeriodYears.map(item=>`<option value="${item}" ${item===year?'selected':''}>${item}</option>`).join('')}</select></label>
 <label class="admin-month-filter">${label}<select id="${id}" ${year?'':'disabled'}>
 ${allowAllMonths?'<option value="">ทั้งหมด</option>':year?'':'<option value="">ทุกเดือน</option>'}${adminPeriods.filter(period=>year&&demoPeriodYear(period)===year).map(period=>`<option value="${period}" ${period===selected?'selected':''}>${period.slice(0,-5)}</option>`).join('')}</select></label>`;
}
function adminMonthFilter(id,label='เดือน'){
 return demoPeriodFilters(id,adminPeriod,label,adminYear,true);
}
function adminFilters(scope){
 return `<div class="admin-filters no-print"><label>พื้นที่<select id="admin-region" ${scope.region?'disabled':''}>${scope.region?`<option>${scopeLabel(scope)}</option>`:`<option value="">ครบ 13 เขต</option>${adminRegions.map(region=>`<option value="${region}" ${String(region)===adminRegion?'selected':''}>${regionLabel(region)}</option>`).join('')}`}</select></label>
 ${adminMonthFilter('admin-period')}</div>`;
}
function adminCounter(value,suffix='',digits=0){
 const final=value.toLocaleString('th-TH',{maximumFractionDigits:digits})+suffix;
 return `<strong aria-label="${final}"><span aria-hidden="true" data-admin-count="${value}" data-suffix="${suffix}" data-digits="${digits}">${final}</span></strong>`;
}
function animateAdminCounters(){
 if(!window.requestAnimationFrame||!window.matchMedia)return;
 const reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
 if(reduced.matches)return;
 const counters=[...main.querySelectorAll('[data-admin-count]')].map(element=>({
  element,target:Number(element.dataset.adminCount),suffix:element.dataset.suffix,
  format:new Intl.NumberFormat('th-TH',{maximumFractionDigits:Number(element.dataset.digits)})
 })).filter(counter=>counter.target>0);
 if(!counters.length)return;
 const update=progress=>counters.forEach(({element,target,suffix,format})=>{
  element.textContent=format.format(target*progress)+suffix;
 });
 update(0);
 let start;
 function frame(time){
  if(!counters[0].element.isConnected)return;
  start??=time;
  const progress=reduced.matches?1:Math.min(1,(time-start)/680);
  update(1-(1-progress)**3);
  if(progress<1)window.requestAnimationFrame(frame);
 }
 window.requestAnimationFrame(frame);
}
let selectedOverviewMetric=null;
let overviewReportPage=1;
const overviewReportPageSize=7;
const overviewMetrics=[['screened','ครั้งที่ประเมิน','check'],['high','ครั้งที่ได้ผลเสี่ยงสูง','alert'],['referred','คำขอประสาน','phone'],['tested','ยืนยันการตรวจ','hospital']];
function adminStats(rows){
 const hosted=Boolean(document.getElementById('hosted-sample-dashboard'));
 return `<div class="admin-kpis">${overviewMetrics.map(([key,label,symbol])=>{
  const content=`${box(symbol)}<div class="admin-kpi-copy"><span>${label}</span>${adminCounter(metricTotal(rows,key))}</div>`;
  return hosted?`<button type="button" class="card staff-detail-card staff-kpi-link" data-overview-metric="${key}" aria-pressed="${selectedOverviewMetric===key}">${content}${icon('next')}</button>`:`<section class="card">${content}</section>`;
 }).join('')}</div>`;
}
function reportTableRows(rows,scope){
 if(!rows.length||scope.key!=='central'||adminRegion)return [...rows].sort((left,right)=>left.region-right.region||adminPeriods.indexOf(left.period)-adminPeriods.indexOf(right.period));
 const period=adminPeriod||(adminYear?'ทุกเดือน ปี '+adminYear:'ทุกเดือน ทุกปี');
 const totals=new Map(adminRegions.map(region=>[region,{region,period,screened:0,high:0,referred:0,tested:0}]));
 for(const row of rows){
  const total=totals.get(row.region);
  if(!total)continue;
  total.screened+=row.screened;
  total.high+=row.high;
  total.referred+=row.referred;
  total.tested+=row.tested;
 }
 return [...totals.values()];
}
function adminTable(rows,scope,selectedMetric=null){
 const periodLabel=period=>period.replace(/\s+(?:ปี )?(?:\d{4}|ทุกปี)$/,'');
 const columns=[['screened','ประเมิน'],['high','เสี่ยงสูง'],['referred','ขอประสาน'],['tested','ตรวจแล้ว']];
 const metricClass=key=>key===selectedMetric?' class="overview-metric-column"':'';
 return `<div class="tablewrap"><table class="admin-table"><thead><tr><th scope="col">พื้นที่</th><th scope="col">ช่วงเวลา</th>${columns.map(([key,label])=>`<th scope="col"${metricClass(key)}>${label}</th>`).join('')}</tr></thead>
 <tbody>${rows.length?rows.map(row=>`<tr><th scope="row">${scope.key==='provincial'?scopeLabel(scope):regionLabel(row.region)}</th><td>${periodLabel(row.period)}</td>${columns.map(([key])=>`<td${metricClass(key)}>${row[key]}</td>`).join('')}</tr>`).join(''):'<tr><td colspan="6">ยังไม่มีข้อมูลในช่วงที่เลือก</td></tr>'}</tbody></table></div>`;
}
function printDemoReport(scope){
 const report=window.open('','_blank');
 if(!report)return;
 report.opener=null;
 const sheet=new URL('report-print.css?v=7',location.href).href;
 const pdfScript=new URL('report-pdf/download.js?v=4',location.href).href;
 const logo=new URL('portal-heart-clear.png',location.href).href;
 const area=scope.region?scopeLabel(scope):adminRegion?regionLabel(Number(adminRegion)):'ครบ 13 เขต';
 const issued=new Intl.DateTimeFormat('th-TH',{dateStyle:'long',timeStyle:'short',timeZone:'Asia/Bangkok'}).format(new Date());
 report.document.write(`<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>รายงานการคัดกรองและติดตาม · Anemia Portal</title><link rel="stylesheet" href="${sheet}"></head><body class="report-landscape">
 <div class="report-print-toolbar">
 <button type="button" aria-label="พิมพ์รายงาน" title="พิมพ์รายงาน" onclick="document.fonts.ready.then(()=>window.print())"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8V3h12v5M6 17H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6zM6 11h1"/></svg></button>
 <button type="button" aria-label="บันทึก PDF" title="บันทึก PDF" data-report-pdf><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM13 2v7h7M12 12v6m-3-3 3 3 3-3"/></svg></button>
 <p id="report-pdf-hint" role="status" hidden></p>
</div>
 <main class="report-paper"><header class="report-paper-header">
 <h1>รายงานการคัดกรองและติดตาม</h1><p>พื้นที่ ${area} · ช่วงเวลา ${adminPeriod||(adminYear?'ทุกเดือน ปี '+adminYear:'ทุกเดือน')}</p>
 <p>แหล่งข้อมูล ${liveOverview?'ข้อมูลจากระบบ':'ข้อมูลจำลอง'} · จัดทำ ${issued} น.</p></header>
 ${adminTable(reportTableRows(reportRows(scope),scope),scope)}
 <p class="report-note">ยอดประเมินและเสี่ยงสูงนับเป็นครั้ง ส่วนคำขอประสานและตรวจแล้วนับจากรายการส่งต่อ</p>
 <footer class="report-paper-footer">
  <img src="${logo}" alt="" width="29" height="29">
  <div><p>Anemia Portal</p><p>ศูนย์อนามัยที่ ๑๐ จังหวัดอุบลราชธานี</p></div>
 </footer>
 </main><script src="${pdfScript}"></script></body></html>`);
 report.document.close();
}
function metricTotal(rows,key){
 return rows.reduce((total,row)=>total+row[key],0);
}
const donutRadius=47;
function donutPoint(percent){
 const angle=percent*Math.PI/50;
 return [60+donutRadius*Math.sin(angle),60-donutRadius*Math.cos(angle)];
}
function donutArc({offset,width}){
 const start=donutPoint(offset);
 const arcTo=(fraction,large)=>`A ${donutRadius} ${donutRadius} 0 ${large} 1 ${donutPoint(fraction).join(' ')}`;
 return `M ${start.join(' ')} ${width>=100?arcTo(offset+50,0)+' '+arcTo(offset+100,0):arcTo(offset+width,Number(width>50))}`;
}
function donutCallouts(segments,total,attributes,formatCount){
 const rails=[[],[]];
 for(const segment of segments){
  const [ringX,ringY]=donutPoint(segment.offset+segment.width/2);
  const x=ringX-60, y=ringY-60;
  rails[segment.width>0?Number(x>=0):Number(rails[0].length>rails[1].length)].push({segment,x,y});
 }
 const lines=[], labels=[];
 rails.forEach((rail,side)=>{
  rail.sort((a,b)=>a.y-b.y);
  rail.forEach(({segment,x,y},index)=>{
   const lineY=rail.length===1?115:48+134*index/(rail.length-1);
   const edgeX=side?351:129, elbowX=side?337:143;
   if(segment.width>0){
    const pointX=240+1.6*x, pointY=115+1.6*y;
    lines.push(`<g data-donut-leader="${segment.key}" fill="${segment.color}" stroke="${segment.color}"><polyline points="${pointX},${pointY} ${elbowX},${lineY} ${edgeX},${lineY}" fill="none" stroke-width="1"/><circle cx="${pointX}" cy="${pointY}" r="2"/></g>`);
   }
   labels.push(`<button type="button" class="donut-callout side-${side}" style="--label-y:${lineY/2.3}%;--callout-tone:${segment.color}" aria-pressed="false" ${attributes(segment)}><span>${segment.label}</span><strong>${formatCount(segment.count)}</strong> <small>${percent(segment.count,total)}</small></button>`);
  });
 });
 return `<svg class="donut-leaders" viewBox="0 0 480 230" aria-hidden="true">${lines.join('')}</svg>${labels.join('')}`;
}
function riskComposition(rows){
 const groups=[['low','เสี่ยงต่ำ',riskChartColors.low],['medium','เสี่ยงปานกลาง',riskChartColors.medium],['high','เสี่ยงสูง',riskChartColors.high],['unknown','ยังสรุประดับไม่ได้',riskChartColors.unknown]];
 const total=metricTotal(rows,'screened');
 let offset=0;
 const segments=groups.map(([key,label,color])=>{
  const count=metricTotal(rows,key);
  const width=total?count/total*100:0;
  const segment={label,color,count,width,offset};
  offset+=width;
  return {...segment,key};
 });
 return `<section class="card admin-chart-card"><div class="admin-chart-title">${box('check')}<div><h2>ผลประเมินแยกตามระดับความเสี่ยง</h2><p>สัดส่วนจากแบบประเมินทั้งหมด รวมผลที่ยังสรุประดับไม่ได้</p></div></div>
 <div class="admin-risk-layout"><div class="donut-callout-stage"><div class="admin-risk-donut"><svg viewBox="0 0 120 120" role="group" aria-label="สัดส่วนผลประเมิน"><circle cx="60" cy="60" r="${donutRadius}" fill="none" stroke="#eeeaf6" stroke-width="16"/>
 ${segments.filter(segment=>segment.width>0).map(({key,label,color,count,width,offset})=>`<path d="${donutArc({offset,width})}" fill="none" stroke="${color}" stroke-width="16" role="button" tabindex="0" aria-pressed="false" data-admin-risk="${key}" data-risk-tooltip="${label} · ${countLabel(count)} ครั้ง · ${percent(count,total)}" aria-label="${label} ${countLabel(count)} ครั้ง ${percent(count,total)}"/>`).join('')}</svg><div class="admin-ring-copy">${adminCounter(total)}<span>ครั้งที่ประเมิน</span></div></div>
 ${donutCallouts(segments,total,({key,label,count})=>`data-admin-risk="${key}" data-risk-tooltip="${label} · ${countLabel(count)} ครั้ง · ${percent(count,total)}"`,countLabel)}</div>
 <div id="admin-risk-tooltip" class="admin-risk-tooltip" role="status" hidden></div></div></section>`;
}
let adminRiskTimer=null, selectedAdminRisk=null;
function showAdminRisk(target){
 const tip=document.querySelector('#admin-risk-tooltip');
 if(!tip)return;
 for(const node of document.querySelectorAll('[data-admin-risk]')){
  node.classList.toggle('risk-highlight',Boolean(target&&node.dataset.adminRisk===target.dataset.adminRisk));
  node.setAttribute('aria-pressed',String(Boolean(selectedAdminRisk&&node.dataset.adminRisk===selectedAdminRisk.dataset.adminRisk)));
 }
 tip.hidden=!target;
 tip.textContent=target?.dataset.riskTooltip??'';
}
function pickAdminRisk(target){
 clearTimeout(adminRiskTimer);
 selectedAdminRisk=selectedAdminRisk?.dataset.adminRisk===target.dataset.adminRisk?null:target;
 showAdminRisk(selectedAdminRisk);
}
for(const [eventName,enter] of [['pointerover',true],['pointerout',false],['focusin',true],['focusout',false]]){
 document.addEventListener(eventName,event=>{
  const target=event.target.closest?.('[data-admin-risk]');
  if(!target||target.contains(event.relatedTarget))return;
  clearTimeout(adminRiskTimer);
  if(eventName==='pointerover')adminRiskTimer=setTimeout(()=>{if(target.isConnected)showAdminRisk(target);},180);
  else showAdminRisk(enter?target:selectedAdminRisk);
 });
}
document.addEventListener('keydown',event=>{
 const target=event.target.closest?.('path[data-admin-risk]');
 if(target&&(event.key==='Enter'||event.key===' ')){event.preventDefault();pickAdminRisk(target);}
});
function monthlyTrend(months){
 const maximum=Math.max(1,...months.map(month=>month.screened));
 const top=Math.ceil(maximum/4/10)*40;
 const points=months.map(({period,screened},index)=>({
  period,screened,selected:index===months.length-1,x:66+index*352/(months.length-1),y:151-screened/top*116
 }));
 const line=points.map(({x,y,screened},index)=>screened===null?'':`${index&&points[index-1].screened!==null?'L':'M'}${x} ${y}`).join(' ');
 const area=points.every(point=>point.screened!==null)?`${points[0].x},151 ${points.map(point=>point.x+','+point.y).join(' ')} ${points.at(-1).x},151`:'';
 return `<section class="card admin-chart-card"><div class="admin-chart-title">${box('chart')}<div><h2>แนวโน้มการประเมินรายเดือน</h2><p>จำนวนครั้งที่ประเมินเสร็จในเดือนที่เลือกและ 3 เดือนก่อนหน้า</p></div></div>
 <svg class="admin-trend" viewBox="0 0 465 166" aria-hidden="true">
 <defs><linearGradient id="admin-trend-fill" x2="0" y2="100%" gradientUnits="objectBoundingBox"><stop stop-color="#8c6dd0" stop-opacity=".3"/><stop offset="100%" stop-color="#8c6dd0" stop-opacity=".04"/></linearGradient></defs>
 ${[0,1,2,3,4].map(tick=>`<path d="M57 ${151-tick*29}H430" stroke="#ece5f3"/><text x="50" y="${155-tick*29}" text-anchor="end">${countLabel(top*tick/4)}</text>`).join('')}
 ${area?`<polygon points="${area}" fill="url(#admin-trend-fill)"/>`:''}
 <path class="admin-trend-line" d="${line}" fill="none" stroke="#7148b0" stroke-width="3" pathLength="100"/>
 ${points.filter(point=>point.screened!==null).map(({period,x,y,screened,selected})=>`<circle cx="${x}" cy="${y}" r="${selected?7:5}" fill="${selected?'#7148b0':'#f2b544'}" stroke="#fff" stroke-width="2"><title>${period} · ${countLabel(screened)} ครั้ง</title></circle><text class="trend-value" x="${x}" y="${y-11}" text-anchor="middle">${countLabel(screened)}</text>`).join('')}</svg>
 <ul class="admin-trend-labels">${points.map(point=>`<li ${point.selected?'aria-current="date"':''}>${point.period}<span class="${point.screened===null?'':'trend-readable'}"> ${point.screened===null?'ยังไม่มีข้อมูล':countLabel(point.screened)+' ครั้ง'}</span></li>`).join('')}</ul>
 ${adminPeriod?'':'<p class="notice">แสดง 4 เดือนล่าสุดในชุดข้อมูล</p>'}</section>`;
}
function adminChartMonths(scope){
 const end=adminPeriod||latestDemoPeriodForYear(adminYear)||latestAdminPeriod;
 const endMonth=demoMonthNames.indexOf(end.slice(0,-5));
 const endYear=Number(demoPeriodYear(end))-543;
 return [-3,-2,-1,0].map(offset=>{
  const date=new Date(Date.UTC(endYear,endMonth+offset,1));
  const period=demoMonthNames[date.getUTCMonth()]+' '+(date.getUTCFullYear()+543);
  const rows=reportRows(scope,period);
  const screened=metricTotal(rows,'screened');
  return {period,screened:screened||null,high:screened?metricTotal(rows,'high'):null};
 });
}
function regionMapPanel(scope){
 const year=adminYear||demoPeriodYear(latestAdminPeriod);
 const rows=reportRows(scope,'').filter(row=>demoPeriodYear(row.period)===year);
 const selected=scope.region??(adminRegion?Number(adminRegion):null);
 const interactive=scope.key==='central';
 const hues=['#ab90d1','#9f88c7','#c7a9d9','#d4abd0','#dab9d4','#c596c1','#9e87cd','#8576c2','#ba99d4','#a984c6','#b3a1da','#9b8cc9','#b45383'];
 const shapes=healthRegionMap.map(({region,path,anchor:[x,y]})=>{
  const active=region===selected;
  const content=`<path d="${path}" fill-rule="evenodd" fill="${hues[region-1]}"/><text x="${x}" y="${y}" aria-hidden="true">${region}</text>`;
  return interactive?`<g data-chart-region="${region}" data-map-shape="true" role="button" tabindex="0" aria-label="${regionLabel(region)}" aria-pressed="${active}"><title>${regionLabel(region)}</title>${content}</g>`:`<g class="${active?'map-selected':''}">${content}</g>`;
 }).join('');
 return `<section class="card admin-chart-card admin-region-map">
 <div class="admin-chart-title">${box('hospital')}<div><h2>แผนที่เขตสุขภาพ</h2><p>${interactive?'คลิกพื้นที่หรือเลือกเลขเขตเพื่อกรองแดชบอร์ด':'แสดงตำแหน่งเขตที่บัญชีนี้รับผิดชอบ'}</p></div></div>
 <div class="region-map-layout">
 <div class="region-map-stage"><svg viewBox="0 0 430 700" ${interactive?'role="group"':'role="img"'} aria-label="แผนที่ประเทศไทย 13 เขตสุขภาพ"><defs><filter id="region-map-shadow" x="-15%" y="-10%" width="130%" height="130%"><feDropShadow dx="0" dy="4" stdDeviation="2" flood-color="#4d3069" flood-opacity=".2"/></filter></defs><g class="region-map-shapes" filter="url(#region-map-shadow)">${shapes}</g></svg></div>
 <div class="region-map-controls"><h3>${scope.key==='provincial'?scopeLabel(scope):selected?regionLabel(selected):'ทุกเขตสุขภาพ'}</h3>
 <p class="region-map-period">ภาพรวมปี พ.ศ. ${year} · รวมทุกเดือนที่มีข้อมูล</p>
 ${interactive?`<div class="region-map-picker" role="group" aria-label="เลือกเขตสุขภาพ">${adminRegions.map(region=>`<button type="button" data-chart-region="${region}" aria-label="${regionLabel(region)}" aria-pressed="${region===selected}">${region}</button>`).join('')}</div>
 ${selected?button('ดูครบทุกเขต','admin-clear-region','refresh'):''}`:''}
 <dl class="region-map-metrics" aria-label="สรุปผลการดำเนินงานของพื้นที่">
 ${[['screened','ประเมิน','check','ครั้ง'],['high','เสี่ยงสูง','alert','ครั้ง'],
    ['referred','ขอประสาน','phone','คำขอ'],['tested','ตรวจแล้ว','hospital','คำขอ']].map(([key,label,symbol,unit])=>
   `<div class="region-map-metric">${box(symbol)}<dt>${label}</dt><dd><strong>${rows.length?countLabel(metricTotal(rows,key)):'—'}</strong> <span>${unit}</span></dd></div>`).join('')}
 </dl>
 ${rows.length?'':'<p class="notice">ยังไม่มีข้อมูลสำหรับพื้นที่และช่วงเวลาที่เลือก</p>'}
 </div>
 </div></section>`;
}
document.addEventListener('keydown',event=>{
 const shape=event.target.closest?.('[data-map-shape]');
 if(!shape||(event.key!=='Enter'&&event.key!==' '))return;
 event.preventDefault();
 selectChartRegion(Number(shape.dataset.chartRegion));
});
function adminCharts(rows,scope){
 const months=adminChartMonths(scope);
 const maximum=Math.max(1,...months.map(month=>month.screened));
 const requests=metricTotal(rows,'referred'), completed=metricTotal(rows,'tested');
 const completion=requests?completed/requests*100:0;
 const caption=requests?percent(completed,requests):'—';
 const chartRegions=scope.key==='central'&&!adminRegion?adminRegions:[...new Set(rows.map(row=>row.region))];
 const areaTotals=chartRegions.map(region=>({
  region,count:metricTotal(rows.filter(row=>row.region===region),'screened')
 }));
 const areaMax=Math.max(1,...areaTotals.map(area=>area.count));
 return `<div class="admin-visuals">
 <section class="card admin-chart-card">
  <div class="admin-chart-title">${box('chart')}<div><h2>ผลประเมินรายเดือน</h2><p>ยอดประเมินทั้งหมดและผลเสี่ยงสูง ${adminPeriod?'ในเดือนที่เลือกและ 3 เดือนก่อนหน้า':'ใน 4 เดือนล่าสุดของชุดข้อมูล'}</p></div></div>
  <div class="admin-legend"><span class="legend-screened">ประเมินทั้งหมด</span><span class="legend-high">ผลเสี่ยงสูง</span></div>
  <div class="admin-month-chart" style="--months:${months.length||1};--chart-min-width:${months.length*74}px">
   ${months.map(({period,screened,high},index)=>`<div class="admin-month" ${index===months.length-1?'aria-current="date"':''} style="--month-delay:${index*110}ms">
    <div class="admin-column-group">
     ${screened===null?'<span class="admin-month-empty">ยังไม่มีข้อมูล</span>':`
     <div class="admin-column"><strong>${countLabel(screened)}</strong><span class="admin-column-bar screened" style="--bar-height:${screened/maximum*100}%"></span></div>
     <div class="admin-column"><strong>${countLabel(high)}</strong><span class="admin-column-bar high" style="--bar-height:${high/maximum*100}%"></span></div>`}
    </div><small>${period}</small></div>`).join('')}
  </div>
 </section>
 <section class="card admin-chart-card">
  <div class="admin-chart-title">${box('hospital')}<div><h2>ยืนยันว่าได้รับการตรวจ</h2><p>เทียบกับจำนวนคำขอประสานทั้งหมด</p></div></div>
  <div class="admin-ring" style="--completion:${completion}">
   <svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="48"/><circle class="admin-ring-value" cx="60" cy="60" r="48" pathLength="100"/></svg>
   <div class="admin-ring-copy">${requests?adminCounter(completion,'%',1):`<strong>${caption}</strong>`}<span>ตรวจแล้วจากคำขอ</span></div>
  </div>
  <div class="admin-ring-caption"><span>ตรวจแล้ว <strong>${countLabel(completed)}</strong></span><span>ขอประสาน <strong>${countLabel(requests)}</strong></span></div>
 </section>
 ${riskComposition(rows)}
 ${monthlyTrend(months)}
 ${scope.key==='central'&&areaTotals.length>1?`<section class="card admin-chart-card admin-regions">
  <div class="admin-chart-title">${box('history')}<div><h2>เปรียบเทียบจำนวนครั้งตามเขต</h2><p>เลือกแถบเพื่อดูรายละเอียดของเขตนั้น</p></div></div>
  <div class="admin-region-chart">${areaTotals.map(({region,count},index)=>`<button type="button" class="admin-region-bar" data-chart-region="${region}" style="--region-width:${count/areaMax*100}%;--region-delay:${index*40}ms">
   <span>${regionLabel(region)}</span><span class="admin-region-track" aria-hidden="true"><i></i></span><strong>${countLabel(count)}</strong></button>`).join('')}</div>
 </section>`:''}
 </div>`;
}
function selectChartRegion(region){
 if(adminScope()?.key!=='central'||!adminRegions.includes(region))return;
 adminRegion=String(region);
 overviewReportPage=1;
 staff(true);
 document.querySelector('#admin-region')?.focus({preventScroll:true});
}
function reportView(scope,showHeading=true){
 const rows=reportRows(scope);
 const tableRows=reportTableRows(rows,scope);
 const paginated=!showHeading;
 const pageCount=Math.max(1,Math.ceil(tableRows.length/overviewReportPageSize));
 overviewReportPage=Math.min(overviewReportPage,pageCount);
 const offset=(overviewReportPage-1)*overviewReportPageSize;
 const visibleRows=paginated?tableRows.slice(offset,offset+overviewReportPageSize):tableRows;
 const exporting=adminView==='reports';
 const metricLabel=exporting?overviewMetrics.find(([key])=>key===selectedOverviewMetric)?.[1]:null;
 const area=scope.region?scopeLabel(scope):adminRegion?regionLabel(Number(adminRegion)):'ครบ 13 เขต';
 return `${showHeading?adminHeading(exporting?'รายงานการคัดกรองและติดตาม':'แดชบอร์ดการคัดกรองและติดตาม','ข้อมูลจำลองสำหรับสาธิตกระบวนการรายงาน'):''}
 <div class="admin-report-toolbar"><p class="admin-report-meta">${icon('calendar')} <span>${area} · ${adminPeriod||(adminYear?'ทุกเดือน ปี '+adminYear:'ทุกเดือน')}</span></p>${adminFilters(scope)}</div>
 ${adminStats(rows)}
 ${exporting?'':`${adminCharts(rows,scope)}<div class="admin-map-slot">${regionMapPanel(scope)}</div>`}
 <section class="card admin-report"><div class="admin-report-top"><h2>${metricLabel|| (exporting?'รายละเอียดตามพื้นที่และเดือน':'ผลการดำเนินงานรายพื้นที่')}</h2>
 <div class="admin-report-controls no-print">${adminMonthFilter('admin-table-period','เลือกเดือน')}
 ${exporting?`<div class="actions no-print">${button('ดาวน์โหลด CSV','admin-csv','download')}${button('พิมพ์ / บันทึก PDF','admin-print','history',true)}</div>`:button('เปิดรายงาน','admin-reports','history')}</div></div>
 ${adminTable(visibleRows,scope,exporting?selectedOverviewMetric:null)}
 ${paginated&&pageCount>1?`<nav class="overview-report-pages no-print" aria-label="แบ่งหน้ารายงาน">
  <span role="status">แสดง ${offset+1}–${offset+visibleRows.length} จาก ${tableRows.length} รายการ</span>
  <button type="button" class="action" data-overview-page="-1" ${overviewReportPage===1?'disabled':''}>ก่อนหน้า</button>
  <span>หน้า ${overviewReportPage} / ${pageCount}</span>
  <button type="button" class="action" data-overview-page="1" ${overviewReportPage===pageCount?'disabled':''}>ถัดไป</button>
 </nav>`:''}
 <p class="notice">${liveOverview?'ยอดประเมินนับรอบแรกที่ระบุจังหวัด ยอดคำขอและการตรวจนับรายการที่จับคู่กับทะเบียนหน่วยบริการ':'ยอดประเมินและเสี่ยงสูงนับเป็นครั้ง ส่วนคำขอประสานและตรวจแล้วนับจากรายการส่งต่อ'}</p></section>`;
}
const canUpdateQueue=scope=>!!(scope?.facility&&facilityDirectory.some(site=>site.id===requestFacilityId&&site.name===scope.facility));
function queueView(scope){
 const visible=canUpdateQueue(scope);
 return `${adminHeading('คิวประสานตรวจ',scopeLabel(scope))}
 <div class="admin-kpis"><section class="card">${box('phone')}<span>คำขอที่เห็นในเดโม่</span><strong>${visible?1:0}</strong></section></div>
 <section class="card admin-report">${visible?`<h2>AN-001 · ${request}</h2><p class="status"><strong>${stageNames[stage]}</strong></p><div class="actions">${stage<2?button(stage===0?'รับเรื่อง':'ยืนยันการตรวจจำลอง','advance','check',true):''}${button('ดูสถานะฝั่งประชาชน','track','arrow')}${button('ส่งออกคำขอตัวอย่าง','export','download')}</div>`:'<h2>ยังไม่มีคำขอของหน่วยนี้</h2><p>คำขอที่ส่งจากหน้าผลจะเข้าคิวของหน่วยบริการที่ผู้ใช้เลือก</p>'}</section>`;
}
function accountOptions(values,selected){
 return values.map(([value,label])=>`<option value="${value}" ${String(value)===String(selected)?'selected':''}>${label}</option>`).join('');
}
function accessView(){
 return `${adminHeading('จัดการสิทธิ์เจ้าหน้าที่','กำหนดบทบาทและขอบเขตเขตให้บัญชีตัวอย่าง')}
 ${adminMessage?`<p class="status" role="status">${adminMessage}</p>`:''}
 <div class="admin-accounts">${accounts.map(account=>{
  const locked=account.id===adminAccount;
  return `<form class="card admin-account" data-admin-account="${account.id}"><h2>${account.name}</h2><p>${account.id}${locked?' · บัญชีที่กำลังใช้งาน':''}</p>
  <label>บทบาท<select name="role" ${locked?'disabled':''}>${accountOptions(Object.entries(roles).map(([key,role])=>[key,role.label]),account.role)}</select></label>
  <label>เขตที่รับผิดชอบ<select name="region" ${locked?'disabled':''}>${accountOptions(adminRegions.map(region=>[region,regionLabel(region)]),account.region??10)}</select></label>
  <label class="admin-check"><input name="active" type="checkbox" ${account.active?'checked':''} ${locked?'disabled':''}> เปิดให้เข้าใช้งานในเดโม่</label>
  ${locked?'': `<button class="action primary" type="submit">${icon('check')}บันทึกสิทธิ์</button>`}
  </form>`;
 }).join('')}</div>`;
}
const readinessKey='anemia-national-readiness-v1';
let rolloutRegion='',rolloutQuery='',rolloutMessage='';
let provinceReadiness={};
try{provinceReadiness=JSON.parse(window.localStorage?.getItem(readinessKey)||'{}')??{};}catch{}
function provinceRollout(){
 const rows=nationalProvinces.filter(([,name,region])=>(!rolloutRegion||String(region)===rolloutRegion)&&name.includes(rolloutQuery));
 const confirmed=nationalProvinces.filter(([code])=>provinceReadiness[code]===true).length;
 return `${adminHeading('ความพร้อมทั่วประเทศ','ทะเบียน 13 เขตสุขภาพ · 77 จังหวัด')}
 <p class="notice">สถานะในหน้านี้ใช้เตรียมแผนและบันทึกบนเครื่องที่เปิดเดโม่ การระบุว่าพร้อมนำร่องไม่เปิดบริการสาธารณะ</p>
 ${rolloutMessage?`<p class="status" role="status">${rolloutMessage}</p>`:''}
 <div class="admin-kpis"><section class="card"><span>จังหวัดในทะเบียน</span><strong>${nationalProvinces.length}</strong></section>
 <section class="card"><span>ระบุว่าพร้อมนำร่องในแผน</span><strong>${confirmed}</strong></section>
 <section class="card"><span>อยู่ระหว่างเตรียม</span><strong>${nationalProvinces.length-confirmed}</strong></section></div>
 <form id="rollout-filter" class="admin-filters"><label>เขตสุขภาพ<select name="region"><option value="">ทุกเขต</option>${accountOptions(adminRegions.map(region=>[region,regionLabel(region)]),rolloutRegion)}</select></label>
 <label>ชื่อจังหวัด<input name="query" type="search" placeholder="ค้นหาจังหวัด" maxlength="40"></label><button class="action" type="submit">${icon('chart')}กรองรายการ</button></form>
 <div class="tablewrap"><table class="admin-table"><thead><tr><th>จังหวัด</th><th>เขต</th><th>สถานะเตรียมงาน</th><th>จัดการ</th></tr></thead><tbody>
 ${rows.map(([code,name,region])=>`<tr><th scope="row">${name}</th><td>${region}</td><td>${provinceReadiness[code]===true?'พร้อมนำร่องในแผน':'รอเตรียม'}</td><td><button class="action" data-rollout-code="${code}">${icon(provinceReadiness[code]===true?'refresh':'check')}${provinceReadiness[code]===true?'ย้ายกลับไปรอเตรียม':'ระบุว่าพร้อมนำร่อง'}</button></td></tr>`).join('')||'<tr><td colspan="4">ไม่พบจังหวัดตามตัวกรอง</td></tr>'}
 </tbody></table></div>
 <p class="notice">รหัสจังหวัดและขอบเขตแผนที่จาก <a href="https://github.com/GeoThai/boundaries/tree/v2026.10.23">GeoThai/boundaries</a> · <a href="https://creativecommons.org/licenses/by/3.0/igo/">CC BY 3.0 IGO</a> ขอบเขตถูกรวมตามเขตสุขภาพและย่อรายละเอียดเพื่อแสดงผล · การจัดเขตอิง <a href="https://healthkpi.moph.go.th/kpi/kpi_template/2568/025.1.pdf">กระทรวงสาธารณสุข</a></p>`;
}
function toggleProvinceReadiness(code){
 if(adminScope()?.key!=='administrator'||!nationalProvinces.some(row=>row[0]===code))return;
 provinceReadiness[code]=provinceReadiness[code]!==true;
 rolloutMessage='อัปเดตสถานะในแผนแล้ว';
 try{window.localStorage?.setItem(readinessKey,JSON.stringify(provinceReadiness));}
 catch{rolloutMessage='อัปเดตสถานะในรอบนี้แล้ว เบราว์เซอร์ไม่อนุญาตให้บันทึกไว้หลังปิดหน้า';}
 staff();
 document.querySelector(`[data-rollout-code="${code}"]`)?.focus();
}
document.addEventListener('submit',event=>{
 if(event.target.id!=='rollout-filter')return;
 event.preventDefault();
 if(adminScope()?.key!=='administrator')return;
 rolloutRegion=event.target.elements.region.value;
 rolloutQuery=event.target.elements.query.value.trim();
 rolloutMessage='';
 staff();
});
function staff(preserveScroll=false){
 clearTimeout(adminRiskTimer);selectedAdminRisk=null;
 const scope=adminScope();
 if(!scope){adminAccount='DEMO-06';staff();return;}
 if(typeof renderHostedSample==='function'&&document.querySelector('#hosted-sample-dashboard')){renderHostedSample(scope);return;}
 const tabs=scope.key==='administrator'?['access','rollout']:scope.facility?['queue']:['overview','reports'];
 if(!tabs.includes(adminView))adminView=tabs[0];
 set(panel(`<div class="admin-shell">${backRow('home')}
 <div class="admin-top no-print">
  <div class="admin-top-main">
   <div class="admin-brand">${box('hospital')}<div><strong>ศูนย์จัดการ Anemia Portal</strong>
    <div class="admin-top-meta"><span>${scope.label}</span>${scope.key==='administrator'?'':`<span>${scopeLabel(scope)}</span>`}</div>
   </div></div>
   <label class="admin-account-picker"><span>บัญชีสาธิต</span><select id="admin-account">${accountOptions(accounts.filter(account=>account.active).map(account=>[account.id,account.name]),adminAccount)}</select></label>
  </div>
  <nav class="admin-top-tabs" aria-label="เมนูเจ้าหน้าที่">${tabs.map(adminTab).join('')}</nav>
 </div>
 <div class="admin-content">${canReport(scope)?reportView(scope):scope.facility?queueView(scope):adminView==='rollout'?provinceRollout():accessView()}</div></div>`,true),undefined,preserveScroll);
 if(canReport(scope)&&adminView==='overview')animateAdminCounters();
 const query=document.querySelector('#rollout-filter input');if(query)query.value=rolloutQuery;
}
function adminExport(){
 const scope=adminScope();
 if(!canReport(scope))return;
 const rows=reportTableRows(reportRows(scope),scope);
 const lines=['พื้นที่,ช่วงเวลา,ประเมิน,เสี่ยงสูง,ขอประสาน,ตรวจแล้ว,ประเภทข้อมูล',...rows.map(row=>[scope.key==='provincial'?scopeLabel(scope):regionLabel(row.region),row.period,row.screened,row.high,row.referred,row.tested,liveOverview?'จริง':'จำลอง'].join(','))];
 const href=URL.createObjectURL(new Blob(['\uFEFF'+lines.join('\r\n')],{type:'text/csv;charset=utf-8'}));
 const link=document.createElement('a');link.href=href;link.download=liveOverview?'anemia-regional-report.csv':'anemia-regional-demo-report.csv';link.click();
 setTimeout(()=>URL.revokeObjectURL(href),1000);
}
document.addEventListener('submit',event=>{
 const id=event.target.dataset?.adminAccount;
 if(!id)return;
 event.preventDefault();
 if(adminScope()?.key!=='administrator'||id===adminAccount)return;
 const account=accounts.find(entry=>entry.id===id);
 const {role,region,active}=event.target.elements;
 const next=roles[role.value], area=Number(region.value);
 if(!account||!next||!adminRegions.includes(area))return;
 account.role=role.value;
 account.region=role.value==='regional'?area:next.region;
 account.active=active.checked;
 adminMessage='บันทึกสิทธิ์ของบัญชีตัวอย่างแล้ว';
 staff();
});
document.addEventListener('change',event=>{
 const {id,value}=event.target;
 if(id==='admin-account'){
  if(!accounts.some(account=>account.id===value&&account.active))return;
  adminAccount=value;adminRegion='';adminPeriod='';adminYear=demoPeriodYear(latestAdminPeriod);adminMessage='';
 }else if(id==='admin-region'){adminRegion=value;overviewReportPage=1;}
 else if(id==='admin-period-year'||id==='admin-table-period-year'){adminYear=value;adminPeriod='';overviewReportPage=1;}
 else if(id==='admin-period'||id==='admin-table-period'){adminPeriod=value;overviewReportPage=1;}
 else return;
 staff();
 document.querySelector('#'+id)?.focus();
});
