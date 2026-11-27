function initializeQueueTimeline() {
 const dialog=document.querySelector('#queue-timeline');
 if(!dialog)return;
 const form=dialog.querySelector('.contact-queue-state');
 const list=dialog.querySelector('ol');
 const historyPanel=list.closest('details');
 const currentPanel=dialog.querySelector('[data-timeline-current]');
 const currentLabel=currentPanel.querySelector('[data-current-label]');
 const currentDetail=currentPanel.querySelector('[data-current-detail]');
 const contactPanel=dialog.querySelector('[data-timeline-contact]');
 const message=dialog.querySelector('[data-timeline-message]');
 const states=form.elements.state;
 const labels=new Map([...states.options].filter(option=>option.value).map(option=>[option.value,option.textContent]));
 const roundField=dialog.querySelector('[data-workflow-round]');
 const confirmationField=dialog.querySelector('[data-confirmation-fields]');
 function showConfirmation(enabled){
  confirmationField.hidden=!enabled;
  for(const field of confirmationField.querySelectorAll('select,input')){
   field.disabled=!enabled;
   field.required=enabled;
   if(!enabled)field.value='';
  }
 }
 const slotField=roundField.querySelector('[data-round-slots]');
 const contactTime=roundField.querySelector('[data-contact-time]');
 const timeInputs=[...contactTime.querySelectorAll('select')];
 const callbackField=roundField.querySelector('[data-callback-times]');
 const callbackInputs=[...callbackField.querySelectorAll('input')];
 const closedDays=new Set(callbackField.dataset.closedDays.split(','));
 const calendar=roundField.querySelector('details');
 const calendarBody=calendar.querySelector('[data-round-calendar]');
 const dayInput=form.elements.appointment_day;
 const slotInputs=[...roundField.querySelectorAll('input[type=radio]')];
 const roundMessage=roundField.querySelector('[data-round-message]');
 const thaiDate=new Intl.DateTimeFormat('th-TH-u-ca-buddhist',{dateStyle:'long',timeZone:'Asia/Bangkok'});
 const thaiMonth=new Intl.DateTimeFormat('th-TH-u-ca-buddhist',{month:'long',year:'numeric',timeZone:'Asia/Bangkok'});
 const today=new Intl.DateTimeFormat('sv-SE',{year:'numeric',month:'2-digit',day:'2-digit',timeZone:'Asia/Bangkok'}).format(new Date());
 const thaiClock=new Intl.DateTimeFormat('en-GB',{hour:'2-digit',minute:'2-digit',hourCycle:'h23',timeZone:'Asia/Bangkok'});
 let monthOffset=0;
 let roundOperation=0;
 let defaultAppointmentDay=today;
 const roundCounts=[...roundField.querySelectorAll('[data-round-count]')];
 const emptyCount='จำนวน – คน';
 const submit=form.querySelector('button');
 let trigger;
 let operation=0;

 function isCallbackDay(day){
  const weekday=new Date(day+'T00:00:00Z').getUTCDay();
  return day>=today&&weekday>0&&weekday<6&&!closedDays.has(day);
 }
 function callbackTimeAvailable(day,time){
  return isCallbackDay(day)&&(day!==today||time>thaiClock.format(new Date()));
 }
 function refreshCallbackTimes(){
  for(const input of callbackInputs){
   input.disabled=!callbackTimeAvailable(dayInput.value,input.value);
   if(input.disabled)input.checked=false;
  }
 }
 function drawCalendar(){
  const [year,month]=today.split('-').map(Number);
  const first=new Date(Date.UTC(year,month-1+monthOffset,1));
  const days=new Date(Date.UTC(year,month+monthOffset,0)).getUTCDate();
  calendarBody.innerHTML=`<div class="request-calendar-nav"><button type="button" data-month-shift="-1" aria-label="เดือนก่อนหน้า" ${monthOffset===0?'disabled':''}>‹</button><strong>${thaiMonth.format(first)}</strong><button type="button" data-month-shift="1" aria-label="เดือนถัดไป">›</button></div>
   <div class="request-calendar-grid">${['อา.','จ.','อ.','พ.','พฤ.','ศ.','ส.'].map(day=>`<span>${day}</span>`).join('')}${'<span></span>'.repeat(first.getUTCDay())}${Array.from({length:days},(_,index)=>{
    const date=new Date(Date.UTC(year,month-1+monthOffset,index+1));
    const day=date.toISOString().slice(0,10);
    const disabled=day<today||(states.value==='retry_contact'&&!callbackInputs.some(input=>callbackTimeAvailable(day,input.value)));
    return `<button type="button" data-round-day="${day}" aria-label="${thaiDate.format(date)}" aria-pressed="${day===dayInput.value}" ${disabled?'disabled':''}>${index+1}</button>`;
   }).join('')}</div>`;
 }
 async function loadRounds(day){
  const id=++roundOperation;
  roundCounts.forEach(count=>count.textContent=emptyCount);
  roundMessage.textContent='กำลังโหลดยอดของแต่ละรอบ';
  try{
   const body=new FormData();
   for(const name of ['csrf','receipt'])body.set(name,form.elements[name].value);
   body.set('round_day',day);
   const response=await fetch(location.href,{method:'POST',body,cache:'no-store'});
   if(!response.ok||response.redirected)throw new Error();
   const rounds=await response.json();
   if(id!==roundOperation)return;
   roundCounts.forEach(count=>{
    const {booked}=rounds[count.dataset.roundCount];
    count.textContent=`จำนวน ${booked} คน · ลงนัดแล้ว`;
   });
   roundMessage.textContent='';
  }catch{
   if(id===roundOperation)roundMessage.textContent='โหลดยอดไม่ได้ ลองเลือกวันอีกครั้ง';
  }
 }
 function showCalendarDay(day){
  dayInput.value=day;
  const [year,month]=day.split('-').map(Number);
  const [currentYear,currentMonth]=today.split('-').map(Number);
  monthOffset=(year-currentYear)*12+month-currentMonth;
  roundField.querySelector('[data-round-date-label]').textContent=thaiDate.format(new Date(day+'T00:00:00+07:00'));
  drawCalendar();
 }
 calendarBody.addEventListener('click',event=>{
  const button=event.target.closest('button');
  if(!button)return;
  if(button.dataset.monthShift){monthOffset+=Number(button.dataset.monthShift);drawCalendar();return;}
  const day=button.dataset.roundDay;
  if(!day)return;
  showCalendarDay(day);
  calendar.open=false;
  calendar.querySelector('summary').focus({preventScroll:true});
  if(states.value==='scheduled')loadRounds(day);
  else {
   roundMessage.textContent='';
   if(states.value==='retry_contact')refreshCallbackTimes();
  }
 });

 function addEvent(label,kind,detail=''){
  const item=document.createElement('li');
  item.dataset.kind=kind;
  if(kind==='current')item.setAttribute('aria-current','step');
  const title=document.createElement('strong');
  title.textContent=label;
  item.append(title);
  if(kind==='current'){
   const marker=document.createElement('span');
   marker.className='queue-current-marker';
   marker.textContent='ตอนนี้';
   item.append(marker);
  }
  if(detail){
   const text=document.createElement('small');
   text.textContent=detail;
   item.append(text);
  }
  list.append(item);
 }

 async function request(button,saving){
  const id=++operation;
  const body=new FormData(form);
  if(!saving){
   body.delete('state');
   body.delete('previous');
  }
  submit.disabled=true;
  message.textContent=saving?'กำลังบันทึกสถานะ':'กำลังโหลดไทม์ไลน์';
  try{
   const response=await fetch(location.href,{method:'POST',body,cache:'no-store'});
   if(!response.ok){
    const errors={403:'กรุณาเข้าสู่ระบบใหม่',404:'ไม่พบคำขอนี้',409:'สถานะเปลี่ยนหรือใช้ขั้นตอนนี้ไม่ได้ กรุณาเปิดไทม์ไลน์อีกครั้ง',422:states.value==='test_completed'?'กรุณาตรวจผลตรวจและวันที่ตรวจอีกครั้ง':'กรุณาตรวจวันและเวลาที่เลือกอีกครั้ง'};
    throw new Error(errors[response.status]??'ดำเนินการไม่สำเร็จ กรุณาลองอีกครั้ง');
   }
   if(response.redirected)throw new Error('กรุณาเข้าสู่ระบบใหม่');
   const data=await response.json();
   if(saving){
    button.querySelector('span').textContent=labels.get(data.state);
    document.dispatchEvent(new Event('anemia:queue-changed'));
   }
   if(id!==operation||!dialog.open)return;
   defaultAppointmentDay=[data.appointment_at?.slice(0,10),data.preferred_date,today].find(day=>day&&day>=today);
   list.replaceChildren();
   const latest=data.history.at(-1);
   const pending=data.state==='pending'&&!latest;
   currentPanel.dataset.state=data.state;
   currentLabel.textContent=labels.get(data.state)??'ยังไม่ทราบสถานะ';
   currentDetail.textContent=[data.appointment_label,data.confirmation_label,data.next_contact_label&&'ติดตาม '+data.next_contact_label].filter(Boolean).join(' · ');
   currentDetail.hidden=!currentDetail.textContent;
   if(contactPanel){
    contactPanel.querySelector('span').textContent=[data.contact_name,data.facility].filter(Boolean).join(' · ');
    const phone=contactPanel.querySelector('a');
    phone.textContent=data.phone??'';
    phone.href='tel:'+(data.phone??'').replace(/[^0-9+]/g,'');
    contactPanel.hidden=false;
   }
   historyPanel.hidden=false;
   addEvent(pending?currentLabel.textContent:'ส่งคำขอแล้ว',pending?'current':'done',data.submitted_at);
   data.history.forEach((event,index)=>{
    const current=index===data.history.length-1&&event.state===data.state;
    addEvent(labels.get(event.state),current?'current':'done',event.changed_at+' · '+event.actor);
   });
   if(!pending&&latest?.state!==data.state)addEvent(labels.get(data.state),'current','ไม่มีวันเวลาในประวัติเดิม');
   if(data.appointment_label)addEvent('วันนัด','future',data.appointment_label);
   if(data.next_contact_label)addEvent('กำหนดติดตาม','future',data.next_contact_label);
   if(data.state!=='closed'){
    const keys=[...labels.keys()];
    const upcoming=keys.slice(keys.indexOf(data.state)+1).filter(key=>data.actions[key]);
    if(upcoming.length)addEvent('ขั้นที่เลือกได้ต่อจากนี้','future',upcoming.map(key=>data.actions[key][0]).join(' · '));
   }
   form.elements.previous.value=data.state;
   for(const option of states.options){
    if(!option.value)continue;
    const action=data.actions[option.value];
    option.disabled=option.hidden=!action;
    if(action)option.textContent=action[0];
   }
   states.value='';
   showConfirmation(false);
   states.required=true;
   form.hidden=!Object.keys(data.actions).length;
   message.textContent='';
   if(saving)dialog.close();
  }catch(error){
   if(id===operation&&dialog.open){
    if(!saving)currentLabel.textContent='โหลดสถานะไม่ได้';
    message.textContent=error instanceof SyntaxError?'โหลดข้อมูลไม่ได้ กรุณาลองอีกครั้ง':error.message;
   }
  }finally{
   if(id===operation)submit.disabled=false;
  }
 }

 document.querySelector('.contact-queue-table')?.addEventListener('click',event=>{
  if(event.target.closest('a,button,input,select,textarea,label') || window.getSelection()?.toString())return;
  event.target.closest('.queue-clickable-row')?.querySelector('[data-queue-timeline]')?.click();
 });
 document.querySelectorAll('[data-queue-timeline]').forEach(button=>button.addEventListener('click',()=>{
  trigger=button;
  form.hidden=true;
  ++roundOperation;
  roundField.hidden=true;
  showConfirmation(false);
  dayInput.value='';
  slotInputs.forEach(input=>{input.checked=false;input.required=false;});
  timeInputs.forEach(input=>{input.value='';input.required=false;});
  callbackInputs.forEach(input=>{input.checked=false;input.required=false;input.disabled=true;});
  roundMessage.textContent='';
  roundField.querySelector('[data-round-date-label]').textContent='เลือกวันนัด';
  roundCounts.forEach(count=>count.textContent=emptyCount);
  monthOffset=0;
  calendar.open=false;
  drawCalendar();
  list.replaceChildren();
  historyPanel.open=false;
  historyPanel.hidden=true;
  delete currentPanel.dataset.state;
  currentLabel.textContent='กำลังโหลดสถานะ…';
  currentDetail.hidden=true;
  if(contactPanel)contactPanel.hidden=true;
  const receipt=button.dataset.queueTimeline;
  form.elements.receipt.value=receipt;
  dialog.querySelector('[data-timeline-receipt]').textContent=receipt;
  dialog.showModal();
  request(button,false);
 }));
 states.addEventListener('change',()=>{
  showConfirmation(states.value==='test_completed');
  historyPanel.open=false;
  const appointment=states.value==='scheduled';
  const callback=states.value==='retry_contact';
  const followUp=states.value==='follow_up';
  roundField.hidden=!appointment&&!callback&&!followUp;
  slotField.hidden=!appointment;
  contactTime.hidden=!followUp;
  callbackField.hidden=!callback;
  slotInputs.forEach(input=>input.required=appointment);
  timeInputs.forEach(input=>input.required=followUp);
  callbackInputs.forEach(input=>{input.required=callback;input.disabled=!callback;});
  ++roundOperation;
  roundMessage.textContent='';
  if(!dayInput.value)roundField.querySelector('[data-round-date-label]').textContent=appointment?'เลือกวันนัด':'เลือกวันที่ติดต่อ';
  if(appointment){
   if(!dayInput.value)showCalendarDay(defaultAppointmentDay);
   loadRounds(dayInput.value);
  }
  if(callback){
   const date=new Date((dayInput.value&&dayInput.value>=today?dayInput.value:today)+'T00:00:00Z');
   let day=date.toISOString().slice(0,10);
   while(!callbackInputs.some(input=>callbackTimeAvailable(day,input.value))){
    date.setUTCDate(date.getUTCDate()+1);
    day=date.toISOString().slice(0,10);
   }
   showCalendarDay(day);
   refreshCallbackTimes();
  }else drawCalendar();
 });
 calendar.addEventListener('toggle',()=>{if(calendar.open)historyPanel.open=false;});
 historyPanel.addEventListener('toggle',()=>{if(historyPanel.open)calendar.open=false;});
 form.addEventListener('submit',event=>{
  event.preventDefault();
  if(!roundField.hidden&&!dayInput.value){roundMessage.textContent='กรุณาเลือกวันที่';calendar.open=true;calendar.querySelector('summary').focus();return;}
  if(!submit.disabled)request(trigger,true);
 });
 function onBackdrop(event){
  if(event.target!==dialog)return false;
  const {left,right,top,bottom}=dialog.getBoundingClientRect();
  return event.clientX<left||event.clientX>right||event.clientY<top||event.clientY>bottom;
 }
 let startedOnBackdrop=false;
 dialog.addEventListener('pointerdown',event=>{startedOnBackdrop=onBackdrop(event);});
 dialog.addEventListener('click',event=>{
  if(startedOnBackdrop&&onBackdrop(event))dialog.close();
  startedOnBackdrop=false;
 });
 dialog.addEventListener('close',()=>{
  startedOnBackdrop=false;
  trigger?.focus({preventScroll:true});
 });
 document.querySelector('[data-queue-open]')?.click();
}

(() => {
 const status=document.querySelector('#queue-navigation-status');
 if(!status)return;
 const tabLinks='.queue-tabs a, .queue-attention, .staff-queue-tabs a, [data-queue-pager] a';
 let queue=document.querySelector('#queue-content');
 let pending;
 initializeQueueTimeline();

 async function loadQueue(url,addHistory,link){
  pending?.abort();
  const controller=new AbortController();
  pending=controller;
  queue.setAttribute('aria-busy','true');
  status.hidden=true;
  try{
   const response=await fetch(url,{cache:'no-store',signal:controller.signal});
   if(response.redirected){location.assign(response.url);return;}
   if(!response.ok)throw new Error('Queue request failed');
   const html=await response.text();
   if(controller.signal.aborted)return;
   const replacement=new DOMParser().parseFromString(html,'text/html').querySelector('#queue-content');
   if(!replacement)throw new Error('Queue content missing');
   const hadFocus=link===document.activeElement;
   queue.replaceWith(replacement);
   queue=document.querySelector('#queue-content');
   if(addHistory&&url.href!==location.href)history.pushState(null,'',url);
   initializeQueueTimeline();
   if(hadFocus){
    const target=[...queue.querySelectorAll(tabLinks)].find(item=>item.href===url.href)
     ?? queue.querySelector('.queue-status-tabs [aria-current], .staff-queue-tabs [aria-current]');
    target?.focus({preventScroll:true});
   }
  }catch{
   if(controller.signal.aborted)return;
   if(!addHistory){location.reload();return;}
   status.textContent='โหลดคิวไม่ได้ กรุณากดแท็บอีกครั้ง';
   status.hidden=false;
  }finally{
   if(pending===controller){queue.removeAttribute('aria-busy');pending=undefined;}
  }
 }
 document.addEventListener('click',event=>{
  const link=event.target.closest(tabLinks);
  if(!link||!queue.contains(link)||event.defaultPrevented||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
  event.preventDefault();
  loadQueue(new URL(link.href),true,link);
 },true);
 window.addEventListener('popstate',()=>loadQueue(new URL(location.href),false));
})();
