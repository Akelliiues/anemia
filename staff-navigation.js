for (const menu of document.querySelectorAll('.admin-top-tabs .hosted-menu-group')) {
 const summary = menu.querySelector('summary');
 const links = menu.querySelector('.hosted-menu-links');
 let closeTimer;
 let mouseLeft = false;

 function scheduleClose() {
  clearTimeout(closeTimer);
  closeTimer = setTimeout(() => {
   if (mouseLeft && !links.contains(document.activeElement)) {
    menu.open = false;
   }
  }, 300);
 }

 menu.addEventListener('pointerenter', event => {
  if (event.pointerType !== 'mouse') return;
  mouseLeft = false;
  clearTimeout(closeTimer);
  menu.open = true;
 });
 menu.addEventListener('pointerleave', event => {
  if (event.pointerType !== 'mouse') return;
  mouseLeft = true;
  if (menu.open) scheduleClose();
 });
 links.addEventListener('focusout', () => {
  if (menu.open && mouseLeft) scheduleClose();
 });
 menu.addEventListener('toggle', () => {
  if (!menu.open) {
   clearTimeout(closeTimer);
  }
 });
 menu.addEventListener('keydown', event => {
  if (event.key !== 'Escape' || !menu.open) return;
  event.preventDefault();
  menu.open = false;
  summary.focus();
 });
}
