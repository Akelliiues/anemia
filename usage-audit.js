const auditTable = document.querySelector('.usage-audit-table');
const auditTooltip = document.querySelector('.usage-audit-tooltip');

if (auditTable && typeof auditTooltip?.showPopover === 'function') {
  const cells = auditTable.querySelectorAll('.usage-audit-cell');
  const isClipped = cell => cell.scrollWidth > cell.clientWidth;
  const close = () => {
    if (auditTooltip.matches(':popover-open')) auditTooltip.hidePopover();
  };
  const updateCells = () => {
    close();
    for (const cell of cells) {
      const clipped = isClipped(cell);
      cell.tabIndex = clipped ? 0 : -1;
      cell.title = clipped ? cell.textContent : '';
    }
  };
  const show = event => {
    const cell = event.target.closest('.usage-audit-cell');
    if (!cell || !isClipped(cell)) return;
    auditTooltip.textContent = cell.textContent;
    if (!auditTooltip.matches(':popover-open')) auditTooltip.showPopover();
    const anchor = cell.getBoundingClientRect();
    const {width, height} = auditTooltip.getBoundingClientRect();
    auditTooltip.style.left = Math.max(8, Math.min(anchor.left, document.documentElement.clientWidth - width - 8)) + 'px';
    auditTooltip.style.top = Math.max(8, anchor.bottom + height + 16 <= innerHeight ? anchor.bottom + 8 : anchor.top - height - 8) + 'px';
  };
  updateCells();
  new ResizeObserver(updateCells).observe(auditTable);
  document.fonts?.ready.then(updateCells);
  auditTable.addEventListener('focusin', show);
  auditTable.addEventListener('focusout', close);
  auditTable.addEventListener('click', show);
  window.addEventListener('scroll', close, {capture:true, passive:true});
}
