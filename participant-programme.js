(() => {
 const lesson=document.querySelector('.programme-lesson');
 if(!lesson)return;
 const video=lesson.querySelector('video');
 const play=lesson.querySelector('[data-lesson-play]');
 const watch=lesson.querySelector('[data-lesson-watch]');
 const next=lesson.querySelector('[data-lesson-next]');
 const count=lesson.querySelector('[data-lesson-count]');
 const progress=lesson.querySelector('[data-lesson-progress]');
 const reading=lesson.querySelector('[data-lesson-reading]');
 const read=lesson.querySelector('[data-lesson-read]');
 const questions=[...lesson.querySelectorAll('[data-lesson-question]')];
 const submit=lesson.querySelector('[type=submit]');
 let ready=false;
 let index=0;
 function showQuestion(){
  questions.forEach((question,i)=>{question.hidden=i!==index;});
  count.textContent=(index+1)+' / '+questions.length;
  next.hidden=index===questions.length-1;
  submit.hidden=!next.hidden;
  update();
 }
 function update(){
  const correct=questions.every(question=>question.querySelector(':checked')?.value===question.dataset.correct);
  next.disabled=questions[index].querySelector(':checked')?.value!==questions[index].dataset.correct;
  submit.disabled=!ready||!correct;
 }
 function complete(message){
  ready=true;
  progress.textContent=message;
  update();
 }
 read.hidden=false;
 play.hidden=false;
 watch.hidden=false;
 play.addEventListener('click',()=>{
  play.hidden=true;
  video.play().catch(()=>{if(!video.error)progress.textContent='แตะปุ่มเล่นบนวิดีโอเพื่อเริ่มดู';});
 });
 video.addEventListener('play',()=>{play.hidden=true;});
 next.addEventListener('click',()=>{
  if(next.disabled||index>=questions.length-1)return;
  index++;
  showQuestion();
  questions[index].querySelector('input').focus({preventScroll:true});
 });
 read.addEventListener('click',()=>complete('อ่านสรุปแล้ว ลองตอบคำถามท้ายบทได้เลย'));
 function checkVideo(){
  if(!Number.isFinite(video.duration)||video.duration<=0)return;
  let played=0;
  for(let range=0;range<video.played.length;range++)played+=video.played.end(range)-video.played.start(range);
  watch.value=Math.min(100,Math.round(played/video.duration*100));
  if(ready)return;
  if(played/video.duration>=.9)complete('ดูคลิปครบตามเกณฑ์แล้ว ลองตอบคำถามท้ายบทได้เลย');
  else if(video.ended)progress.textContent='ลองดูช่วงที่ยังไม่ได้ดู หรือเลือกอ่านสรุปบทเรียน';
 }
 video.addEventListener('timeupdate',checkVideo);
 video.addEventListener('ended',checkVideo);
 video.addEventListener('error',()=>{
  if(ready)return;
  progress.textContent='เปิดคลิปไม่ได้ อ่านสรุปแล้วตอบคำถามแทนได้';
  reading.open=true;
 });
 questions.forEach(question=>question.addEventListener('change',()=>{
  const correct=question.querySelector(':checked').value===question.dataset.correct;
  const answer=question.querySelector('[data-lesson-answer]');
  answer.hidden=false;
  answer.dataset.correct=String(correct);
  update();
 }));
 showQuestion();
})();
