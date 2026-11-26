const icons={drop:'<path d="M12 2s7 8 7 13a7 7 0 0 1-14 0c0-5 7-13 7-13Z"/><path d="M9 16a3 3 0 0 0 3 3"/>',check:'<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',book:'<path d="M12 6v15M3 4c4-1 7 0 9 2 2-2 5-3 9-2v15c-4-1-7 0-9 2-2-2-5-3-9-2z"/>',sound:'<path d="M3 9v6h4l5 4V5L7 9H3m13 0a4 4 0 0 1 0 6m3-9a8 8 0 0 1 0 12"/>',hospital:'<path d="M4 21V4h16v17M2 21h20M9 21v-5h6v5m-3-14v6M9 10h6"/>',refresh:'<path d="M20 7v5h-5M4 17v-5h5m-4-1a7 7 0 0 1 12-5l3 6M4 12l3 6a7 7 0 0 0 12-5"/>',alert:'<path d="M12 3 2 21h20L12 3Zm0 6v5m0 3v1"/>',phone:'<path d="M7 3H4a1 1 0 0 0-1 1c0 9 8 17 17 17a1 1 0 0 0 1-1v-3l-5-2-2 2a14 14 0 0 1-7-7l2-2z"/>',arrow:'<path d="M19 12H5m6 6-6-6 6-6"/>'};
Object.assign(icons,{
 person:'<circle cx="12" cy="7" r="3"/><path d="M5 21v-3a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v3"/>',
 share:'<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m9 10 6-4m-6 8 6 4"/>',
 student:'<path d="m2 9 10-5 10 5-10 5zM6 11v5c4 3 8 3 12 0v-5m4-2v7"/>',
 menu:'<path d="M4 6h16M4 12h16M4 18h16"/>',
 search:'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/>',
 close:'<path d="m6 6 12 12M18 6 6 18"/>',
 pause:'<path d="M9 5v14m6-14v14"/>',
 play:'<path d="m8 4 13 8-13 8z"/>',
 help:'<circle cx="12" cy="12" r="9"/><path d="M9 9a3 3 0 1 1 3 3v1m0 3h.01"/>',
 calendar:'<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 10h18m-13 5h2m4 0h2"/>',
 pulse:'<path d="M2 12h4l3-6 4 12 3-6h6"/><path d="M5 5a9 9 0 0 1 14 0M5 19a9 9 0 0 0 14 0"/>',
 history:'<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2m6 2V2M9 10h6m-6 5h6"/>',
 food:'<path d="M4 3v7a3 3 0 0 0 6 0V3M7 3v18m13-18c-4 1-5 5-5 9h5m0-9v18"/>',
 cup:'<path d="M4 9h13v4a6 6 0 0 1-6 6h-1a6 6 0 0 1-6-6V9Zm13 1h2a2 2 0 0 1 0 4h-2M3 21h16M8 3v3m5-3v3"/>',
 download:'<path d="M12 3v12m-4-4 4 4 4-4M4 16v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/>',
 chart:'<path d="M4 20h16M7 16v-4m5 4V8m5 8V4" stroke-width="2.4"/>',
 next:'<path d="m10 6 6 6-6 6"/>'
});
const illustrations={
 calendar:`<rect x="12" y="15" width="48" height="46" rx="11" fill="#b95083"/><rect x="10" y="12" width="48" height="45" rx="11" fill="#fff5fb"/><path d="M10 23a11 11 0 0 1 11-11h26a11 11 0 0 1 11 11v5H10z" fill="#e979a9"/><path d="M22 9v9m24-9v9" stroke="#823665" stroke-width="4" stroke-linecap="round"/><path d="M34 32s-7 8-7 12a7 7 0 0 0 14 0c0-4-7-12-7-12z" fill="#ce578a"/>`,
 pulse:`<path d="M36 60C24 51 9 41 9 26c0-18 19-22 27-9 8-13 27-9 27 9 0 15-15 25-27 34" fill="#a955a8"/><path d="M36 55C25 47 12 38 12 25c0-14 16-19 24-6 8-13 24-8 24 6 0 13-13 22-24 30" fill="#d87fc6"/><path d="M16 32h11l5-8 6 16 5-8h13" fill="none" stroke="#fff5fe" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>`,
 history:`<rect x="17" y="12" width="42" height="50" rx="9" fill="#5483a8"/><rect x="13" y="9" width="42" height="50" rx="9" fill="#f6fbff"/><path d="M25 8h18v10H25z" fill="#83afd4"/><rect x="19" y="25" width="30" height="14" rx="5" fill="#dceefa"/><path d="M34 28v8m-4-4h8M23 46h21m-21 6h15" stroke="#477ca8" stroke-width="2.5" stroke-linecap="round"/>`,
 food:`<circle cx="36" cy="37" r="27" fill="#91ada6"/><circle cx="36" cy="34" r="27" fill="#f8fff9"/><circle cx="36" cy="34" r="20" fill="#d8eee5"/><path d="M26 47c-9-14-2-23 13-24 1 12-3 22-13 24Z" fill="#498d75"/><path d="M36 46c1-13 8-18 17-16-2 11-8 17-17 16Z" fill="#77ae7d"/><circle cx="43" cy="23" r="7" fill="#eeb479"/>`,
 cup:`<ellipse cx="36" cy="55" rx="27" ry="8" fill="#d0ab9d"/><ellipse cx="36" cy="52" rx="27" ry="7" fill="#fff5ed"/><path d="M49 29h7a8 8 0 1 1 0 16h-7" fill="none" stroke="#a76756" stroke-width="5"/><path d="M15 27h37v12a14 14 0 0 1-14 14h-9a14 14 0 0 1-14-14z" fill="#dc917c"/><ellipse cx="33.5" cy="27" rx="18.5" ry="5.5" fill="#f2b49f"/><ellipse cx="33.5" cy="27" rx="14" ry="3.5" fill="#93564b"/><path d="M28 16c-4-4 4-5 0-10m11 10c-4-4 4-5 0-10" fill="none" stroke="#b8776b" stroke-width="2" stroke-linecap="round"/>`
};
const illustration=name=>`<svg viewBox="0 0 72 72" aria-hidden="true">${illustrations[name]}</svg>`;
const icon=name=>`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${icons[name]}</svg>`;
const button=(label,go,symbol,primary=false)=>`<button class="action ${primary?'primary':''}" data-go="${go}">${icon(symbol)}${label}</button>`;
const box=name=>`<span class="iconbox">${icon(name)}</span>`;
const main=document.querySelector('#main');
const levels={unknown:['คุณดูคำแนะนำต่อได้เลย','บางเรื่องยังไม่แน่ใจ จึงยังสรุประดับความเสี่ยงไม่ได้ หากนึกออก ค่อยเติมเฉพาะส่วนนั้นได้','book'],low:['ผลคัดกรองอยู่ในระดับความเสี่ยงต่ำ','คุณอ่านคำแนะนำเพื่อดูแลตัวเองได้ หากมีอาการหรือยังเป็นกังวล คุณปรึกษาหน่วยบริการได้','check'],medium:['ผลคัดกรองอยู่ในระดับความเสี่ยงปานกลาง','ลองอ่านหรือฟังคำแนะนำเพื่อดูแลตัวเอง คุณยังเลือกปรึกษาหน่วยบริการได้','book'],high:['แนะนำให้ตรวจเพิ่มเติม','คุณดูสถานที่ตรวจหรือขอให้เจ้าหน้าที่ช่วยประสานได้ค่ะ','alert']};
let answers=[],originalAnswers=[],bodyMeasurements=null,reviewingMissing=false,multipleSymptoms=false,step=0,level='low',request=null,requestKind=null,requestReceipt=null,stage=0,lessonProgress=0;
let reviewed=false, reviewIndex=null;
let activityRound=null, activityQueue=Promise.resolve();
function logActivity(event,outcome=null){
 if(!activityRound)return;
 const body=JSON.stringify({...activityRound,event,outcome});
 activityQueue=activityQueue.then(()=>fetch('activity.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'omit',keepalive:true,body})).catch(()=>{});
}
function startActivity(){
 activityRound=null;
 if(!globalThis.crypto?.getRandomValues||!['http:','https:'].includes(location.protocol))return;
 const bytes=crypto.getRandomValues(new Uint8Array(16));
 activityRound={id:Array.from(bytes,byte=>byte.toString(16).padStart(2,'0')).join(''),province:document.querySelector('#activity-province')?.value||null};
 logActivity('start');
}
const stageNames=['รอรับเรื่อง','รับเรื่องแล้ว','ยืนยันการตรวจแล้ว'];
const note='ผลนี้สรุปความเสี่ยงจากคำตอบของคุณ การยืนยันภาวะโลหิตจางต้องอาศัยการตรวจจากหน่วยบริการ';
const back=()=>`<button class="back" data-go="back" aria-label="ย้อนกลับ" title="ย้อนกลับ">${icon('arrow')}</button>`;
const backRow=()=>back().replace('class="back"','class="back floating-back"');
function pageHeading(symbol,title,summary='',category=''){
 return `<header class="knowledge-hero page-heading">${box(symbol)}<div>${category?`<span class="eyebrow">${category}</span>`:''}<h1 class="title">${title}</h1>${summary?`<p>${summary}</p>`:''}</div></header>`;
}
const panel=(content,wide=false)=>`<section class="panel ${wide?'wide':''}">${content}</section>`;
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
const navigationStack=[];
let activeView=null;
function set(html,viewKey,preserveScroll=false){
 const scroll=preserveScroll?window.scrollY:0;
 const template=document.createElement('template');
 template.innerHTML=html;
 const key=viewKey??template.content.querySelector('h1')?.textContent??'main';
 if(activeView&&activeView.key!==key){
  const dialog=main.querySelector('dialog[open]');
  dialog?.close();
  if(navigationStack.length===64)navigationStack.shift();
  navigationStack.push({...activeView,nodes:[...main.childNodes],dialogId:dialog?.id,scroll:window.scrollY});
 }
 activeView={key,step,reviewingMissing,multipleSymptoms,answers,originalAnswers,bodyMeasurements,reviewed,reviewIndex,level,directoryType,directoryListing,activityRound};
 main.replaceChildren(template.content);
 main.focus({preventScroll:true});
 if(window.scrollY!==scroll)window.scrollTo({top:scroll,behavior:'instant'});
 observeBarCharts();
}
function goBack(){
 const previous=navigationStack.pop();
 if(!previous)return;
 main.querySelector('dialog[open]')?.close();
 facilitySearch++;
 if(previous.answers!==answers){
  ({answers,originalAnswers,bodyMeasurements}=previous);
 }
 ({step,reviewingMissing,multipleSymptoms,reviewed,reviewIndex,level,directoryType,directoryListing,activityRound}=previous);
 activeView=previous;
 main.replaceChildren(...previous.nodes);
 main.focus({preventScroll:true});
 window.scrollTo(0,previous.scroll);
 observeBarCharts();
 if(previous.dialogId)document.getElementById(previous.dialogId)?.showModal();
 const activity=main.querySelector('#public-activity');
 if(activity)loadPublicActivity(activity);
}
const voiceText={
 unknown:'ขอบคุณที่ตอบเท่าที่จำได้ค่ะ แบบประเมินนี้ยังสรุประดับความเสี่ยงไม่ได้ คุณอ่านคำแนะนำต่อได้เลย หากนึกข้อมูลออก ค่อยกลับมาเติม หรือเลือกดูช่องทางปรึกษาหน่วยบริการได้ค่ะ',
 low:'จากคำตอบตัวอย่างครั้งนี้ คุณอ่านความรู้สำหรับดูแลตัวเองได้จากหน้านี้ค่ะ',
 medium:'คุณลองอ่านหรือฟังคำแนะนำเพื่อดูแลตัวเอง คุณยังเลือกปรึกษาหน่วยบริการได้ได้ค่ะ',
 high:'จากคำตอบตัวอย่าง แนะนำให้ปรึกษาหน่วยบริการเรื่องตรวจเลือดค่ะ คุณกดดูสถานที่ตรวจ หรือขอให้เจ้าหน้าที่ช่วยประสานได้เลย',
 learn:'ผลจากแบบประเมินมาจากคำตอบที่คุณให้ หากหน้าจอแนะนำให้ตรวจเพิ่มเติม กดดูสถานที่ตรวจ หรือเลือกขอให้เจ้าหน้าที่ติดต่อกลับได้ค่ะ'
};
const audioUI=(key,showText=true)=>`<div class="status">${box('sound')}<div><strong>Voice Coach ภาษาไทย</strong>${showText?`<p class="small">${voiceText[key]}</p>`: '<p class="small">กดฟังเนื้อหาบทนี้</p>'}<div class="actions"><button class="action" data-voice="${key}">${icon('play')}ฟังคำแนะนำ</button><button class="action" data-go="pause">${icon('pause')}พักเสียง</button></div><span id="voice-status" class="notice" role="status"></span></div></div>`;
function project(){
 set(panel(`${backRow()}${pageHeading('history','เกี่ยวกับโครงการ','ประเมินภาวะโลหิตจางด้วยตนเอง เรียนรู้ และติดตามการเข้ารับบริการ')}
 <div class="grid project-grid"><section class="card">${box('check')}<h2>แบบประเมินและคำแนะนำ</h2><p>แบบประเมินฉบับร่าง 10 ข้อสำหรับหญิงอายุ 15–49 ปี ใช้เป็นต้นทางของคำถามและหน้าผลในเดโม่</p>${button('ลองประเมิน','start','check')}</section>
 <section class="card">${box('book')}<h2>พิชิตโลหิตจาง 12 สัปดาห์</h2><p>แนวทางเรียนรู้ ติดตาม และวัดผลก่อน–หลัง จากเอกสาร Connected Anemia Pipeline</p>${button('เปิดคลังความรู้','learn','book')}</section>
 <section class="card">${box('chart')}<h2>การติดตามและรายงาน</h2><p>ร่างตัวชี้วัดครอบคลุมผลประเมิน การเข้าโปรแกรม การส่งต่อ การตรวจ และความรู้ก่อน–หลัง</p>${button('ดูข้อมูลภาพรวม','open-data','chart')}</section></div>
 <p class="notice">ข้อมูลและเกณฑ์ในหน้าสาธิตใช้แสดงรูปแบบการทำงานของโครงการ</p>`,true));
}
function homeActionCard(label,summary,route,symbol){
 return `<button type="button" class="card home-action-card" data-go="${route}">
 <span class="home-card-title">${box(symbol)}<strong>${label}</strong>${icon('next')}</span>
 <span class="home-action-copy">${summary}</span></button>`;
}
function home(){set(`<section class="hero"><div><span class="eyebrow">สำหรับผู้หญิงอายุ 15–49 ปี</span><h1><i class="home-title-icon">${icon('pulse')}</i>มาดูแลสุขภาพ<span>ของเรากัน</span></h1><p>ลองตอบเรื่องใกล้ตัว เช่น รอบเดือน อาการช่วงนี้ และอาหารที่กิน แล้วดูคำแนะนำเบื้องต้นเกี่ยวกับภาวะโลหิตจาง</p><div class="actions">${button('เริ่มตอบได้เลย','start','check',true)}${button('เรียนรู้เรื่องโลหิตจาง','learn','book')}</div></div><div class="hero-art" aria-hidden="true"><span class="halo">${icon('drop')}</span><span class="hero-ring ring-inner"></span><span class="hero-ring ring-outer"></span><img class="hero-portrait" src="hero-women.webp" alt="" decoding="async" width="520" height="337"><span class="hero-pattern"></span></div></section><div class="grid">${homeActionCard('ประเมินได้ทันที','ตอบเท่าที่จำได้ ข้อไหนไม่แน่ใจมีตัวเลือกให้','start','check')}${homeActionCard('ฟังคำแนะนำได้','กดเพื่อฟังตัวอย่างและดูว่าจะทำอะไรต่อ','home-voice','sound')}<section class="card"><h3 class="home-card-title">${box('hospital')}<span>ติดตามคำขอตรวจ</span></h3><p>ดูว่าหน่วยบริการรับเรื่องแล้วหรือยัง</p>${button('ดูสถานะของฉัน','track','hospital')}</section></div>`);}
const optionHints={
 3:['รู้สึกเวียนหัว','รู้สึกล้าหรือไม่มีแรง','รู้สึกเหนื่อยง่ายกว่าที่เคย','รู้สึกว่าหัวใจเต้นแรงหรือเร็ว','เลือกข้อนี้ได้ข้อเดียว','เลือกข้อนี้หากนึกไม่ออก'],
 4:['นับเฉพาะครั้งที่ชุ่มเต็มแผ่น','นับเฉพาะครั้งที่ชุ่มเต็มแผ่น'],
 6:['นับมื้อในวันทั่วไปของคุณ','นับมื้อในวันทั่วไปของคุณ','นับมื้อในวันทั่วไปของคุณ']
};
const optionSymbols={
 0:['15','25','35','…'],
 3:['pulse','pulse','pulse','pulse','—','?'],
 4:['calendar','calendar','?','—'],
 5:['≤7','>7','>7','?'],
 6:['1','2','3+','?'],
 8:['<3','3+','?'],
 9:['<3','3+','?']
};
function optionCard(option,index,selected,symbol,hint,attribute){
 const artwork=illustrations[symbol]?illustration(symbol):icons[symbol]?icon(symbol):symbol;
 return `<button class="choice option-card" aria-pressed="${selected}" ${attribute}="${index}">
 <span class="option-art" aria-hidden="true">${artwork}</span>
 <span class="option-copy"><strong>${option}</strong>${hint?`<small>${hint}</small>`:''}</span>
 <span class="option-state" aria-hidden="true">${icon(selected?'check':'next')}</span></button>`;
}
function question(){
 const {topic,symbol,cue,prompt,options,multi,measurements}=questions[step];
 const selected=multi?(answers[step]??[]):[answers[step]];
 const choosingMultiple=multi&&multipleSymptoms;
 const progressLabel=step===0?'เริ่มกันเลย':step===questions.length-1?'เรื่องสุดท้ายแล้ว':step>=7?'ใกล้ดูผลแล้ว':'ค่อย ๆ ตอบทีละเรื่อง';
 set(panel(`${backRow()}<div class="question-top"><span class="progress-caption">${progressLabel}</span></div>
 <div class="question-progress" role="progressbar" aria-label="ความคืบหน้าการประเมิน" aria-valuemin="0" aria-valuemax="${questions.length}" aria-valuenow="${step+1}"><span style="width:${(step+1)/questions.length*100}%"></span></div>
 <div class="question-scene scene-${symbol}"><div class="topic-copy"><span class="question-topic">${icon(symbol)} ${topic}</span><h1 class="title">${prompt}</h1><p>${cue}</p></div><span class="topic-art">${illustration(symbol)}</span></div>
 ${multi?`<button class="action symptom-mode" data-go="multiple-symptoms" aria-pressed="${choosingMultiple}">${icon('pulse')}เลือกหลายอาการ</button>`:''}
 ${measurements?measurementFields():`<div class="question-options">${options.map((option,i)=>optionCard(option,i,selected.includes(i),optionSymbols[step]?.[i]??(i===options.length-1?'?':symbol),optionHints[step]?.[i],'data-answer')).join('')}</div>`}
 ${choosingMultiple?`<button class="action primary" data-go="next-multi" ${selected.length?'':'disabled'}>ไปต่อ ${icon('next')}</button>`:''}
 <aside class="answer-note">${icon('book')} <span>${multi?'เลือกได้หลายอาการ ถ้าจำไม่ได้ เลือกข้อนั้นได้เลย':'ตอบตามที่จำได้ ถ้าไม่แน่ใจใช้ตัวเลือกที่มีได้'}</span></aside>`),`question:${step}:${reviewingMissing}`);
}
function measurementFields(){
 return `<form id="measurements" class="measurements" novalidate>
 <div class="measurement-grid">
 <label>น้ำหนัก (กิโลกรัม)<input name="weight" type="number" inputmode="decimal" step="any" placeholder="เช่น 52" value="${bodyMeasurements?.weight??''}"></label>
 <label>ส่วนสูง (เซนติเมตร)<input name="height" type="number" inputmode="decimal" step="any" placeholder="เช่น 160" value="${bodyMeasurements?.height??''}"></label>
 </div>
 <p class="notice" id="measurement-message" role="status"></p>
 <div class="actions"><button class="action primary" type="submit">ไปต่อ ${icon('next')}</button>
 <button class="action" type="button" data-go="skip-measurements">${icon('help')}จำไม่ได้หรือไม่ทราบ</button></div>
 </form>`;
}
document.addEventListener('submit',event=>{
 if(event.target.id!=='measurements')return;
 event.preventDefault();
 const {weight,height}=event.target.elements;
 const weightValue=Number(weight.value),heightValue=Number(height.value);
 if(!(weightValue>0&&weightValue<=500&&heightValue>=50&&heightValue<=250)){
  document.querySelector('#measurement-message').textContent='ลองตรวจตัวเลขอีกครั้ง น้ำหนักใช้กิโลกรัมและส่วนสูงใช้เซนติเมตร';
  (weightValue>0&&weightValue<=500?height:weight).focus();
  return;
 }
 bodyMeasurements={weight:weightValue,height:heightValue};
 answerQuestion(weightValue/(heightValue/100)**2<18.5?0:1);
});
const nextSteps={
 low:{title:'อ่านวิธีดูแลตัวเอง',description:'เปิดเรื่องที่สนใจและดูคำแนะนำที่นำไปใช้ได้',route:'learn',symbol:'book'},
 medium:{title:'เริ่มจากบทเรียนสั้น ๆ',description:'อ่านหรือฟังบทแรกก่อน แล้วค่อยเลือกว่าจะทำอะไรต่อ',route:'learn',symbol:'book'},
 high:{title:'ดูว่าจะไปตรวจที่ไหน',description:'ดูหน่วยบริการและวิธีเข้ารับการตรวจ',route:'facilities',symbol:'hospital'},
 unknown:{title:'อ่านคำแนะนำได้เลย',description:'ดูขั้นตอนที่เลือกทำได้ แม้ยังจำข้อมูลได้ไม่ครบ',route:'learn',symbol:'book'}
};
function result(showWarning=false){
 const flagged=['medium','high'].includes(level);
 const needsReview=flagged&&!reviewed;
 const [title,message,symbol]=levels[level];
 const next=nextSteps[level];
 const hasMissing=missingAnswerIndex()!==-1;
 const reviewAction=hasMissing?button('เติมเฉพาะเรื่องที่ไม่แน่ใจ','review-missing','refresh'):button('ทบทวนคำตอบเรื่องรอบเดือน','review','refresh');
 set(panel(`<div class="result-view">${backRow()}
 <section class="result-summary status ${level}"><div class="result-copy"><span class="small">${level==='unknown'?'ขอบคุณที่ตอบเท่าที่จำได้':'ผลคัดกรองเบื้องต้น · ฉบับทดลอง'}</span><h1 class="title">${title}</h1><p>${message}</p></div><div class="result-art" aria-hidden="true"><span class="result-orbit orbit-wide"></span><span class="result-orbit orbit-inner"></span><span class="result-emblem">${illustration(level==='high'?'history':level==='low'?'pulse':'calendar')}</span><span class="result-satellite">${icon(symbol)}</span><span class="result-glint">✦</span></div></section>
 <section class="card result-next"><h2>ทำอะไรต่อได้บ้าง?</h2>
 <button class="primary next-card" data-go="${next.route}">${box(next.symbol)}<span><strong>${next.title}</strong><small>${next.description}</small></span>${icon('next')}</button>
 <div class="result-links">${level==='high'?'':button('ดูสถานที่ตรวจ','facilities','hospital')}${button('ขอให้เจ้าหน้าที่ติดต่อกลับ','callback-directory','phone')}</div></section>
 ${audioUI(level)}
 <div class="actions"><a class="action" href="participant-account.php">${icon('person')}เก็บแต้มและความคืบหน้าในบัญชี</a></div>
 <section class="card result-review"><div class="result-section-title">${box('refresh')}<h2>ทบทวนหรือเริ่มใหม่</h2></div><p>แก้เฉพาะเรื่องที่จำได้ หรือเริ่มตอบตั้งแต่เรื่องแรก</p>
 <div class="actions">${needsReview?button('ลองตอบอีกมุม','review-risk','refresh'):reviewAction}${button('ประเมินใหม่ทั้งชุด','restart','refresh')}</div></section>
 <p class="notice">${note}</p>
 ${showWarning&&flagged?`<dialog class="signal-dialog" id="risk-warning" aria-labelledby="risk-warning-title">
  ${box('alert')}<h2 id="risk-warning-title">${level==='high'?'แนะนำให้ตรวจเพิ่มเติม':'มีคำแนะนำสำหรับคุณ'}</h2>
  <p>${level==='high'?'คุณเลือกที่ตรวจหรือขอให้เจ้าหน้าที่ติดต่อกลับได้เลย หากยังไม่แน่ใจในคำตอบ สามารถทบทวนก่อนก็ได้':'เริ่มอ่านคำแนะนำได้เลย หรือทบทวนคำตอบหากมีเรื่องที่ยังไม่แน่ใจ'}</p>
  <div class="actions">${button(next.title,next.route,next.symbol,true)}${level==='high'?button('ให้เจ้าหน้าที่ติดต่อกลับ','callback-directory','phone'):''}${needsReview?button('ทบทวนคำตอบ','review-risk','refresh'):''}
  <form method="dialog"><button class="action">${icon('close')}ปิด</button></form></div>
 </dialog>`:''}</div>`));
 if(showWarning&&flagged)document.querySelector('#risk-warning').showModal();
}
const reviewCues={0:'นึกถึงอายุในวันเกิดล่าสุด',1:'นึกถึงตัวเลขจากครั้งล่าสุดที่ชั่งและวัด',2:'นึกถึงสิ่งที่เจ้าหน้าที่เคยบอกหลังตรวจสุขภาพ',3:'นึกถึงวันที่เรียน ทำงาน หรือเดินทางในเดือนที่ผ่านมา',4:'นึกถึงวันที่ออกมากที่สุดในรอบล่าสุด นับเฉพาะครั้งที่เปลี่ยนเพราะชุ่มเต็มแผ่น',5:'ลองนับจากวันแรกที่มีเลือดถึงวันสุดท้ายของรอบล่าสุด',6:'ลองไล่นึกถึงมื้อตั้งแต่ตื่นจนเข้านอนในวันทั่วไป',7:'ลองไล่นึกถึงเมนูที่กินใน 7 วันที่ผ่านมา',8:'ลองนึกถึงวันที่กินฝรั่ง ส้ม หรือผลไม้วิตามินซีสูงใน 7 วันที่ผ่านมา',9:'ลองนึกถึงมื้อที่ดื่มชาหรือกาแฟใกล้มื้ออาหารใน 7 วันที่ผ่านมา'};
function riskReview(){
 logActivity('review_open');
 const scoreFor=(q,i)=>q.multi?(answers[i]??[]).reduce((total,a)=>total+(q.points[a]??0),0):q.points[answers[i]]??0;
 reviewIndex=[3,4,5,2,1,7,8,9,6,0].sort((a,b)=>scoreFor(questions[b],b)-scoreFor(questions[a],a))[0];
 if(!scoreFor(questions[reviewIndex],reviewIndex))reviewIndex=-1;
 if(reviewIndex<0){reviewed=true;result();return;}
 const q=questions[reviewIndex];
 const choices=q.measurements?'':q.options.map((label,i)=>q.multi?`<label class="card"><input type="checkbox" name="review-symptom" value="${i}"> ${label}</label>`:`<button class="choice" data-risk-answer="${i}">${icon('next')}${label}</button>`).join('');
 set(panel(`${backRow()}${pageHeading(q.symbol,'ลองนึกอีกครั้ง',reviewCues[reviewIndex])}<p>${reviewIndex===4?'ในวันนั้น ถ้านับเฉพาะแผ่นที่ชุ่มเต็ม คุณเปลี่ยนเกิน 5 ครั้งไหม?':reviewIndex===3?'ในช่วง 1 เดือนที่ผ่านมา เคยรู้สึกแบบไหนระหว่างทำกิจวัตรบ้าง?':q.prompt}</p>${q.measurements?'<label>น้ำหนัก (กก.) <input type="number" id="review-weight" min="1" max="500"></label><label>ส่วนสูง (ซม.) <input type="number" id="review-height" min="50" max="250"></label>':choices}<p id="review-message" role="status"></p><div class="actions">${q.multi||q.measurements?button('ดูผลหลังทบทวน','review-confirm','check',true):''}${button('ยังไม่แน่ใจ · กลับไปดูผล','review-unsure','arrow')}</div>`));
}
function finishRiskReview(answer){
 if(answer!==undefined)answers[reviewIndex]=answer;
 reviewed=true;
 classify();
 logActivity(answer===undefined?'review_skip':'review_done',answer===undefined?null:level);
}
const assessmentGroups={public:'ประชาชนทั่วไป',student:'นักเรียน / นักศึกษา'};
function chooseAssessmentGroup(){
 set(panel(`${backRow()}${pageHeading('check','มาเริ่มกันเลย','ตอนนี้คุณกำลังเรียนอยู่ไหม?')}
 <div class="assessment-group-grid">
  <button type="button" class="card assessment-group-card" data-go="assessment-public">
   <img src="assessment-public.webp" width="400" height="400" alt="">
   <span><strong>ประชาชนทั่วไป</strong><small>ฉันไม่ได้กำลังเรียนอยู่</small></span>${icon('next')}
  </button>
  <button type="button" class="card assessment-group-card" data-go="assessment-student">
   <img src="assessment-student.webp" width="400" height="400" alt="">
   <span><strong>นักเรียน / นักศึกษา</strong><small>ฉันกำลังเรียนอยู่</small></span>${icon('next')}
  </button>
 </div>`));
}
const signalScenarios={
 now:{label:'แนะนำให้ไปตรวจทันที',action:'ดูคำแนะนำ',route:'signal-urgent',icon:'alert'},
 check:{label:'แนะนำให้หาเวลาไปตรวจ',action:'ดูทางเลือกการตรวจ',route:'signal-check',icon:'hospital'},
 none:{label:'ยังไม่มีคำแนะนำให้ไปตรวจ',action:'เริ่มตอบแบบประเมิน',route:'assessment',icon:'next'},
};
function signalDemo(group){
 activityRound=null;
 set(panel(`${backRow()}${pageHeading('history','ก่อนเริ่ม มาลองดูตัวอย่างกัน','คำตอบในหน้านี้ใช้ทดลองการแนะนำขั้นตอนถัดไป',assessmentGroups[group])}
 <label>จังหวัด (ไม่จำเป็น)<select id="activity-province"><option value="">ไม่ระบุจังหวัด</option>${[...nationalProvinces].sort((a,b)=>a[1].localeCompare(b[1],'th')).map(([code,name])=>`<option value="${code}">${name}</option>`).join('')}</select></label>
 <fieldset class="signal-scenarios">
  <legend>ในเรื่องสมมตินี้ เจ้าหน้าที่แนะนำให้ไปตรวจเมื่อไหร่?</legend>
  ${Object.entries(signalScenarios).map(([value,scenario])=>`<label class="signal-option"><input type="radio" name="signal-scenario" value="${value}"><span>${scenario.label}</span></label>`).join('')}
 </fieldset>
 <div class="actions signal-next-action" id="signal-next-action" aria-live="polite"></div>
 <p class="notice">ระบบนับสถิติการทดลองด้วยรหัสสุ่มต่อรอบ โดยไม่ขอชื่อหรือเบอร์โทร</p>`));
}
function showSignalNext(value){
 const target=document.querySelector('#signal-next-action');
 if(!target)return;
 const scenario=Object.hasOwn(signalScenarios,value)?signalScenarios[value]:null;
 target.innerHTML=scenario?button(scenario.action,scenario.route,scenario.icon,true):'';
}
function signalAlert(urgent){
 if(!activityRound)startActivity();
 logActivity('signal');
 const title=urgent?'ควรรับบริการทันที':'มีเรื่องที่ควรให้เจ้าหน้าที่ช่วยดูเพิ่มเติม';
 set(panel(`${backRow()}${pageHeading('alert',title,'สถานการณ์สมมติ')}<dialog class="signal-dialog" id="signal-warning" aria-labelledby="warning-title">${box('alert')}<h2 id="warning-title">${title}</h2><p>${urgent?'ไปดูทางเลือกสำหรับเข้ารับบริการทันทีได้เลย':'เลือกดูสถานที่ตรวจ หรือทดลองให้เจ้าหน้าที่ติดต่อกลับ'}</p><div class="actions">${button(urgent?'ดูช่องทางรับบริการทันที':'ค้นหาที่ตรวจ','signal-facilities','hospital',true)}${urgent?'':(button('ให้เจ้าหน้าที่ติดต่อกลับ','signal-callback','phone')+button('ทำแบบประเมินต่อ','assessment','next'))}<form method="dialog"><button class="action">${icon('close')}ปิดข้อความ</button></form></div></dialog><div class="actions">${button('เปิดคำเตือนอีกครั้ง',urgent?'signal-urgent':'signal-check','alert')}${button('ทำแบบประเมินต่อ','assessment','next',true)}</div>`));
 document.querySelector('#signal-warning').showModal();
}
function restart(){
 set(panel(`${backRow()}<h1 class="title">เริ่มประเมินใหม่ทั้งชุด?</h1><p>คุณจะกลับไปตอบตั้งแต่เรื่องแรก ผลและคำขอจำลองของรอบนี้จะถูกล้างจากหน้าจอเดโม่</p>
 <div class="actions">${button('เริ่มประเมินใหม่','start','refresh',true)}${button('กลับไปดูผลเดิม','result','arrow')}</div>`));
}
function review(){set(panel(`${backRow()}<h1 class="title">ทบทวนคำตอบ</h1><p>ลองนึกถึงวันที่เปลี่ยนผ้าอนามัยบ่อยที่สุดในรอบล่าสุด</p><h2 class="title">ถ้านับเฉพาะครั้งที่เปลี่ยนเพราะชุ่มเต็มแผ่น วันนั้นเปลี่ยนเกิน 5 ครั้งไหม?</h2>${questions[4].options.map((option,i)=>`<button class="choice" aria-pressed="${answers[4]===i}" data-review="${i}">${icon('next')}${option}</button>`).join('')}`));}
let knowledgeCategory='',knowledgeQuery='';
const knowledgeIllustrations={
 basics:[0,0],signals:[1,0],cycle:[2,0],urgent:[3,0],
 result:[0,1],lab:[1,1],visit:[2,1],food:[3,1],
 drinks:[0,2],supplements:[1,2],pregnancy:[2,2],thalassemia:[3,2]
};
function knowledgeArt(id){
 const [column,row]=knowledgeIllustrations[id];
 return `<span class="knowledge-art" aria-hidden="true" style="--art-x:${column/3*100}%;--art-y:${row/2*100}%"></span>`;
}
function visibleKnowledge(){
 const query=knowledgeQuery.trim().toLowerCase();
 return knowledge.filter(([,category,,title,summary,body])=>
  (!knowledgeCategory||category===knowledgeCategory)&&[title,summary,...body].join(' ').toLowerCase().includes(query));
}
function knowledgeCards(){
 const entries=visibleKnowledge();
 return entries.length?entries.map(([id,category,,title,summary])=>`<button class="card knowledge-card" data-article="${id}">${knowledgeArt(id)}<span><small>${category}</small><strong>${title}</strong><span>${summary}</span></span>${icon('next')}</button>`).join(''):'<p>ยังไม่เจอเรื่องที่ค้นหา ลองใช้คำสั้น ๆ เช่น “รอบเดือน” หรือ “ตรวจเลือด”</p>';
}
function learn(){
 const categories=['',...new Set(knowledge.map(article=>article[1]))];
 set(panel(`${backRow()}${pageHeading('book','คลังความรู้เรื่องโลหิตจาง','ค้นเรื่องที่อยากรู้ ตั้งแต่สังเกตอาการไปจนถึงเตรียมตัวตรวจเลือด')}
 <div class="knowledge-tools">
  <div class="knowledge-search-box" role="search" aria-label="ค้นหาในคลังความรู้">
   <label for="knowledge-search">ค้นหาบทความ</label>
   <div class="knowledge-search-field">${icon('search')}<input id="knowledge-search" type="search" placeholder="เช่น รอบเดือน หรือ ธาตุเหล็ก"></div>
  </div>
 <div class="knowledge-filters" aria-label="หมวดความรู้">${categories.map((category,i)=>`<button class="action" data-category="${i}" aria-pressed="${knowledgeCategory===category}">${icon(i?'book':'chart')}${category||'ทุกเรื่อง'}</button>`).join('')}</div></div>
 <div id="knowledge-list" class="knowledge-grid">${knowledgeCards()}</div>
 <section class="card knowledge-program"><h2>พิชิตโลหิตจาง 12 สัปดาห์</h2><p>ลองบทเรียนตัวอย่าง · ความคืบหน้า ${lessonProgress} จาก 12 บท</p><div class="actions">${button(lessonProgress?'ทบทวนบทแรก':'ลองบทแรก','lesson','book',true)}${button('ทีมของฉัน · ชวนเพื่อน','team-challenge','share')}</div></section>`,true));
 document.querySelector('#knowledge-search').value=knowledgeQuery;
}
function openArticle(id){
 const article=knowledge.find(entry=>entry[0]===id);
 if(!article)return;
 const [,category,symbol,title,summary,body,sources]=article;
 const visible=visibleKnowledge();
 const playlist=visible.some(entry=>entry[0]===id)?visible:knowledge;
 const next=playlist[playlist.findIndex(entry=>entry[0]===id)+1];
 const nextControl=next?`<button type="button" class="back floating-back floating-next" data-article="${next[0]}" aria-label="บทถัดไป ${next[3]}" title="บทถัดไป">${icon('next')}</button>`:'';
 voiceText.article=body.join(' ');
 set(panel(`${backRow()}${nextControl}<article><header class="knowledge-hero knowledge-article-hero"><div>${box(symbol)}<span class="eyebrow">${category}</span><h1 class="title">${title}</h1><p>${summary}</p></div>${knowledgeArt(id)}</header>
 <div class="knowledge-body"><p>${body[0]}</p><section class="knowledge-action-note"><h2>${id==='urgent'?'ถ้าตั้งครรภ์หรืออาจตั้งครรภ์':'ลองทำดู'}</h2><p>${body[1]}</p></section></div>${audioUI('article',false)}
 <section class="knowledge-sources"><h2>แหล่งข้อมูล</h2><ul>${sources.map(key=>{const [label,url]=knowledgeSources[key];return `<li><a href="${url}" target="_blank" rel="noopener noreferrer">${label}</a></li>`;}).join('')}</ul></section>
 <div class="actions">${button('กลับคลังความรู้','learn','book')}${button(originalAnswers.length?'กลับไปดูผลของฉัน':'เริ่มประเมินตนเอง',originalAnswers.length?'result':'start','check',true)}</div></article>`));
}
document.addEventListener('input',event=>{
 if(event.target.id==='request-lookup-code')event.target.setCustomValidity('');
 if(event.target.id==='request-phone')event.target.value=requestPhoneDisplay(event.target.value);
 if(event.target.id!=='knowledge-search')return;
 knowledgeQuery=event.target.value;
 document.querySelector('#knowledge-list').innerHTML=knowledgeCards();
});
function lesson(){set(panel(`${backRow()}${pageHeading('book','จากผลประเมิน ไปตรวจต่ออย่างไร','บทแรก · บทอ่านและกิจกรรมทบทวน')}<section class="card"><p>เมื่อหน้าผลแนะนำให้ตรวจเพิ่มเติม คุณเปิดรายชื่อหน่วยบริการเพื่อดูสถานที่และช่องทางติดต่อได้ หากส่งคำขอนัดผ่านเว็บ ให้ตรวจสถานะจนหน่วยบริการยืนยันวันและรอบนัด</p></section><h2 class="title">ถ้าต้องการเปิดรายชื่อหน่วยบริการ ควรเลือกปุ่มไหน?</h2><button class="choice" data-go="correct">${icon('hospital')}ดูสถานที่ตรวจ</button><button class="choice" data-go="tryagain">${icon('refresh')}ทบทวนคำตอบ</button><p id="feedback" role="status"></p>${button('ดูทีมและแต้มของฉัน','team-challenge','share')}`));}
const teamInvite=new URLSearchParams(location.search).get('invite')??'';
function teamAccountLink(connected){
 const url=new URL('participant-account.php',location.href);
 if(/^[a-f0-9]{32}$/.test(teamInvite))url.searchParams.set('invite',teamInvite);
 return `<a class="action ${connected?'':'primary'}" href="${escapeDirectoryText(url.href)}">${icon('person')}${connected?'บัญชีของฉัน':'เข้าสู่ระบบหรือสมัครบัญชี'}</a>`;
}
async function teamChallengeApi(input){
 const response=await fetch('team-challenge.php',input?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(input),cache:'no-store'}:{cache:'no-store'});
 const data=await response.json();
 if(!response.ok)throw new Error(data.error??'โหลดกิจกรรมไม่ได้ กรุณาลองอีกครั้ง');
 return data;
}
async function showTeamChallenge(join=false){
 set(panel(`${backRow()}${pageHeading('share','ทีมของฉัน','ชวนเพื่อนมาเรียนรู้ด้วยกัน')}<div id="team-challenge-content" aria-live="polite">กำลังเปิดกิจกรรม</div>`),'team-challenge');
 const content=document.querySelector('#team-challenge-content');
 try{
  const team=await teamChallengeApi(join?{action:'join',invite:teamInvite}:undefined);
  if(!content.isConnected)return;
  if(!team.enabled){content.innerHTML=`<p>กิจกรรมชวนเพื่อนยังไม่เปิด</p>${teamAccountLink(team.connected)}`;return;}
  if(!team.joined){
   content.innerHTML=`<p>${teamInvite?'มีเพื่อนชวนคุณเข้าทีม':'เริ่มทีมของคุณแล้วแชร์ลิงก์ให้เพื่อน'}</p><div class="actions">${team.connected?button(teamInvite?'เข้าทีมเพื่อน':'สร้างทีมของฉัน','team-join','check',true):''}${teamAccountLink(team.connected)}</div>`;
   return;
  }
  const goal=team.completed<5?5:10;
  content.innerHTML=`<div class="grid team-challenge-stats"><div class="card"><strong>${team.points} แต้ม</strong><p>โบนัสจากชวนเพื่อน ${team.bonus} แต้ม</p></div><div class="card"><strong>เรียนจบแล้ว ${team.completed} คน</strong><progress value="${Math.min(team.completed,goal)}" max="${goal}" aria-label="ความคืบหน้าทีม"></progress><p>เป้าหมาย ${goal} คน</p></div></div>
   <p>${team.completed>=10?'🏅 เหรียญส่งต่อความรู้ · ทีมเรียนครบเป้าหมาย 10 คนแล้ว':team.completed>=5?'🏅 ปลดล็อกเหรียญส่งต่อความรู้แล้ว':'ครบ 5 คนรับเหรียญส่งต่อความรู้'}</p>
   <label>ลิงก์ชวนเพื่อน<input id="team-share-link" type="url" readonly></label>
   <div class="actions">${button('ส่งต่อให้เพื่อน','team-share','share',true)}${button(team.finished?'ทบทวนบทแรก':'เริ่มบทแรก','lesson','book')}${teamAccountLink(team.connected)}</div><p id="team-share-status" role="status"></p>`;
  const url=new URL('index.html',location.href);
  url.searchParams.set('invite',team.invite);
  content.querySelector('#team-share-link').value=url.href;
 }catch(error){
  if(!content.isConnected)return;
  const message=document.createElement('p');
  message.textContent=error.message;
  content.replaceChildren(message);
  if(teamInvite){
   const start=document.createElement('a');
   start.className='action';
   start.href='index.html?team=1';
   start.textContent='ไปที่ทีมของฉัน';
   content.append(start);
  }
 }
}
async function shareTeamChallenge(){
 const input=main.querySelector('#team-share-link');
 const status=main.querySelector('#team-share-status');
 try{
  if(navigator.share)await navigator.share({title:'ชวนเรียนรู้ไปด้วยกัน',url:input.value});
  else {await navigator.clipboard.writeText(input.value);status.textContent='คัดลอกลิงก์แล้ว';}
 }catch(error){
  if(error.name==='AbortError')return;
  input.select();
  status.textContent='เลือกลิงก์ไว้ให้แล้ว กดคัดลอกเพื่อส่งให้เพื่อน';
 }
}
async function completeTeamLesson(){
 const feedback=main.querySelector('#feedback');
 lessonProgress=1;
 feedback.textContent='ถูกต้อง ปุ่มนี้พาไปดูหน่วยบริการและวิธีเข้ารับการตรวจ';
 try{
  const team=await teamChallengeApi({action:'complete',answer:'facilities'});
  if(feedback.isConnected)feedback.textContent+=team.joined&&team.finished?' · บันทึกแต้มบทแรกแล้ว':' · เข้าทีมก่อนทำบทนี้เพื่อสะสมแต้ม';
 }catch {
  if(feedback.isConnected)feedback.textContent+=' · หากกำลังสะสมแต้ม ให้กดตอบอีกครั้งเพื่อบันทึก';
 }
}
const facilityDirectory=[
 {id:'DEMO-H01',name:adminFacilities[0],province:'อุบลราชธานี',district:'วารินชำราบ',lat:15.1839,lon:104.8548,onsite:false},
 {id:'DEMO-H02',name:adminFacilities[1],province:'อุบลราชธานี',district:'วารินชำราบ',lat:15.2061,lon:104.8617,onsite:true}
];
let requestFacilityId=null, facilitySearch=0;
const directoryTypes=['โรงพยาบาล','รพ.สต.'];
let directoryType=0,directoryListing=null;
function distanceKm(lat,lon,site){
 const rad=Math.PI/180;
 const hav=Math.sin((site.lat-lat)*rad/2)**2+Math.cos(lat*rad)*Math.cos(site.lat*rad)*Math.sin((site.lon-lon)*rad/2)**2;
 return 6371*2*Math.asin(Math.sqrt(Math.min(1,hav)));
}
const escapeDirectoryText=value=>String(value).replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
const directoryKey=value=>value.normalize('NFC').toLowerCase().replace(/[\s.]/g,'');
const searchableDirectory=hospitalDirectory.map(([name,address,lat,lon,type],index)=>({
 index,name,address,lat,lon,type,search:directoryKey(name+' '+address)
}));
function facilities(callbackIntent=false){
 facilitySearch++;
 directoryType=0;directoryListing=null;
 set(panel(`${backRow()}${pageHeading('hospital','ค้นหาโรงพยาบาลและ รพ.สต.',callbackIntent?'เลือกหน่วยบริการที่ต้องการให้ติดต่อกลับ':'ค้นจากชื่อ พื้นที่ หรือตำแหน่งปัจจุบัน แล้วเลือกดูตามประเภทหน่วยบริการ')}
 <section class="card facility-search"><form id="hospital-search"><label for="hospital-query">ชื่อหน่วยบริการ จังหวัด อำเภอ หรือรหัสไปรษณีย์</label>
 <div class="facility-query"><input id="hospital-query" type="search" placeholder="เช่น อุบลราชธานี" required><button class="action primary" type="submit">${icon('next')}ค้นหา</button></div></form>
 <div class="actions">${button('ค้นจากตำแหน่งปัจจุบัน','facility-location','hospital')}</div>
 <p class="notice">พิกัดของคุณใช้คำนวณบนเครื่องและจะไม่ถูกส่งหรือบันทึก</p></section>
 <div id="facility-tabs" class="facility-tabs" role="group" aria-label="ประเภทหน่วยบริการ">${directoryTabs()}</div>
 <p id="facility-feedback" class="notice" role="status">พิมพ์ชื่อหรือพื้นที่ หรือกดค้นจากตำแหน่งปัจจุบัน</p>
 <div id="facility-list" class="facility-list"></div>
 <p class="notice">ชื่อและพิกัดจาก <a href="https://data.go.th/th/dataset/health-citizeninfo" target="_blank" rel="noopener noreferrer">DGA CITIZENinfo (ชุดข้อมูล มีนาคม 2563 · CC BY 4.0)</a> ควรตรวจสอบเวลาเปิดและบริการตรวจเลือดกับหน่วยบริการก่อนเดินทาง</p>`));
}
function directoryResults(query,coords){
 const terms=query.trim().split(/\s+/).filter(Boolean).map(directoryKey).filter(Boolean);
 return searchableDirectory.filter(site=>terms.every(term=>site.search.includes(term)))
  .map(site=>({site,km:coords?distanceKm(coords.latitude,coords.longitude,site):null}))
  .sort((first,second)=>first.site.type-second.site.type||
   (coords?first.km-second.km:0)||first.site.name.localeCompare(second.site.name,'th'));
}
function facilityDirections(site){
 return 'https://www.google.com/maps/dir/?api=1&destination='+encodeURIComponent(site.lat+','+site.lon);
}
function directoryCard({site,km}){
 return `<button type="button" class="card facility-card facility-card-button" data-select-facility="${site.index}" aria-haspopup="dialog">
 ${box('hospital')}<span class="facility-card-copy"><strong>${escapeDirectoryText(site.name)}</strong>
 <span>${escapeDirectoryText(site.address)}${km==null?'':` · ${Math.round(km*10)/10} กม. (เส้นตรง)`}</span></span>${icon('next')}</button>`;
}
function selectedFacility(site){
 main.querySelector('#facility-options')?.remove();
 main.insertAdjacentHTML('beforeend',`<dialog class="signal-dialog facility-options" id="facility-options" aria-labelledby="facility-options-title">
  <div class="facility-options-heading">${box('hospital')}<div><h2 id="facility-options-title">${escapeDirectoryText(site.name)}</h2><p>${escapeDirectoryText(site.address)}</p></div></div>
  <h3>อยากให้เราช่วยเรื่องไหน?</h3>
  <div class="actions">
   <button type="button" class="action primary" data-facility-request="appointment" data-facility-index="${site.index}">${icon('calendar')}ขอนัดเข้าตรวจ</button>
   <button type="button" class="action" data-facility-request="callback" data-facility-index="${site.index}">${icon('phone')}ฝากเบอร์ให้ติดต่อกลับ</button>
   <a class="action" href="${facilityDirections(site)}" target="_blank" rel="noopener noreferrer">${icon('next')}เปิดเส้นทางไปเอง</a>
   <form method="dialog"><button class="action">${icon('close')}ปิด</button></form>
  </div>
  <p class="notice">${testAppointmentOtp?'คำขอตัวอย่างจะเข้าคิวทดสอบ':'ส่งคำขอหลังยืนยันเบอร์โทร เจ้าหน้าที่จะประสานก่อนยืนยันวันนัด'}</p>
 </dialog>`);
 main.querySelector('#facility-options').showModal();
}
const requestCalendarFormat=new Intl.DateTimeFormat('th-TH-u-ca-buddhist',{dateStyle:'long',timeZone:'UTC'});
const requestMonthFormat=new Intl.DateTimeFormat('th-TH-u-ca-buddhist',{month:'long',year:'numeric',timeZone:'UTC'});
const requestToday=new Intl.DateTimeFormat('sv-SE',{timeZone:'Asia/Bangkok'});
function requestPhoneDigits(value){
 return value.replace(/[๐-๙]/g,digit=>String(digit.charCodeAt(0)-3664)).replace(/[^0-9]/g,'').slice(0,10);
}
function requestPhoneDisplay(value){
 const digits=requestPhoneDigits(value);
 return [digits.slice(0,3),digits.slice(3,6),digits.slice(6)].filter(Boolean).join('-');
}
function renderRequestCalendar(calendar,offset=0){
 const today=requestToday.format(new Date());
 const [year,month,day]=today.split('-').map(Number);
 const first=new Date(Date.UTC(year,month-1+offset,1));
 const last=new Date(Date.UTC(year,month+5,day)).toISOString().slice(0,10);
 const days=new Date(Date.UTC(first.getUTCFullYear(),first.getUTCMonth()+1,0)).getUTCDate();
 const selected=calendar.querySelector('#request-date').value;
 calendar.dataset.offset=String(offset);
 calendar.querySelector('.request-calendar-content').innerHTML=`
  <div class="request-calendar-nav">
   <button type="button" data-calendar-shift="-1" aria-label="เดือนก่อนหน้า" ${offset===0?'disabled':''}>${icon('arrow')}</button>
   <strong aria-live="polite">${requestMonthFormat.format(first)}</strong>
   <button type="button" data-calendar-shift="1" aria-label="เดือนถัดไป" ${offset>=6?'disabled':''}>${icon('next')}</button>
  </div>
  <div class="request-calendar-grid">
   ${['อา.','จ.','อ.','พ.','พฤ.','ศ.','ส.'].map(name=>`<span>${name}</span>`).join('')}
   ${'<span aria-hidden="true"></span>'.repeat(first.getUTCDay())}
   ${Array.from({length:days},(_,index)=>{
    const date=new Date(Date.UTC(first.getUTCFullYear(),first.getUTCMonth(),index+1));
    const iso=date.toISOString().slice(0,10);
    return `<button type="button" data-calendar-day="${iso}" aria-label="${requestCalendarFormat.format(date)}" aria-pressed="${selected===iso}" ${iso<today||iso>last?'disabled':''}>${index+1}</button>`;
   }).join('')}
  </div>
  ${selected?'<button type="button" class="action" data-calendar-day="">ล้างวันที่ที่เลือก</button>':''}`;
}
function pickRequestDate(calendar,date){
 calendar.querySelector('#request-date').value=date;
 calendar.querySelector('#request-date-label').textContent=date?requestCalendarFormat.format(new Date(date+'T00:00:00Z')):'เลือกวันที่';
 renderRequestCalendar(calendar,Number(calendar.dataset.offset));
 calendar.open=false;
 calendar.querySelector('summary').focus({preventScroll:true});
}
const testAppointmentOtp=new URLSearchParams(location.search).get('test-otp')==='1';
function facilityRequestForm(site,kind){
 const appointment=kind==='appointment';
 set(panel(`${backRow()}${pageHeading(appointment?'calendar':'phone',appointment?'ขอนัดเข้าตรวจ':'ฝากเบอร์ให้ติดต่อกลับ',escapeDirectoryText(site.name))}
 <form class="card facility-request-form" id="facility-request-form" data-facility-index="${site.index}" data-kind="${kind}" autocomplete="off">
  <p class="notice">${testAppointmentOtp?'โหมดทดสอบ ชื่อและเบอร์ที่กรอกจะบันทึกในคิวทดสอบ โดยไม่มีการส่ง SMS':'โครงการใช้ชื่อและเบอร์โทรเพื่อประสานเรื่องการตรวจ และใช้เบอร์โทรเพื่อส่งรหัสยืนยันทาง SMS'}</p>
  <fieldset class="facility-request-details"><legend>${icon('person')}ข้อมูลติดต่อ</legend>
  <div class="facility-name-fields">
   <label><span>คำนำหน้า</span><select id="request-prefix" required><option value="">เลือกคำนำหน้า</option><option>น.ส.</option><option>นาง</option></select></label>
   <label><span>ชื่อ</span><input id="request-first-name" type="text" autocomplete="given-name" required maxlength="35" pattern=".*\\S.*"></label>
   <label><span>นามสกุล</span><input id="request-last-name" type="text" autocomplete="family-name" required maxlength="35" pattern=".*\\S.*"></label>
  </div>
  <label><span>${icon('phone')} เบอร์โทรสำหรับติดต่อ</span><input id="request-phone" type="tel" inputmode="numeric" autocomplete="tel-national" pattern="0[0-9]{2}\\-[0-9]{3}\\-[0-9]{4}" required placeholder="08X-XXX-XXXX"></label>
  ${appointment?`<details class="request-calendar"><summary>${icon('calendar')}<span>วันที่สะดวก <small>(ไม่บังคับ)</small></span><strong id="request-date-label">เลือกวันที่</strong></summary>
   <input id="request-date" type="hidden"><div class="request-calendar-content"></div></details>`:''}
  <fieldset><legend>${icon('check')}ความประสงค์</legend><label class="facility-request-consent"><input id="request-consent" type="checkbox" required> ${appointment?'ต้องการขอเข้ารับการตรวจประเมิน และให้ติดต่อเพื่อยืนยันวันนัด':'ต้องการให้เจ้าหน้าที่ติดต่อกลับเพื่อประสานเรื่องการตรวจ'}</label></fieldset>
  <button class="action primary" type="submit">${icon('phone')}${testAppointmentOtp?'ขอรหัส OTP ทดสอบ':'ส่งรหัส OTP ทาง SMS'}</button>
  <p id="request-start-status" role="status"></p>
  </fieldset>
  <fieldset class="facility-request-otp" hidden disabled><legend>ยืนยันเบอร์โทร</legend>
   <p class="notice" id="request-otp-hint"></p>
   <label>รหัส OTP <input id="request-otp-code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required aria-describedby="request-otp-hint request-otp-status"></label>
   <p id="request-otp-status" role="status"></p>
   <div class="actions"><button class="action primary" type="submit">${icon('check')}ยืนยันและส่งคำขอ</button>
   <button class="action" type="button" data-go="edit-request-phone">${icon('arrow')}แก้ข้อมูลหรือขอรหัสใหม่</button></div>
  </fieldset>
 </form>`));
 if(appointment)renderRequestCalendar(document.querySelector('.request-calendar'));

}
function setRequestVerification(form,verifying,last4='',demoCode=''){
 const details=form.querySelector('.facility-request-details');
 const verification=form.querySelector('.facility-request-otp');
 details.disabled=details.hidden=verifying;
 verification.disabled=verification.hidden=!verifying;
 const code=form.querySelector('#request-otp-code');
 code.value='';
 form.querySelector('#request-otp-status').textContent='';
 if(verifying){
  form.querySelector('#request-otp-hint').textContent=demoCode?'รหัสทดสอบ '+demoCode+' · เบอร์ลงท้าย '+last4+' · ชื่อและเบอร์ที่กรอกจะบันทึกในคิวทดสอบ โดยไม่มีการส่ง SMS':'กรอกรหัส 6 หลักจาก SMS ที่ส่งไปยังเบอร์ลงท้าย '+last4;
  code.focus();
 }else form.querySelector('#request-phone').focus();
}
function directoryTabs(){
 return directoryTypes.map((label,type)=>{
  const count=directoryListing?.counts[type];
  return `<button class="action facility-tab" data-directory-type="${type}" aria-pressed="${directoryType===type}">${icon(type===0?'hospital':'check')}${label}${count==null?'':`<span>${count.toLocaleString('th-TH')}</span>`}</button>`;
 }).join('');
}
function showDirectory(rows,mode){
 directoryType=0;
 directoryListing={rows,mode,counts:directoryTypes.map((_,type)=>rows.filter(row=>row.site.type===type).length)};
 renderDirectory();
}
function renderDirectory(limit=12){
 Object.assign(activeView,{directoryType,directoryListing});
 const tabs=document.querySelector('#facility-tabs');
 const list=document.querySelector('#facility-list');
 const feedback=document.querySelector('#facility-feedback');
 if(!list||!feedback||!tabs)return;
 tabs.innerHTML=directoryTabs();
 if(!directoryListing)return;
 const {rows,mode,counts}=directoryListing;
 const matching=rows.filter(row=>row.site.type===directoryType);
 const name=directoryTypes[directoryType];
 feedback.textContent=mode==='nearby'?name+'ใกล้คุณ '+counts[directoryType]+' แห่ง · เรียงตามระยะเส้นตรง':`พบ${name} ${counts[directoryType].toLocaleString('th-TH')} แห่ง`;
 list.innerHTML=matching.length?matching.slice(0,limit).map(directoryCard).join(''):`<p>ไม่พบ${name}ตามคำค้น ลองสลับแท็บหรือใช้คำค้นที่สั้นลง</p>`;
 if(matching.length>limit){
  const more=document.createElement('button');
  more.type='button';more.className='action';more.textContent='แสดงเพิ่ม';
  more.addEventListener('click',()=>renderDirectory(limit+12));
  list.append(more);
 }
}
function locateFacilities(){
 const search=++facilitySearch;
 const feedback=document.querySelector('#facility-feedback');
 if(!feedback)return;
 if(!navigator.geolocation){feedback.textContent='อุปกรณ์นี้ใช้ตำแหน่งไม่ได้ ลองพิมพ์ชื่อจังหวัดหรืออำเภอ';return;}
 feedback.textContent='กำลังขอตำแหน่งจากอุปกรณ์…';
 navigator.geolocation.getCurrentPosition(({coords})=>{
  if(search!==facilitySearch||!document.querySelector('#facility-list'))return;
  const rows=directoryResults('',coords);
  const hospitals=rows.filter(row=>row.site.type===0).slice(0,8);
  const primary=rows.filter(row=>row.site.type===1).slice(0,8);
  showDirectory([...hospitals,...primary],'nearby');
 },()=>{
  if(search===facilitySearch&&document.querySelector('#facility-feedback'))document.querySelector('#facility-feedback').textContent='ยังไม่พบตำแหน่ง ลองพิมพ์ชื่อจังหวัดหรืออำเภอเพื่อค้นหาได้เลย';
 },{timeout:8000,maximumAge:0});
}
async function submitFacilityRequest(form){
 if(!form.reportValidity()||form.dataset.busy==='1')return;
 const verifying=!form.querySelector('.facility-request-otp').disabled;
 const status=form.querySelector(verifying?'#request-otp-status':'#request-start-status');
 const payload=verifying?{action:'confirm',token:form.dataset.token,code:form.querySelector('#request-otp-code').value}:{
  action:'start',facility:Number(form.dataset.facilityIndex),kind:form.dataset.kind,
  prefix:form.querySelector('#request-prefix').value,firstName:form.querySelector('#request-first-name').value,
  lastName:form.querySelector('#request-last-name').value,phone:requestPhoneDigits(form.querySelector('#request-phone').value),
  date:form.querySelector('#request-date')?.value??'',consent:form.querySelector('#request-consent').checked
 };
 const submit=form.querySelector((verifying?'.facility-request-otp':'.facility-request-details')+' button[type=submit]');
 form.dataset.busy='1';submit.disabled=true;
 status.textContent=verifying?'กำลังยืนยันรหัส…':'กำลังส่ง SMS…';
 try{
  const publicDemoEndpoint='contact-request-demo.php?public=1';
  const demoEndpoint=testAppointmentOtp?'contact-request-demo.php':publicDemoEndpoint;
  let endpoint=testAppointmentOtp||form.dataset.otpMode==='demo'?demoEndpoint:'contact-request.php';
  const send=(url,body)=>fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  let response=await send(endpoint,payload);
  let data=await response.json();
  if(!verifying&&endpoint==='contact-request.php'&&data.demoAvailable===true){
   endpoint=publicDemoEndpoint;
   response=await send(endpoint,payload);
   data=await response.json();
  }
  if(!response.ok){status.textContent=data.error||'ดำเนินการไม่ได้ กรุณาลองอีกครั้ง';return;}
  if(!verifying){
   form.dataset.token=data.token;
   form.dataset.otpMode=endpoint===demoEndpoint?'demo':'live';
   status.textContent='';
   const demoCode=form.dataset.otpMode==='demo'?data.demoCode:undefined;
   setRequestVerification(form,true,data.last4,demoCode);
   if(demoCode)form.querySelector('#request-otp-code').value=demoCode;
   return;
  }
  request=searchableDirectory[Number(form.dataset.facilityIndex)].name;
  requestKind=form.dataset.kind;requestReceipt=data.receipt;requestFacilityId=null;stage=0;
  if(requestKind==='callback'&&form.dataset.otpMode!=='demo')logActivity('callback_sent');
  const calendar=form.querySelector('.request-calendar');
  form.reset();delete form.dataset.token;delete form.dataset.otpMode;
  if(calendar)pickRequestDate(calendar,'');
  setRequestVerification(form,false);
  if(form.isConnected)track();
 }catch{status.textContent='เชื่อมต่อไม่ได้ กรุณาลองอีกครั้ง';}
 finally{delete form.dataset.busy;submit.disabled=false;}
}
document.addEventListener('submit',event=>{
 if(event.target.id==='facility-request-form'){
  event.preventDefault();submitFacilityRequest(event.target);return;
 }
 if(event.target.id==='request-lookup-form'){
  event.preventDefault();
  const input=event.target.querySelector('#request-lookup-code');
  const receipt=input.value.trim().toUpperCase();
  if(!receiptPattern.test(receipt)){
   input.setCustomValidity('กรุณากรอกเลขคำขอจากใบรับคำขอ');
   input.reportValidity();
   return;
  }
  if(location.hash==='#request='+receipt)track(receipt,false);
  else location.hash='request='+receipt;
  return;
 }
 if(event.target.id!=='hospital-search')return;
 event.preventDefault();
 const query=document.querySelector('#hospital-query').value.trim();
 if(!directoryKey(query))return;
 facilitySearch++;
 const rows=directoryResults(query);
 showDirectory(rows,'search');
});
function track(reference=requestReceipt,showReceipt=true){
 const receipt=typeof reference==='string'&&receiptPattern.test(reference)?reference:null;
 const facility=showReceipt&&receipt&&receipt===requestReceipt?request:null;
 const sample=receipt?.startsWith('DE-');
 set(panel(`${backRow()}${pageHeading('hospital',facility?'ส่งคำขอเรียบร้อยแล้ว':'ติดตามคำขอของฉัน',facility?'เก็บใบรับคำขอไว้ใช้ติดต่อหรือติดตามสถานะ':'ใช้เลขคำขอเพื่อตรวจสอบสถานะ')}
 ${receipt?`<section class="card request-receipt-details">
  ${facility?`<strong>${escapeDirectoryText(facility)}</strong>`:''}
  <span>เลขคำขอ <strong class="request-receipt-number">${receipt}</strong></span>
  <p id="request-lookup-state" role="status">กำลังตรวจสถานะ</p>
  <div class="actions">${button('ตรวจสถานะล่าสุด','refresh-request-status','refresh')}</div>
  <p class="notice">${sample?'เป็นใบรับคำขอตัวอย่างสำหรับทดลองใช้งาน':'เก็บเลขนี้ไว้เมื่อติดต่อเจ้าหน้าที่'} · วันนัดต้องรอเจ้าหน้าที่ยืนยัน</p>
 </section>`:''}
 ${facility?`<figure class="request-receipt-figure"><canvas id="request-receipt-image" width="720" height="840" hidden role="img" aria-label="ใบรับคำขอพร้อม QR สำหรับติดตามสถานะ"></canvas><figcaption id="request-receipt-caption" role="status">กำลังสร้างภาพใบรับคำขอ</figcaption></figure>
 <div class="actions request-receipt-actions"><button type="button" class="action primary" data-go="save-request-receipt" disabled>${icon('download')}บันทึกภาพลงเครื่อง</button><a class="action" href="${escapeDirectoryText(receiptStatusUrl(receipt))}">${icon('next')}เปิดหน้าติดตาม</a></div>`:`
 <form class="card facility-request-form" id="request-lookup-form"><label>เลขคำขอ<input type="text" id="request-lookup-code" autocomplete="off" maxlength="19" required placeholder="เช่น AN-…"></label><button class="action primary" type="submit">${icon('search')}ตรวจสถานะ</button></form>`}
 <section class="card request-lookup-image" aria-label="ตรวจสถานะจากภาพ QR">
  <label class="request-scan-drop">
   ${box('search')}<span><strong>เลือกรูปใบรับคำขอ</strong><small>ใช้ภาพที่บันทึกไว้หรือภาพหน้าจอที่มี QR</small></span>
   <input type="file" id="request-lookup-image-file" accept="image/*">
  </label>
  <p id="request-lookup-image-status" role="status"></p>
 </section>
 ${receipt?`<dialog id="request-status-dialog" class="signal-dialog request-status-dialog" data-reference="${receipt}" aria-labelledby="request-status-title">
  <header class="request-status-hero">
   ${box('history')}<div><h2 id="request-status-title">สถานะคำขอ</h2>
   <p>เลขคำขอ <strong>${receipt}</strong>${sample?' · คำขอตัวอย่าง':''}</p></div>
  </header>
  <div class="request-status-body">
   <div class="request-status-result" data-state="loading">
    <span class="request-status-icon" aria-hidden="true" data-request-state-icon>${icon('history')}</span>
    <div><strong data-current-request-state role="status">กำลังตรวจสถานะ</strong><p data-request-guidance hidden></p></div>
   </div>
   <p data-request-checked class="request-status-checked" hidden></p>
  </div>
  <div class="actions request-status-actions"><form method="dialog"><button class="action" autofocus>${icon('close')}ปิด</button></form>${button('ตรวจอีกครั้ง','refresh-request-status','refresh',true)}</div>
 </dialog>`:''}`));
 if(receipt)loadRequestState(document.querySelector('#request-status-dialog'),!facility);
 if(facility){
  const canvas=document.querySelector('#request-receipt-image');
  const caption=document.querySelector('#request-receipt-caption');
  const save=document.querySelector('[data-go=save-request-receipt]');
  drawRequestReceipt(canvas,{receipt,facility,kind:requestKind,sample}).then(()=>{
   if(!canvas.isConnected)return;
   canvas.hidden=false;save.disabled=false;caption.textContent='สแกน QR หรือใช้เลขคำขอในการติดต่อ';
  }).catch(()=>{caption.textContent='สร้างภาพไม่ได้ คุณยังใช้เลขคำขอเพื่อติดตามสถานะได้';});
 }
}
let receiptScannerLoading;
function loadReceiptScanner(){
 if(typeof jsQR==='function')return Promise.resolve();
 if(!receiptScannerLoading)receiptScannerLoading=new Promise((resolve,reject)=>{
  const script=document.createElement('script');
  script.src='jsQR.js?v=1.4.0';
  script.onload=resolve;
  script.onerror=()=>{
   script.remove();receiptScannerLoading=null;
   reject(Error('เปิดเครื่องมืออ่าน QR ไม่ได้ กรุณาลองอีกครั้งหรือกรอกเลขคำขอ'));
  };
  document.head.append(script);
 });
 return receiptScannerLoading;
}
async function checkRequestImage(input){
 const file=input.files[0];
 if(!file)return;
 const status=input.closest('.request-lookup-image').querySelector('[role=status]');
 input.disabled=true;
 status.textContent='กำลังอ่าน QR จากภาพ';
 try{
  await loadReceiptScanner();
  const canvas=document.createElement('canvas');
  const context=canvas.getContext('2d',{willReadFrequently:true});
  const raw=await readRequestImage(file,(image,w,h)=>decodeRequestFrame(canvas,context,image,w,h));
  if(!input.isConnected)return;
  const directory=new URL('./',location.href).pathname;
  const receipt=scannedRequestReference(raw,location.origin,directory);
  if(!receipt){status.textContent='QR นี้ไม่ใช่ใบรับคำขอของระบบ';return;}
  if(location.hash==='#request='+receipt)track(receipt,false);
  else location.hash='request='+receipt;
 }catch(error){status.textContent=error.message;}
 finally{input.value='';input.disabled=false;}
}
async function loadRequestState(dialog,open=false){
 if(!dialog)return;
 if(open&&!dialog.open)dialog.showModal();
 if(dialog.dataset.loading==='1')return;
 const inline=main.querySelector('#request-lookup-state');
 const result=dialog.querySelector('[data-current-request-state]');
 const checked=dialog.querySelector('[data-request-checked]');
 const stateIcon=dialog.querySelector('[data-request-state-icon]');
 const guidance=dialog.querySelector('[data-request-guidance]');
 const statusDetails={
  pending:['history',dialog.dataset.reference.startsWith('DE-')?'ใช้ตรวจสอบขั้นตอนการติดตามในโหมดทดสอบ':'เจ้าหน้าที่จะติดต่อผ่านเบอร์ที่คุณให้ไว้'],
  contacted:['phone','หากมีวันนัด ให้ยึดรายละเอียดที่เจ้าหน้าที่แจ้ง'],
  accepted:['history','เจ้าหน้าที่รับเรื่องแล้ว กรุณารอการติดต่อ'],
  retry_contact:['phone','เจ้าหน้าที่จะลองติดต่ออีกครั้งตามเบอร์ที่ให้ไว้'],
  scheduled:['calendar','ดูวัน เวลา และสถานที่ตามที่เจ้าหน้าที่แจ้ง'],
  attended:['hospital','สอบถามขั้นตอนถัดไปกับหน่วยบริการที่คุณเข้ารับบริการ'],
  test_completed:['check','สอบถามผลตรวจและคำแนะนำจากหน่วยบริการ'],
  follow_up:['phone','ติดตามตามช่องทางที่หน่วยบริการแจ้ง'],
  closed:['check','เก็บเลขคำขอไว้หากต้องสอบถามเพิ่มเติม'],
  error:['alert',''],
  loading:['history','']
 };
 const controls=[...main.querySelectorAll('[data-go=refresh-request-status]')];
 const display=(message,state='')=>{
  result.textContent=message;
  result.closest('.request-status-result').dataset.state=state;
  const [symbol,description]=statusDetails[state]??statusDetails.loading;
  stateIcon.innerHTML=icon(symbol);
  guidance.textContent=description;
  guidance.hidden=!description;
  if(inline)inline.textContent=message;
 };
 dialog.dataset.loading='1';
 controls.forEach(control=>control.disabled=true);
 checked.hidden=true;
 display('กำลังตรวจสถานะล่าสุด','loading');
 try{
  const response=await fetch('request-status.php',{method:'POST',cache:'no-store',credentials:'omit',headers:{'Content-Type':'application/json'},body:JSON.stringify({receipt:dialog.dataset.reference})});
  if(response.status===404){display('ไม่พบเลขคำขอนี้ กรุณาตรวจสอบอีกครั้ง','error');return;}
  if(!response.ok)throw Error();
  const {state,label,checkedAt,appointmentAt,appointmentLabel}=await response.json();
  display(typeof label==='string'?label:Object.hasOwn(receiptStates,state)?receiptStates[state]:'ยังไม่ทราบสถานะ',state);
  if(appointmentLabel){
   guidance.textContent='วันนัด '+appointmentLabel;
   guidance.hidden=false;
  }else if(appointmentAt&&!Number.isNaN(Date.parse(appointmentAt))){
   guidance.textContent='วันนัด '+new Intl.DateTimeFormat('th-TH',{dateStyle:'long',timeStyle:'short',timeZone:'Asia/Bangkok'}).format(new Date(appointmentAt))+' น.';
   guidance.hidden=false;
  }
  if(checkedAt&&!Number.isNaN(Date.parse(checkedAt))){
   checked.textContent='ตรวจเมื่อ '+new Intl.DateTimeFormat('th-TH',{dateStyle:'medium',timeStyle:'short',timeZone:'Asia/Bangkok'}).format(new Date(checkedAt));
   checked.hidden=false;
  }
 }catch{display('ยังตรวจสถานะไม่ได้ กรุณากดตรวจอีกครั้ง','error');}
 finally{delete dialog.dataset.loading;controls.forEach(control=>control.disabled=false);}
}
function saveRequestReceipt(){
 const canvas=document.querySelector('#request-receipt-image:not([hidden])');
 if(!canvas)return;
 canvas.toBlob(blob=>{
  if(!blob)return;
  const url=URL.createObjectURL(blob);
  const link=document.createElement('a');
  link.href=url;link.download='Anemia-Portal-'+requestReceipt+'.png';link.click();
  setTimeout(()=>URL.revokeObjectURL(url),1000);
 },'image/png');
}
function openRequestFromHash(){
 const receipt=new URLSearchParams(location.hash.slice(1)).get('request');
 if(receipt&&receiptPattern.test(receipt))track(receipt,false);
}
function missingAnswerIndex(){
 return questions.findIndex((question,index)=>{
  const answer=answers[index];
  return question.multi?!answer?.length||answer.some(item=>question.points[item]==null):question.points[answer]==null;
 });
}
function reviewMissing(){
 const index=missingAnswerIndex();
 if(index===-1){result();return;}
 reviewingMissing=true;
 step=index;
 question();
}
function classify(){
 const points=questions.flatMap((question,index)=>{
  const answer=answers[index];
  return question.multi?(answer??[]).map(item=>question.points[item]):[question.points[answer]];
 });
 if(points.some(value=>value==null))level='unknown';
 else {
  const total=points.reduce((sum,value)=>sum+value,0);
  level=total>=14?'high':total>=10?'medium':'low';
 }
 result(true);
}
function nextQuestion(){
 if(reviewingMissing){reviewingMissing=false;classify();return;}
 logActivity('step_'+(step+1));
 if(step<questions.length-1){step++;question();return;}
 originalAnswers=structuredClone(answers);
 classify();
 logActivity('first',level);
}
function answerQuestion(index){
 if(step===0&&index===3){
  set(panel(`${backRow()}<h1 class="title">แบบประเมินนี้สำหรับอายุ 15–49 ปี</h1><p>คุณยังเปิดอ่านข้อมูลเกี่ยวกับภาวะโลหิตจางได้</p>${button('เรียนรู้เรื่องโลหิตจาง','learn','book',true)}`));
  return;
 }
 if(!questions[step].multi){answers[step]=index;nextQuestion();return;}
 if(!multipleSymptoms||index>=4){answers[step]=[index];nextQuestion();return;}
 const previous=answers[step]??[];
 if(previous.includes(index))answers[step]=previous.filter(item=>item!==index);
 else answers[step]=[...previous.filter(item=>item<4),index];
 question();
}
const publicData=demoAggregates.map(({region,period,low,medium,high,unknown})=>({
 region,area:regionLabel(region),period,low,medium,high,unknown
}));
let publicArea='',publicPeriod='';
const publicRows=()=>publicData.filter(row=>(!publicArea||row.area===publicArea)&&(!publicPeriod||row.period===publicPeriod));
const percent=(count,total)=>total?(count/total*100).toLocaleString('th-TH',{maximumFractionDigits:1})+'%':'—';
function publicFilter(id,label,key,current){
 const values=[...new Set(publicData.map(row=>row[key]))];
 return `<label for="${id}"><span class="data-filter-label">${icon(key==='period'?'calendar':'hospital')}${label}</span><select id="${id}"><option value="">ทั้งหมด</option>${values.map(value=>`<option ${value===current?'selected':''}>${value}</option>`).join('')}</select></label>`;
}
const publicRiskGroups=[
 {key:'low',label:'เสี่ยงต่ำ',color:riskChartColors.low},
 {key:'medium',label:'เสี่ยงปานกลาง',color:riskChartColors.medium},
 {key:'high',label:'เสี่ยงสูง',color:riskChartColors.high}
];
function publicCharts(sums,valid){
 let offset=0;
 const groups=publicRiskGroups.map(group=>{
  const count=sums[group.key],width=valid?count/valid*100:0;
  const item={...group,count,width,offset,labelValue:`${count.toLocaleString('th-TH')} ครั้ง · ${percent(count,valid)}`};
  offset+=width;
  return item;
 });
 const maximum=Math.max(1,...groups.map(group=>group.count));
 return `<div class="public-charts">
 <section class="card data-chart">
 <div class="admin-chart-title">${box('chart')}<div><h2>จำนวนผลประเมิน</h2><p>เปรียบเทียบจำนวนครั้งในแต่ละระดับ</p></div></div>
 <div class="public-columns">${groups.map(({label,count,color},index)=>`<div class="public-column">
 <div class="public-column-area"><strong>${count.toLocaleString('th-TH')}</strong><span class="public-column-bar" style="--value:${count/maximum*100}%;--tone:${color};--delay:${index*90}ms"></span></div><small>${label}</small></div>`).join('')}</div></section>
 <section class="card data-chart">
 <div class="admin-chart-title">${box('pulse')}<div><h2>สัดส่วนระดับความเสี่ยง</h2><p>ชี้หรือแตะสีบนกราฟเพื่อดูรายละเอียด</p></div></div>
 <div class="public-donut-layout"><div class="donut-callout-stage"><div class="public-donut">
 <svg viewBox="0 0 120 120" role="group" aria-label="กราฟสัดส่วนระดับความเสี่ยง"><circle cx="60" cy="60" r="${donutRadius}" class="public-donut-base"/>
 ${groups.filter(group=>group.width>0).map(({key,label,color,width,offset,labelValue})=>`<path d="${donutArc({offset,width})}" stroke="${color}" tabindex="0" role="button" aria-pressed="false" aria-label="${label} ${labelValue}" data-public-segment="${key}" data-public-label="${label}" data-public-value="${labelValue}"/>`).join('')}</svg>
 <div class="public-donut-center"><strong>${valid.toLocaleString('th-TH')}</strong><span>ครั้งที่สรุประดับได้</span></div></div>
 ${donutCallouts(groups,valid,({key,label,labelValue})=>`data-public-segment="${key}" data-public-label="${label}" data-public-value="${labelValue}"`,count=>count.toLocaleString('th-TH'))}</div>
 <div class="public-donut-detail" id="public-donut-detail" role="status">เลือกสีบนกราฟหรือชื่อระดับ</div>
 </div></section></div>`;
}
let publicSelectedSegment=null, publicTooltipTimer=null, mapTooltipTimer=null;
function highlightPublicSegment(segment){
 const detail=document.querySelector('#public-donut-detail');
 if(!detail)return;
 for(const node of document.querySelectorAll('[data-public-segment]')){
  node.classList.toggle('segment-highlight',Boolean(segment&&node.dataset.publicSegment===segment.dataset.publicSegment));
  node.setAttribute('aria-pressed',String(Boolean(publicSelectedSegment&&node.dataset.publicSegment===publicSelectedSegment.dataset.publicSegment)));
 }
 detail.classList.toggle('is-highlighted',Boolean(segment));
 detail.textContent=segment?`${segment.dataset.publicLabel} · ${segment.dataset.publicValue}`:'เลือกสีบนกราฟหรือชื่อระดับ';
}
function pickPublicSegment(segment){
 clearTimeout(publicTooltipTimer);
 publicSelectedSegment=publicSelectedSegment?.dataset.publicSegment===segment.dataset.publicSegment?null:segment;
 highlightPublicSegment(publicSelectedSegment);
}
for(const [eventName,enter] of [['pointerover',true],['pointerout',false],['focusin',true],['focusout',false]]){
 document.addEventListener(eventName,event=>{
  const segment=event.target.closest?.('[data-public-segment]');
  if(!segment||segment.contains(event.relatedTarget))return;
  clearTimeout(publicTooltipTimer);
  if(eventName==='pointerover')publicTooltipTimer=setTimeout(()=>{if(segment.isConnected)highlightPublicSegment(segment);},180);
  else highlightPublicSegment(enter?segment:publicSelectedSegment);
 });
}
document.addEventListener('keydown',event=>{
 const segment=event.target.closest?.('path[data-public-segment]');
 if(segment&&(event.key==='Enter'||event.key===' ')){event.preventDefault();pickPublicSegment(segment);}
});
function publicTotals(rows){
 return Object.fromEntries(['low','medium','high','unknown'].map(key=>[key,rows.reduce((sum,row)=>sum+row[key],0)]));
}
function publicMap(sums,valid){
 const regionTotals=healthRegionMap.map(shape=>{
  const data=publicTotals(publicData.filter(row=>row.region===shape.region&&(!publicPeriod||row.period===publicPeriod)));
  return {...shape,total:data.low+data.medium+data.high+data.unknown,high:data.high};
 });
 const max=Math.max(1,...regionTotals.map(row=>row.total));
 const selected=regionTotals.find(row=>regionLabel(row.region)===publicArea);
 const total=valid+sums.unknown;
 return `<section class="card data-chart public-region-map">
 <div class="admin-chart-title">${box('hospital')}<div><h2>ผลประเมินตามเขตสุขภาพ</h2><p>ชี้เพื่อดูยอดรวม หรือคลิกเขตเพื่อดูรายละเอียด</p></div></div>
 <div class="region-map-layout"><div class="region-map-stage public-map-stage">
 <svg viewBox="0 0 430 700" role="group" aria-label="เลือกเขตสุขภาพจากแผนที่ประเทศไทย">
 <defs><filter id="public-map-shadow" x="-15%" y="-10%" width="130%" height="130%"><feDropShadow dx="0" dy="4" stdDeviation="2" flood-color="#62416f" flood-opacity=".22"/></filter></defs>
 <g class="region-map-shapes" filter="url(#public-map-shadow)">${regionTotals.map(({region,path,anchor:[x,y],total:count,high})=>`<g data-public-region="${region}" data-map-tooltip="${regionLabel(region)} · ประเมิน ${count.toLocaleString('th-TH')} ครั้ง · เสี่ยงสูง ${high.toLocaleString('th-TH')} ครั้ง" role="button" tabindex="0" aria-label="${regionLabel(region)} ประเมิน ${count.toLocaleString('th-TH')} ครั้ง" aria-pressed="${selected?.region===region}"><path d="${path}" fill-rule="evenodd" fill="hsl(271 36% ${Math.round(88-31*count/max)}%)"/><text x="${x}" y="${y}" aria-hidden="true">${region}</text></g>`).join('')}</g></svg>
 <div class="public-map-tooltip" id="public-map-tooltip" aria-hidden="true" hidden></div></div>
 <div class="region-map-controls public-map-controls"><div class="public-map-heading">${box('chart')}<div><h3>${selected?regionLabel(selected.region):'ทุกเขตสุขภาพ'}</h3>
 <p>${publicPeriod||'ทุกเดือนในชุดตัวอย่าง'} · ข้อมูลจำลอง</p></div></div>
 <dl class="public-map-summary"><div><dt>ประเมินทั้งหมด</dt><dd>${total.toLocaleString('th-TH')} ครั้ง</dd></div><div><dt>สรุประดับได้</dt><dd>${valid.toLocaleString('th-TH')} ครั้ง</dd></div><div><dt>ผลเสี่ยงสูง</dt><dd>${sums.high.toLocaleString('th-TH')} ครั้ง · ${percent(sums.high,valid)}</dd></div></dl>
 <div class="region-map-picker" aria-label="เลือกเขตสุขภาพ">${healthRegionMap.map(({region})=>`<button data-public-region="${region}" aria-label="${regionLabel(region)}" aria-pressed="${selected?.region===region}">${region}</button>`).join('')}</div>
 ${selected?button('ดูทุกเขต','public-clear-region','refresh'):''}
 </div></div>
 <small class="public-map-credit"><a href="https://github.com/GeoThai/boundaries/tree/v2026.10.23" target="_blank" rel="noopener noreferrer">ขอบเขตแผนที่ดัดแปลงจาก GeoThai/boundaries</a> · <a href="https://creativecommons.org/licenses/by/3.0/igo/" target="_blank" rel="noopener noreferrer">CC BY 3.0 IGO</a></small></section>`;
}
function selectPublicRegion(region,fromMap=false){
 publicArea=region?regionLabel(region):'';
 const selector=region?`${fromMap?'g':'button'}[data-public-region="${region}"]`:'#data-area';
 updatePublicSample(selector);
}
for(const [name,enter] of [['pointerover',true],['pointerout',false],['focusin',true],['focusout',false]]){
 document.addEventListener(name,event=>{
  const shape=event.target.closest?.('g[data-map-tooltip]');
  if(!shape||shape.contains(event.relatedTarget))return;
  clearTimeout(mapTooltipTimer);
  const tip=document.querySelector('#public-map-tooltip');
  if(!tip)return;
  if(!enter){tip.hidden=true;return;}
  const show=()=>{if(shape.isConnected){tip.textContent=shape.dataset.mapTooltip;tip.hidden=false;}};
  if(name==='pointerover')mapTooltipTimer=setTimeout(show,180);
  else show();
 });
}
document.addEventListener('keydown',event=>{
 const shape=event.target.closest?.('g[data-public-region]');
 if(shape&&(event.key==='Enter'||event.key===' ')){event.preventDefault();selectPublicRegion(Number(shape.dataset.publicRegion),true);}
});
async function readPublicActivity(filters=new URLSearchParams()){
 const response=await fetch('activity.php?'+filters,{credentials:'omit',cache:'no-store'});
 if(!response.ok)throw new Error('Activity unavailable');
 return response.json();
}
function updatePublicProvinceOptions(form){
 const region=Number(form.elements.region.value);
 form.elements.province.replaceChildren(new Option('ทุกจังหวัด',''),...[...nationalProvinces]
  .filter(([, ,zone])=>!region||zone===region)
  .sort((a,b)=>a[1].localeCompare(b[1],'th'))
  .map(([code,name])=>new Option(name,code)));
}
async function loadPublicActivity(target){
 const section=target.closest('section');
 const sample=section.nextElementSibling;
 const retry=section.querySelector('[data-go="reload-public-activity"]');
 const form=section.querySelector('.public-area-filters');
 const metrics=[...section.querySelectorAll('[data-live-metric]')];
 retry.disabled=true;
  const metricPanel=section.querySelector('.public-live-metrics');
  metricPanel.setAttribute('aria-busy','true');
  section.classList.add('is-report-loading');
 for(const select of form.querySelectorAll('select'))select.disabled=true;
 target.textContent='กำลังโหลดสถิติ';
 try{
  const {region,province}=form.elements;
  const filters=new URLSearchParams({region:region.value,province:province.value});
  const data=await readPublicActivity(filters);
  if(!/^\d{4}-(0[1-9]|1[0-2])$/.test(data.month))throw new Error('Invalid month');
  const month=new Intl.DateTimeFormat('th-TH-u-ca-buddhist',{month:'long',year:'numeric',timeZone:'Asia/Bangkok'}).format(new Date(data.month+'-01T00:00:00Z'));
  metrics.forEach(metric=>{
   const value=data[metric.dataset.liveMetric];
   metric.textContent=Number.isSafeInteger(value)&&value>=10?value.toLocaleString('th-TH')+' ครั้ง':'ต่ำกว่า 10 ครั้ง';
  });
  const area=province.value?province.selectedOptions[0].textContent:region.value?regionLabel(Number(region.value)):'ทั่วประเทศ';
  target.textContent='ประจำเดือน '+month+' · '+area;
  sample.hidden=data.sample_enabled!==true;
  if(!sample.hidden&&target.isConnected)observeBarCharts();
 }catch{
  target.textContent='โหลดสถิติไม่ได้ในขณะนี้ กดโหลดใหม่เพื่อลองอีกครั้ง';
  metrics.forEach(metric=>metric.textContent='—');
  sample.hidden=true;
 }finally{
  retry.disabled=false;
  metricPanel.setAttribute('aria-busy','false');
  section.classList.remove('is-report-loading');
  for(const select of form.querySelectorAll('select'))select.disabled=false;
 }
}
function publicSampleMarkup(){
 const rows=publicRows();
 const sums=publicTotals(rows);
 const valid=sums.low+sums.medium+sums.high;
 publicSelectedSegment=null;clearTimeout(publicTooltipTimer);clearTimeout(mapTooltipTimer);
 return `<h2>ข้อมูลจำลอง</h2>
 <div class="data-toolbar"><div class="data-filters">${publicFilter('data-area','พื้นที่','area',publicArea)}${publicFilter('data-period','เดือน','period',publicPeriod)}</div>${button('ดาวน์โหลด CSV ตัวอย่าง','export-public','download')}</div>
 <div class="grid data-kpis"><section class="card">${box('history')}<strong>${(valid+sums.unknown).toLocaleString('th-TH')}</strong><div>ครั้งที่ทำแบบประเมิน</div></section><section class="card">${box('check')}<strong>${valid.toLocaleString('th-TH')}</strong><div>ครั้งที่สรุประดับได้</div></section><section class="card">${box('refresh')}<strong>${sums.unknown.toLocaleString('th-TH')}</strong><div>ครั้งที่ยังสรุประดับไม่ได้</div></section></div>
 ${publicMap(sums,valid)}
 ${publicCharts(sums,valid)}
 <p class="notice">ร้อยละคำนวณจาก ${valid.toLocaleString('th-TH')} ครั้งที่สรุประดับได้ ในชุดข้อมูลจำลอง</p>`;
}
function updatePublicSample(focusSelector){
 const sample=main.querySelector('.public-sample-data:not([hidden])');
 if(!sample)return;
 const scroll=window.scrollY;
 sample.innerHTML=publicSampleMarkup();
 if(window.scrollY!==scroll)window.scrollTo({top:scroll,behavior:'instant'});
 observeBarCharts();
 if(focusSelector)sample.querySelector(focusSelector)?.focus({preventScroll:true});
}
function openData(){
 set(panel(`${backRow()}<header class="data-hero"><div class="data-hero-art">${box('chart')}</div><div><div class="eyebrow">Open Data</div><h1 class="title data-heading">${icon('chart')}<span>ภาพรวมผลประเมินโลหิตจาง</span></h1><p>ดูสถิติการใช้งานแบบประเมินต้นแบบ แยกตามเขตสุขภาพและจังหวัด</p></div></header>
 <section class="card data-chart public-live-panel">
  <div class="public-live-heading">
   <div class="admin-chart-title">${box('chart')}<div><h2>สถิติการใช้งานจริง</h2><div class="public-loading-status"><span class="report-spinner" aria-hidden="true"></span><p id="public-activity" role="status">กำลังโหลดสถิติ</p></div></div></div>
   ${button('โหลดสถิติใหม่','reload-public-activity','refresh')}
  </div>
  <form class="public-area-filters">
   <label>เขตสุขภาพ<select name="region"><option value="">ทุกเขตสุขภาพ</option>${adminRegions.map(zone=>`<option value="${zone}">${regionLabel(zone)}</option>`).join('')}</select></label>
   <label>จังหวัด<select name="province"><option value="">ทุกจังหวัด</option></select></label>
  </form>
  <div class="public-live-metrics">
   <div>${box('history')}<div><span>เริ่มแบบประเมิน</span><strong data-live-metric="started">—</strong></div></div>
   <div>${box('check')}<div><span>ทำรอบแรกครบ</span><strong data-live-metric="completed">—</strong></div></div>
   <div>${box('refresh')}<div><span>ตอบคำถามทบทวน</span><strong data-live-metric="reviewed">—</strong></div></div>
   <div>${box('hospital')}<div><span>เปิดค้นหาสถานบริการ</span><strong data-live-metric="directory">—</strong></div></div>
  </div>
  <details class="public-data-guide"><summary>${icon('help')}อ่านตัวเลขอย่างไร</summary>
   <ul><li>นับตามรอบประเมินที่เริ่มในเดือนนั้น คนเดียวอาจเริ่มหลายรอบได้</li>
    <li>ยอดเริ่มรวมการทดลองเส้นทางแจ้งเตือน ส่วนยอดทำครบจะนับเมื่อทำแบบประเมินรอบแรกจบ</li>
    <li>ยอดประเทศรวมรอบที่ไม่ระบุจังหวัด ยอดรายเขตและจังหวัดนับตามจังหวัดที่ผู้ใช้เลือก ยอดต่ำกว่า 10 ครั้งแสดงเป็นช่วง</li></ul>
  </details>
 </section>
 <section class="public-sample-data" hidden aria-label="ข้อมูลจำลอง">${publicSampleMarkup()}</section>
 <section class="card data-chart data-cta"><h2 class="data-heading">${box('book')}<span>ดูแลสุขภาพของคุณ</span></h2><div class="actions">${button('ลองประเมินตนเอง','start','check',true)}${button('อ่านความรู้เรื่องโลหิตจาง','learn','book')}</div></section>`,true));
 updatePublicProvinceOptions(main.querySelector('.public-area-filters'));
 loadPublicActivity(document.querySelector('#public-activity'));
}
async function exportPublic(){
 try{if((await readPublicActivity()).sample_enabled!==true){openData();return;}}
 catch{openData();return;}
 const rows=['พื้นที่,เดือน,เสี่ยงต่ำ,เสี่ยงปานกลาง,เสี่ยงสูง,ยังสรุประดับไม่ได้,รวมครั้ง,ประเภทข้อมูล'];
 for(const row of publicRows())rows.push([row.area,row.period,row.low,row.medium,row.high,row.unknown,row.low+row.medium+row.high+row.unknown,'จำลอง'].join(','));
 const href=URL.createObjectURL(new Blob(['\uFEFF'+rows.join('\r\n')],{type:'text/csv;charset=utf-8'}));
 const link=document.createElement('a');
 link.href=href;link.download='anemia-open-data-demo.csv';link.click();
 setTimeout(()=>URL.revokeObjectURL(href),1000);
}
document.addEventListener('change',event=>{
 if(event.target.id==='request-lookup-image-file'){checkRequestImage(event.target);return;}
 if(event.target.matches('input[name=signal-scenario]:checked')){showSignalNext(event.target.value);return;}
 const {id,value}=event.target;
 const areaForm=event.target.closest('.public-area-filters');
 if(areaForm){
  if(event.target.name==='region')updatePublicProvinceOptions(areaForm);
  loadPublicActivity(document.querySelector('#public-activity'));
  return;
 }
 if(id==='data-area')publicArea=value;
 else if(id==='data-period')publicPeriod=value;
 else return;
 updatePublicSample('#'+id);
});
function exportData(){const rows=['assessment_id,version,first_answers,confirmed_answers,displayed_result,request_status'];if(originalAnswers.length)rows.push(`DEMO-001,demo-v1,${originalAnswers.map(value=>Array.isArray(value)?value.join('+'):value).join('|')},${answers.map(value=>Array.isArray(value)?value.join('+'):value).join('|')},${level},${request?stageNames[stage]:''}`);const link=document.createElement('a');const url=URL.createObjectURL(new Blob(['\uFEFF'+rows.join('\n')],{type:'text/csv;charset=utf-8'}));link.href=url;link.download='anemia-demo.csv';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}
function playVoice(key){
 const status=document.querySelector('#voice-status');
 if(!status||!voiceText[key])return;
 if(!('speechSynthesis' in window)){
  status.textContent='เครื่องนี้เล่นเสียงไม่ได้ อ่านคำแนะนำด้านบนได้ค่ะ';
  return;
 }
 const audio=window.speechSynthesis;
 const thai=audio.getVoices().find(item=>item.lang.toLowerCase().startsWith('th'));
 audio.cancel();
 const utterance=new SpeechSynthesisUtterance(voiceText[key]);
 utterance.lang='th-TH';
 if(thai)utterance.voice=thai;
 utterance.rate=.9;
 utterance.onerror=()=>status.textContent='เล่นเสียงไม่สำเร็จ อ่านคำแนะนำด้านบนได้ค่ะ';
 audio.speak(utterance);
}
document.querySelectorAll('[data-icon]').forEach(el=>el.innerHTML=icon(el.dataset.icon));
const publicProfilePhoto=document.querySelector('[data-public-profile-photo]');
if(publicProfilePhoto){
 publicProfilePhoto.addEventListener('load',()=>{
  publicProfilePhoto.hidden=false;
  publicProfilePhoto.previousElementSibling.hidden=true;
 });
 publicProfilePhoto.src='participant-photo.php';
}
const publicMenuToggle=document.querySelector('.public-menu-toggle');
const publicNavigation=document.querySelector('#public-navigation');
if(publicMenuToggle&&publicNavigation){
 const setMenu=open=>{
  publicMenuToggle.setAttribute('aria-expanded',String(open));
  publicNavigation.classList.toggle('nav-collapsed',!open);
 };
 publicMenuToggle.hidden=false;
 setMenu(false);
 publicMenuToggle.addEventListener('click',()=>setMenu(publicMenuToggle.getAttribute('aria-expanded')!=='true'));
 publicNavigation.addEventListener('click',event=>{if(event.target.closest('button,a'))setMenu(false);});
 publicNavigation.closest('.public-header').addEventListener('keydown',event=>{
  if(event.key!=='Escape'||publicMenuToggle.getAttribute('aria-expanded')!=='true')return;
  setMenu(false);publicMenuToggle.focus();
 });
}

document.addEventListener('click',event=>{
 const el=event.target.closest('button,path[data-public-segment],path[data-admin-risk],g[data-public-region]');if(!el)return;
 if(el.dataset.voice){playVoice(el.dataset.voice);return;}
 if(el.dataset.go==='pause'){
  if('speechSynthesis' in window){
   const audio=window.speechSynthesis;
   if(audio.paused){audio.resume();el.innerHTML=icon('pause')+'พักเสียง';}
   else if(audio.speaking){audio.pause();el.innerHTML=icon('play')+'ฟังต่อ';}
  }
  return;
 }
 if('speechSynthesis' in window)window.speechSynthesis.cancel();
 if(el.dataset.calendarShift!==undefined){
  const calendar=el.closest('.request-calendar');
  const offset=Math.max(0,Math.min(6,Number(calendar.dataset.offset)+Number(el.dataset.calendarShift)));
  const shift=el.dataset.calendarShift;
  renderRequestCalendar(calendar,offset);
  (calendar.querySelector('[data-calendar-shift="'+shift+'"]:not(:disabled)')??calendar.querySelector('[data-calendar-day]:not(:disabled)'))?.focus({preventScroll:true});
  return;
 }
 if(el.dataset.calendarDay!==undefined){pickRequestDate(el.closest('.request-calendar'),el.dataset.calendarDay);return;}
 if(el.dataset.selectFacility!==undefined){
  const site=searchableDirectory[Number(el.dataset.selectFacility)];
  if(site)selectedFacility(site);
  return;
 }
 if(el.dataset.facilityRequest!==undefined){
  const site=searchableDirectory[Number(el.dataset.facilityIndex)];
  const kind=el.dataset.facilityRequest;
  if(site&&['appointment','callback'].includes(kind)){
   if(kind==='callback')logActivity('callback_open');
   facilityRequestForm(site,kind);
  }
  return;
 }
 if(el.dataset.answer!==undefined){answerQuestion(Number(el.dataset.answer));return;}
 if(el.dataset.riskAnswer!==undefined){finishRiskReview(Number(el.dataset.riskAnswer));return;}
 if(el.dataset.review!==undefined){answers[4]=Number(el.dataset.review);classify();return;}
 if(el.dataset.article){openArticle(el.dataset.article);return;}
 if(el.dataset.category!==undefined){
  knowledgeCategory=['',...new Set(knowledge.map(article=>article[1]))][Number(el.dataset.category)];
  document.querySelectorAll('[data-category]').forEach(button=>button.setAttribute('aria-pressed',String(button===el)));
  document.querySelector('#knowledge-list').innerHTML=knowledgeCards();
  return;
 }
 if(el.dataset.chartRegion!==undefined){selectChartRegion(Number(el.dataset.chartRegion));return;}
 if(el.dataset.publicRegion){selectPublicRegion(Number(el.dataset.publicRegion),el.tagName.toLowerCase()==='g');return;}
 if(el.dataset.adminRisk){pickAdminRisk(el);return;}
 if(el.dataset.publicSegment){pickPublicSegment(el);return;}
 if(el.dataset.rolloutCode){toggleProvinceReadiness(el.dataset.rolloutCode);return;}
 if(el.dataset.adminView){adminView=el.dataset.adminView;staff();return;}
 if(el.dataset.directoryType!==undefined){
  directoryType=Number(el.dataset.directoryType);
  renderDirectory();
  document.querySelector(`[data-directory-type="${directoryType}"]`)?.focus();
  return;
 }
 switch(el.dataset.go){
 case 'home-voice':openArticle('result');playVoice('article');break;case 'project':project();break;case 'home':home();break;case 'start':chooseAssessmentGroup();break;
 case 'back':goBack();break;
 case 'assessment-public':signalDemo('public');break;
 case 'assessment-student':signalDemo('student');break;
 case 'signal-urgent':signalAlert(true);break;
 case 'signal-check':signalAlert(false);break;
 case 'signal-facilities':logActivity('directory');facilities(false);break;
 case 'signal-callback':logActivity('directory');facilities(true);break;
 case 'review-risk':riskReview();break;
 case 'review-unsure':finishRiskReview();break;
 case 'review-confirm':{
  const message=document.querySelector('#review-message');
  if(questions[reviewIndex].measurements){
   const weight=Number(document.querySelector('#review-weight').value), height=Number(document.querySelector('#review-height').value);
   if(weight<1||weight>500||height<50||height>250){message.textContent='ลองตรวจน้ำหนักและส่วนสูงอีกครั้ง';break;}
   finishRiskReview(weight/(height/100)**2<18.5?0:1);
  }else{
   const selected=[...document.querySelectorAll('[name=review-symptom]:checked')].map(input=>Number(input.value));
   if(!selected.length||(selected.length>1&&selected.some(i=>i>=4))){message.textContent='เลือกอาการ หรือเลือกไม่มีอาการหรือจำไม่ได้เพียงอย่างเดียว';break;}
   finishRiskReview(selected);
  }
  break;
 }
 case 'assessment':if(!activityRound)startActivity();reviewed=false;reviewIndex=null;answers=[];originalAnswers=[];bodyMeasurements=null;reviewingMissing=false;multipleSymptoms=false;request=null;requestKind=null;requestReceipt=null;requestFacilityId=null;stage=0;step=0;question();break;
 case 'skip-measurements':bodyMeasurements=null;answerQuestion(2);break;
 case 'multiple-symptoms':multipleSymptoms=!multipleSymptoms;question();document.querySelector('.symptom-mode')?.focus();break;
 case 'next-multi':if(questions[step].multi&&multipleSymptoms&&answers[step]?.length)nextQuestion();break;
 case 'result':reviewingMissing=false;originalAnswers.length?result():home();break;case 'restart':restart();break;case 'review':review();break;case 'review-missing':reviewMissing();break;
 case 'learn':learn();break;case 'lesson':lesson();break;
 case 'correct':completeTeamLesson();break;
 case 'team-challenge':showTeamChallenge();break;
 case 'team-join':showTeamChallenge(true);break;
 case 'team-share':shareTeamChallenge();break;
 case 'tryagain':document.querySelector('#feedback').textContent='ลองมองหาปุ่มที่เปิดรายชื่อหน่วยบริการ';break;
 case 'facilities':logActivity('directory');facilities(false);break;case 'callback-directory':logActivity('directory');facilities(true);break;
 case 'edit-request-phone':{const form=document.querySelector('#facility-request-form');if(form.dataset.busy!=='1'){delete form.dataset.token;delete form.dataset.otpMode;setRequestVerification(form,false);}break;}
 case 'facility-directory':facilities(false);break;case 'facility-location':locateFacilities();break;
 case 'open-data':openData();break;case 'reload-public-activity':loadPublicActivity(document.querySelector('#public-activity'));break;case 'public-clear-region':selectPublicRegion(null);break;case 'export-public':exportPublic();break;case 'track':track();break;case 'save-request-receipt':saveRequestReceipt();break;case 'refresh-request-status':loadRequestState(document.querySelector('#request-status-dialog'),true);break;case 'staff-entry':if(window.location?.protocol==='http:'||window.location?.protocol==='https:'){window.location.assign('login');}else staff();break;case 'staff':staff();break;case 'admin-clear-region':if(adminScope()?.key==='central'){adminRegion='';staff();document.querySelector('#admin-region')?.focus();}break;case 'admin-reports':if(canReport(adminScope())){adminView='reports';staff();}break;case 'admin-csv':adminExport();break;case 'admin-print':if(canReport(adminScope())&&adminView==='reports')window.print();break;case 'advance':if(canUpdateQueue(adminScope())&&stage<2){stage++;staff();}break;case 'export':if(canUpdateQueue(adminScope()))exportData();break;
 }
});
home();
if(testAppointmentOtp)facilities(false);
else if(teamInvite||new URLSearchParams(location.search).has('team'))showTeamChallenge();
else openRequestFromHash();
window.addEventListener('hashchange',openRequestFromHash);
