function bindSchoolWorkspace(workspace) {
 for(const link of workspace.querySelectorAll('[data-school-qr]')){
  const target=link.nextElementSibling;
  link.parentElement.addEventListener('toggle',()=>{
   if(!link.parentElement.open||target.firstChild)return;
   const qr=qrcode(0,'M');
   qr.addData(link.href);
   qr.make();
   target.innerHTML=qr.createImgTag(4,16,'QR สำหรับเข้าร่วมรุ่นกิจกรรม');
  });
 }

 for (const button of workspace.querySelectorAll('[data-copy-school-link]')) {
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

 const schoolParticipationDialog = workspace.querySelector('#school-participation-dialog');
 if (schoolParticipationDialog) {
  const form = schoolParticipationDialog.querySelector('form');
  const years = schoolParticipationDialog.querySelector('[data-school-dialog-years]');
  const copy = {
   'school-withdraw': ['ถอนโรงเรียนออกจากโครงการ', 'โรงเรียนจะกลับไปอยู่ในรายการที่เพิ่มเข้าร่วมได้', 'ยืนยันถอน'],
   'school-archive': ['ยุติการเข้าร่วม', 'ระบบจะหยุดรับนักเรียนใหม่ ห้องเรียน QR และประวัติของทุกปีจะยังอยู่', 'ยืนยันยุติการเข้าร่วม'],
   'school-reopen': ['เปิดใช้งานโรงเรียนอีกครั้ง', 'ใช้ทะเบียนและ QR เดิม แล้วเลือกห้องที่จะเปิดรับจากหน้าจัดการโรงเรียน', 'ยืนยันเปิดใช้งาน']
  };
  let trigger;
  workspace.addEventListener('click', event => {
   const button = event.target.closest('[data-school-participation]');
   if (!button) return;
   const state = button.dataset.schoolParticipation;
   const [title, description, confirmation] = copy[state];
   trigger = button;
   schoolParticipationDialog.querySelector('[data-school-update-error]').hidden = true;
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
   if (event.target !== schoolParticipationDialog || form.getAttribute('aria-busy')==='true') return;
   const bounds = schoolParticipationDialog.getBoundingClientRect();
   if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
    schoolParticipationDialog.close();
   }
  });
  schoolParticipationDialog.addEventListener('close', () => trigger?.focus({preventScroll:true}));
  schoolParticipationDialog.addEventListener('cancel', event => { if (form.getAttribute('aria-busy')==='true') event.preventDefault(); });
 }
}
const initialSchoolWorkspace = document.querySelector('[data-school-workspace]');
if (initialSchoolWorkspace) bindSchoolWorkspace(initialSchoolWorkspace);

let savingSchoolParticipation = false;
document.addEventListener('submit', async event => {
 const form = event.target;
 if (!form.matches('[data-school-update]') || typeof fetch !== 'function') return;
 const workspace = form.closest('[data-school-workspace]');
 if (!workspace) return;
 event.preventDefault();
 if (savingSchoolParticipation) return;

 const dialog = form.closest('dialog');
 const error = (dialog || workspace).querySelector('[data-school-update-error]');
 const data = new FormData(form);
 const code = data.get('directory_code') ||
  workspace.querySelector('[data-school-participation][data-school-id="' + CSS.escape(String(data.get('school'))) + '"]')?.closest('[data-school-code]')?.dataset.schoolCode;
 const buttons = [...form.querySelectorAll('button:not(:disabled)')];
 savingSchoolParticipation = true;
 error.hidden = true;
 form.setAttribute('aria-busy','true');
 buttons.forEach(button => { button.disabled = true; });
 try {
  const response = await fetch(form.action, {
   method:'POST', body:data, headers:{'X-School-Update':'1'}, cache:'no-store'
  });
  if (response.status === 403) throw new Error('คำขอหมดอายุ กรุณาเปิดหน้าใหม่แล้วลองอีกครั้ง');
  if (!response.ok) throw new Error('ยังบันทึกไม่ได้ กรุณาลองอีกครั้ง');
  const page = new DOMParser().parseFromString(await response.text(),'text/html');
  const updated = page.querySelector('[data-school-workspace]');
  if (!updated) throw new Error('ไม่พบข้อมูลที่อัปเดต กรุณาเปิดหน้าใหม่เพื่อตรวจสถานะ');

  const table = workspace.querySelector('.school-directory-table');
  const previousScroll = {left:table?.scrollLeft || 0,top:table?.scrollTop || 0};
  const pageScroll = {left:window.scrollX,top:window.scrollY,behavior:'instant'};
  if (dialog?.open) dialog.close();
  workspace.replaceWith(updated);
  bindSchoolWorkspace(updated);
  const newTable = updated.querySelector('.school-directory-table');
  if (newTable) newTable.scrollTo({...previousScroll,behavior:'instant'});
  window.scrollTo(pageScroll);
  const row = code && updated.querySelector('[data-school-code="' + CSS.escape(String(code)) + '"]');
  const control = row ? row.querySelector('.school-row-toggle') :
   updated.querySelector('#school-activities [data-school-participation]');
  control?.focus({preventScroll:true});
 } catch (failure) {
  error.textContent = failure instanceof TypeError ? 'เชื่อมต่อไม่ได้ กรุณาลองอีกครั้ง' : failure.message;
  error.hidden = false;
 } finally {
  savingSchoolParticipation = false;
  form.removeAttribute('aria-busy');
  buttons.forEach(button => { button.disabled = false; });
 }
});
