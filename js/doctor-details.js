
document.addEventListener('DOMContentLoaded', () => {
  initDDTabs();
  initDDModals();
});

function initDDTabs() {
  const tabs = document.querySelectorAll('.dd-tab');
  const panels = document.querySelectorAll('.dd-tab-panel');
  if (!tabs.length) return;

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      const target = tab.getAttribute('data-tab');

      tabs.forEach((t) => t.classList.remove('is-active'));
      panels.forEach((p) => p.classList.remove('is-active'));

      tab.classList.add('is-active');
      const panel = document.querySelector('.dd-tab-panel[data-tab-panel="' + target + '"]');
      if (panel) panel.classList.add('is-active');
    });
  });
}


function initDDModals() {
  const openers = document.querySelectorAll('[data-open-modal]');
  const closers = document.querySelectorAll('[data-close-modal]');

  openers.forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      openDDModal(btn.getAttribute('data-open-modal'));
    });
  });

  closers.forEach((btn) => {
    btn.addEventListener('click', () => {
      closeDDModal(btn.closest('.dd-modal-overlay'));
    });
  });

  document.querySelectorAll('.dd-modal-overlay').forEach((overlay) => {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) closeDDModal(overlay);
    });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.dd-modal-overlay.is-open').forEach(closeDDModal);
    }
  });


  const autoOpen = document.body.getAttribute('data-auto-open-modal');
  if (autoOpen) openDDModal(autoOpen);
}

function openDDModal(name) {
  const overlay = document.getElementById('dd-modal-' + name);
  if (!overlay) return;
  overlay.classList.add('is-open');
  document.body.style.overflow = 'hidden';
}

function closeDDModal(overlay) {
  if (!overlay) return;
  overlay.classList.remove('is-open');
  document.body.style.overflow = '';
}