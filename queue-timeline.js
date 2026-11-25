(() => {
 const dialog=document.querySelector('#queue-timeline');
 if(!dialog)return;
 const form=dialog.querySelector('.contact-queue-state');
 const list=dialog.querySelector('ol');
 const message=dialog.querySelector('[data-timeline-message]');
 const states=form.elements.state;
 const labels=new Map([...states.options].map(option=>[option.value,option.textContent]));
 const submit=form.querySelector('button');
 let trigger;
 let operation=0;

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
    const errors={403:'กรุณาเข้าสู่ระบบใหม่',404:'ไม่พบคำขอนี้',409:'สถานะเปลี่ยนแล้ว กรุณาปิดและเปิดไทม์ไลน์อีกครั้ง'};
    throw new Error(errors[response.status]??'ดำเนินการไม่สำเร็จ กรุณาลองอีกครั้ง');
   }
   if(response.redirected)throw new Error('กรุณาเข้าสู่ระบบใหม่');
   const data=await response.json();
   if(saving)button.querySelector('span').textContent=labels.get(data.state);
   if(id!==operation||!dialog.open)return;
   list.replaceChildren();
   const latest=data.history.at(-1);
   const pending=data.state==='pending'&&!latest;
   addEvent('ส่งคำขอแล้ว',pending?'current':'done',data.submitted_at+(pending?' · รอเจ้าหน้าที่รับเรื่อง':''));
   data.history.forEach((event,index)=>{
    const current=index===data.history.length-1;
    addEvent(labels.get(event.state),current?'current':'done',event.changed_at+' · '+event.actor+(current?' · สถานะปัจจุบัน':''));
   });
   if(!latest&&!pending)addEvent(labels.get(data.state),'current','สถานะปัจจุบัน · ไม่มีวันเวลาในประวัติเดิม');
   if(data.state!=='closed'){
    const keys=[...labels.keys()];
    keys.slice(keys.indexOf(data.state)+1).filter(key=>key!=='closed'&&!data.history.some(event=>event.state===key))
     .forEach(key=>addEvent(labels.get(key),'future','ยังไม่มีบันทึก'));
   }
   form.elements.previous.value=data.state;
   states.value=data.state;
   form.hidden=false;
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
  list.replaceChildren();
  const receipt=button.dataset.queueTimeline;
  form.elements.receipt.value=receipt;
  dialog.querySelector('[data-timeline-receipt]').textContent=receipt;
  dialog.showModal();
  request(button,false);
 }));
 form.addEventListener('submit',event=>{
  event.preventDefault();
  if(!submit.disabled)request(trigger,true);
 });
 dialog.addEventListener('close',()=>trigger?.focus({preventScroll:true}));
})();
