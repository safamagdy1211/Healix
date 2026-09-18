document.addEventListener('DOMContentLoaded', () => {
  initSidebarDrawer();
  initConfirmModal();
});

/* Mobile sidebar drawer */
function initSidebarDrawer() {
  const toggle  = document.querySelector('[data-sidebar-toggle]');
  const sidebar = document.querySelector('[data-sidebar]');
  const overlay = document.querySelector('[data-sidebar-overlay]');
  if (!toggle || !sidebar || !overlay) return;

  const open = () => {
    sidebar.classList.add('is-open');
    overlay.classList.add('is-visible');
    toggle.classList.add('is-active');
    toggle.setAttribute('aria-expanded', 'true');
    document.body.classList.add('hx-no-scroll');
  };

  const close = () => {
    sidebar.classList.remove('is-open');
    overlay.classList.remove('is-visible');
    toggle.classList.remove('is-active');
    toggle.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('hx-no-scroll');
  };

  toggle.addEventListener('click', () => {
    sidebar.classList.contains('is-open') ? close() : open();
  });

  overlay.addEventListener('click', close);

  sidebar.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', close);
  });

  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') close();
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 900) close();
  });
}

function initConfirmModal() {
  const forms = document.querySelectorAll('form[data-confirm]');
  if (!forms.length) return;

  const backdrop = document.createElement('div');
  backdrop.className = 'hx-modal-backdrop';
  backdrop.innerHTML = `
    <div class="hx-modal" role="alertdialog" aria-modal="true" aria-labelledby="hxModalMessage">
      <div class="hx-modal-icon"><i class="fa-solid fa-trash-can"></i></div>
      <h3 class="hx-modal-title">Are you sure?</h3>
      <p class="hx-modal-message" id="hxModalMessage"></p>
      <div class="hx-modal-actions">
        <button type="button" class="hx-modal-btn hx-modal-btn-cancel">Cancel</button>
        <button type="button" class="hx-modal-btn hx-modal-btn-confirm">
          <i class="fa-solid fa-check"></i> Yes, continue
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(backdrop);

  const messageEl = backdrop.querySelector('.hx-modal-message');
  const cancelBtn = backdrop.querySelector('.hx-modal-btn-cancel');
  const confirmBtn = backdrop.querySelector('.hx-modal-btn-confirm');
  let pendingForm = null;

  function showModal(form) {
    pendingForm = form;
    messageEl.textContent = form.getAttribute('data-confirm') || 'Are you sure you want to continue?';
    backdrop.classList.add('is-visible');
    document.body.classList.add('hx-no-scroll');
  }

  function hideModal() {
    backdrop.classList.remove('is-visible');
    document.body.classList.remove('hx-no-scroll');
    pendingForm = null;
  }

  forms.forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (form.dataset.confirmed === 'true') return; 
      e.preventDefault();
      showModal(form);
    });
  });

  cancelBtn.addEventListener('click', hideModal);

  backdrop.addEventListener('click', (e) => {
    if (e.target === backdrop) hideModal();
  });

  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && backdrop.classList.contains('is-visible')) hideModal();
  });

  confirmBtn.addEventListener('click', () => {
    if (!pendingForm || confirmBtn.disabled) return;
    confirmBtn.disabled = true;
    cancelBtn.disabled = true;
    pendingForm.dataset.confirmed = 'true';

    const submitBtn = pendingForm.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;

    pendingForm.submit();
    // Deliberately not re-enabling the buttons here: the page is about to navigate
    // away (full page reload via form submit), so leaving them disabled prevents
    // a second submission if the user clicks again while the response is in flight.
  });
}