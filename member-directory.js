{
 let directory = document.querySelector('.member-directory');
 const status = document.getElementById('member-directory-status');
 let pending;

 function bindMemberMenus() {
  for (const trigger of directory.querySelectorAll('[data-member-menu]')) {
   const menu = document.getElementById(trigger.dataset.memberMenu);
   if (typeof menu?.showPopover !== 'function') continue;
   menu.hidden = false;
   trigger.setAttribute('aria-expanded', 'false');
   menu.addEventListener('toggle', () => trigger.setAttribute('aria-expanded', String(menu.matches(':popover-open'))));
  }
 }

 async function loadMembers(url, saveHistory) {
  pending?.abort();
  const request = new AbortController();
  pending = request;
  directory.setAttribute('aria-busy', 'true');
  status.textContent = '';
  try {
   const response = await fetch(url, {headers: {'X-Member-Directory': '1'}, signal: request.signal});
   if (response.redirected) {
    if (!request.signal.aborted) location.assign(response.url);
    return;
   }
   if (!response.ok) throw new Error();
   const html = await response.text();
   if (request.signal.aborted) return;
   const next = new DOMParser().parseFromString(html, 'text/html').querySelector('.member-directory');
   if (!next) throw new Error();
   const keepFocus = directory.contains(document.activeElement);
   directory.replaceWith(next);
   directory = next;
   bindMemberMenus();
   if (saveHistory && url !== location.href) history.pushState(null, '', url);
   if (keepFocus) directory.querySelector('[data-member-filter][aria-current="true"]')?.focus({preventScroll: true});
   status.textContent = 'อัปเดตรายชื่อสมาชิกแล้ว';
  } catch {
   if (!request.signal.aborted) {
    if (!saveHistory) location.reload();
    else status.textContent = 'โหลดรายชื่อไม่ได้ กรุณาลองเลือกการ์ดอีกครั้ง';
   }
  } finally {
   if (pending === request) {
    directory.removeAttribute('aria-busy');
    pending = null;
   }
  }
 }

 bindMemberMenus();
 document.addEventListener('click', event => {
  if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
  const link = event.target.closest('a');
  if (!link || !directory.contains(link)) return;
  if (link.hasAttribute('data-member-filter')) {
   event.preventDefault();
   loadMembers(link.href, true);
   return;
  }
  if (!link.dataset.memberMenu) return;
  const menu = document.getElementById(link.dataset.memberMenu);
  if (typeof menu?.showPopover !== 'function') return;
  event.preventDefault();
  if (menu.matches(':popover-open')) {
   menu.hidePopover();
   return;
  }
  menu.showPopover();
  const rect = link.getBoundingClientRect();
  menu.style.left = Math.max(8, Math.min(rect.right - menu.offsetWidth, innerWidth - menu.offsetWidth - 8)) + 'px';
  menu.style.top = (rect.bottom + menu.offsetHeight + 8 < innerHeight
   ? rect.bottom + 4 : Math.max(8, rect.top - menu.offsetHeight - 4)) + 'px';
 });
 window.addEventListener('popstate', () => {
  const params = new URL(location.href).searchParams;
  if (params.has('member')) location.reload();
  else loadMembers(location.href, false);
 });
}
