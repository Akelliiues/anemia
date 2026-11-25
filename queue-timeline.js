(() => {
 const dialog=document.querySelector('#queue-timeline');
 if(!dialog)return;
 const form=dialog.querySelector('.contact-queue-state');
 const list=dialog.querySelector('ol');
 const historyPanel=list.closest('details');
 const currentPanel=dialog.querySelector('[data-timeline-current]');
 const message=dialog.querySelector('[data-timeline-message]');
 const states=form.elements.state;
 const labels=new Map([...states.options].filter(option=>option.value).map(option=>[option.value,option.textContent]));
 const dateField=dialog.querySelector('[data-workflow-date]');
 const dateInput=dateField.querySelector('input');
 const roundField=dialog.querySelector('[data-workflow-round]');
 const calendar=roundField.querySelector('details');
 const calendarBody=calendar.querySelector('[data-round-calendar]');
 const dayInput=form.elements.appointment_day;
 const slotInputs=[...roundField.querySelectorAll('input[type=radio]')];
 const roundMessage=roundField.querySelector('[data-round-message]');
 const thaiDate=new Intl.DateTimeFormat('th-TH-u-ca-buddhist',{dateStyle:'long',timeZone:'Asia/Bangkok'});
 const thaiMonth=new Intl.DateTimeFormat('th-TH-u-ca-buddhist',{month:'long',year:'numeric',timeZone:'Asia/Bangkok'});
 const today=new Intl.DateTimeFormat('sv-SE',{year:'numeric',month:'2-digit',day:'2-digit',timeZone:'Asia/Bangkok'}).format(new Date());
 let monthOffset=0;
 let roundOperation=0;
 const submit=form.querySelector('button');
 let trigger;
 let operation=0;

 function drawCalendar(){
  const [year,month]=today.split('-').map(Number);
  const first=new Date(Date.UTC(year,month-1+monthOffset,1));
  const days=new Date(Date.UTC(year,month+monthOffset,0)).getUTCDate();
  calendarBody.innerHTML=`<div class="request-calendar-nav"><button type="button" data-month-shift="-1" aria-label="เดือนก่อนหน้า" ${monthOffset===0?'disabled':''}>‹</button><strong>${thaiMonth.format(first)}</strong><button type="button" data-month-shift="1" aria-label="เดือนถัดไป">›</button></div>
   <div class="request-calendar-grid">${['อา.','จ.','อ.','พ.','พฤ.','ศ.','ส.'].map(day=>`<span>${day}</span>`).join('')}${'<span></span>'.repeat(first.getUTCDay())}${Array.from({length:days},(_,index)=>{
    const date=new Date(Date.UTC(year,month-1+monthOffset,index+1));
    const day=date.toISOString().slice(0,10);
    return `<button type="button" data-round-day="${day}" aria-label="${thaiDate.format(date)}" aria-pressed="${day===dayInput.value}" ${day<today?'disabled':''}>${index+1}</button>`;
   }).join('')}</div>`;
 }
 async function loadRounds(day){
  const id=++roundOperation;
  const counts=[...roundField.querySelectorAll('[data-round-count]')];
  counts.forEach(count=>count.textContent='');
  roundMessage.textContent='กำลังโหลดยอดของแต่ละรอบ';
  try{
   const body=new FormData();
   for(const name of ['csrf','receipt'])body.set(name,form.elements[name].value);
   body.set('round_day',day);
   const response=await fetch(location.href,{method:'POST',body,cache:'no-store'});
   if(!response.ok||response.redirected)throw new Error();
   const rounds=await response.json();
   if(id!==roundOperation)return;
   counts.forEach(count=>{
    const {booked,checkedIn}=rounds[count.dataset.roundCount];
    count.textContent=`นัดแล้ว ${booked} คน · เช็กอินแล้ว ${checkedIn} คน`;
   });
   roundMessage.textContent='';
  }catch{
   if(id===roundOperation)roundMessage.textContent='โหลดยอดไม่ได้ ลองเลือกวันอีกครั้ง';
  }
 }
 calendarBody.addEventListener('click',event=>{
  const button=event.target.closest('button');
  if(!button)return;
  if(button.dataset.monthShift){monthOffset+=Number(button.dataset.monthShift);drawCalendar();return;}
  const day=button.dataset.roundDay;
  if(!day)return;
  dayInput.value=day;
  roundField.querySelector('[data-round-date-label]').textContent=thaiDate.format(new Date(day+'T00:00:00+07:00'));
  drawCalendar();
  calendar.open=false;
  calendar.querySelector('summary').focus({preventScroll:true});
  loadRounds(day);
 });

 function addEvent(label,kind,detail=''){
  const item=document.createElement('li');
  item.dataset.kind=kind;
  if(kind==='current')item.setAttribute('aria-current','step');
  const title=document.createElement('strong');
  title.textContent=label;
  item.append(title);
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
    const errors={403:'กรุณาเข้าสู่ระบบใหม่',404:'ไม่พบคำขอนี้',409:'สถานะเปลี่ยนหรือใช้ขั้นตอนนี้ไม่ได้ กรุณาเปิดไทม์ไลน์อีกครั้ง',422:'กรุณาระบุวันและเวลาให้ครบ'};
    throw new Error(errors[response.status]??'ดำเนินการไม่สำเร็จ กรุณาลองอีกครั้ง');
   }
   if(response.redirected)throw new Error('กรุณาเข้าสู่ระบบใหม่');
   const data=await response.json();
   if(saving)button.querySelector('span').textContent=labels.get(data.state);
   if(id!==operation||!dialog.open)return;
   list.replaceChildren();
   const latest=data.history.at(-1);
   const pending=data.state==='pending'&&!latest;
   currentPanel.textContent=[labels.get(data.state),data.appointment_label,data.next_contact_at&&'ติดตาม '+data.next_contact_at].filter(Boolean).join(' · ');
   currentPanel.hidden=false;
   historyPanel.hidden=false;
   addEvent('ส่งคำขอแล้ว',pending?'current':'done',data.submitted_at+(pending?' · รอเจ้าหน้าที่รับเรื่อง':''));
   data.history.forEach((event,index)=>{
    const current=index===data.history.length-1;
    addEvent(labels.get(event.state),current?'current':'done',event.changed_at+' · '+event.actor+(current?' · สถานะปัจจุบัน':''));
   });
   if(!latest&&!pending)addEvent(labels.get(data.state),'current','สถานะปัจจุบัน · ไม่มีวันเวลาในประวัติเดิม');
   if(data.appointment_label)addEvent('วันนัด','future',data.appointment_label);
   if(data.next_contact_at)addEvent('กำหนดติดตาม','future',data.next_contact_at);
   if(data.state!=='closed'){
    const keys=[...labels.keys()];
    const upcoming=keys.slice(keys.indexOf(data.state)+1).filter(key=>!['closed','retry_contact'].includes(key)&&!data.history.some(event=>event.state===key));
    if(upcoming.length)addEvent('ขั้นถัดไปที่ยังไม่มีบันทึก','future',upcoming.map(key=>labels.get(key)).join(' · '));
   }
   form.elements.previous.value=data.state;
   for(const option of states.options){
    if(!option.value)continue;
    const action=data.actions[option.value];
    option.disabled=option.hidden=!action;
    if(action)option.textContent=action[0];
   }
   states.value='';
   states.required=true;
   dateInput.value='';
   dateField.hidden=true;
   dateInput.required=false;
   form.hidden=!Object.keys(data.actions).length;
   message.textContent='';
   if(saving)dialog.close();
  }catch(error){
   if(id===operation&&dialog.open)message.textContent=error instanceof SyntaxError?'โหลดข้อมูลไม่ได้ กรุณาลองอีกครั้ง':error.message;
  }finally{
   if(id===operation)submit.disabled=false;
  }
 }

 document.querySelectorAll('[data-queue-timeline]').forEach(button=>button.addEventListener('click',()=>{
  trigger=button;
  form.hidden=true;
  dateField.hidden=true;
  dateInput.required=false;
  ++roundOperation;
  roundField.hidden=true;
  dayInput.value='';
  slotInputs.forEach(input=>{input.checked=false;input.required=false;});
  roundMessage.textContent='';
  roundField.querySelector('[data-round-date-label]').textContent='เลือกวันนัด';
  roundField.querySelectorAll('[data-round-count]').forEach(count=>count.textContent='');
  monthOffset=0;
  calendar.open=false;
  drawCalendar();
  list.replaceChildren();
  historyPanel.open=false;
  historyPanel.hidden=true;
  currentPanel.hidden=true;
  const receipt=button.dataset.queueTimeline;
  form.elements.receipt.value=receipt;
  dialog.querySelector('[data-timeline-receipt]').textContent=receipt;
  dialog.showModal();
  request(button,false);
 }));
 states.addEventListener('change',()=>{
  historyPanel.open=false;
  roundField.hidden=states.value!=='scheduled';
  slotInputs.forEach(input=>input.required=!roundField.hidden);
  dateField.hidden=!['retry_contact','follow_up'].includes(states.value);
  dateInput.required=!dateField.hidden;
 });
 calendar.addEventListener('toggle',()=>{if(calendar.open)historyPanel.open=false;});
 historyPanel.addEventListener('toggle',()=>{if(historyPanel.open)calendar.open=false;});
 form.addEventListener('submit',event=>{
  event.preventDefault();
  if(states.value==='scheduled'&&!dayInput.value){roundMessage.textContent='กรุณาเลือกวันนัด';calendar.open=true;calendar.querySelector('summary').focus();return;}
  if(!submit.disabled)request(trigger,true);
 });
 dialog.addEventListener('close',()=>trigger?.focus({preventScroll:true}));
})();
