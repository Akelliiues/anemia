for(const link of document.querySelectorAll('[data-school-qr]')){
 const target=link.nextElementSibling;
 link.parentElement.addEventListener('toggle',()=>{
  if(!link.parentElement.open||target.firstChild)return;
  const qr=qrcode(0,'M');
  qr.addData(link.href);
  qr.make();
  target.innerHTML=qr.createImgTag(4,16,'QR สำหรับเข้าร่วมรุ่นกิจกรรม');
 });
}

for (const button of document.querySelectorAll('[data-copy-school-link]')) {
 button.addEventListener('click', async () => {
  const link = button.parentElement.querySelector('[data-school-qr]');
  try {
   await navigator.clipboard.writeText(link.href);
   button.textContent = 'คัดลอกแล้ว';
  } catch {
   button.textContent = 'คัดลอกอัตโนมัติไม่ได้';
   link.focus();
  }
 });
}

const schoolParticipationDialog = document.querySelector('#school-participation-dialog');
if (schoolParticipationDialog) {
 const form = schoolParticipationDialog.querySelector('form');
 const years = schoolParticipationDialog.querySelector('[data-school-dialog-years]');
 const copy = {
  'school-withdraw': ['ถอนโรงเรียนออกจากโครงการ', 'โรงเรียนจะกลับไปอยู่ในรายการที่เพิ่มเข้าร่วมได้', 'ยืนยันถอน'],
  'school-archive': ['ยุติการเข้าร่วม', 'ระบบจะหยุดรับนักเรียนใหม่ ห้องเรียน QR และประวัติของทุกปีจะยังอยู่', 'ยืนยันยุติการเข้าร่วม'],
  'school-reopen': ['เปิดใช้งานโรงเรียนอีกครั้ง', 'ใช้ทะเบียนและ QR เดิม แล้วเลือกห้องที่จะเปิดรับจากหน้าจัดการโรงเรียน', 'ยืนยันเปิดใช้งาน']
 };
 let trigger;
 document.addEventListener('click', event => {
  const button = event.target.closest('[data-school-participation]');
  if (!button) return;
  const state = button.dataset.schoolParticipation;
  const [title, description, confirmation] = copy[state];
  trigger = button;
  form.elements.school.value = button.dataset.schoolId;
  form.elements.school_action.value = state;
  schoolParticipationDialog.querySelector('h2').textContent = title;
  schoolParticipationDialog.querySelector('[data-school-dialog-name]').textContent = button.dataset.schoolName;
  schoolParticipationDialog.querySelector('[data-school-dialog-description]').textContent = description;
  schoolParticipationDialog.querySelector('[data-school-dialog-confirm]').textContent = confirmation;
  years.replaceChildren(...JSON.parse(button.dataset.schoolYears).map(entry => {
   const row = document.createElement('li');
   row.textContent = entry.year + ' · ' + entry.rooms + ' ห้อง · ผู้เข้าร่วม ' + entry.participants + ' คน';
   return row;
  }));
  years.hidden = !years.childElementCount;
  schoolParticipationDialog.querySelector('[data-school-dialog-staff]').hidden = !years.hidden || button.dataset.schoolHasData !== '1';
  schoolParticipationDialog.showModal();
 });
 schoolParticipationDialog.querySelector('[data-school-dialog-cancel]').addEventListener('click', () => schoolParticipationDialog.close());
 schoolParticipationDialog.addEventListener('click', event => {
  if (event.target !== schoolParticipationDialog) return;
  const bounds = schoolParticipationDialog.getBoundingClientRect();
  if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
   schoolParticipationDialog.close();
  }
 });
 schoolParticipationDialog.addEventListener('close', () => trigger?.focus());
}
