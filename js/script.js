
document.addEventListener('DOMContentLoaded', () => {
  initHeroSearch();
  animateCounters();
  initAboutTabs();
  initSpecialtiesCarousel();
});

function initHeroSearch() {
  const heroSearch = document.querySelector('.hero-search');
  if (!heroSearch) return;

  heroSearch.addEventListener('submit', (e) => {
    const input = heroSearch.querySelector('input[name="q"]');
    if (input && input.value.trim() === '') {
      e.preventDefault();
      input.focus();
    }
  });
}

function animateCounters() {
  const counters = document.querySelectorAll('.counter');
  if (!counters.length) return;

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  counters.forEach((counter) => {
    const target = parseFloat(counter.getAttribute('data-target')) || 0;
    const decimals = parseInt(counter.getAttribute('data-decimals') || '0', 10);
    const suffix = counter.getAttribute('data-suffix') || '';

    if (prefersReducedMotion) {
      counter.textContent = formatCounterValue(target, decimals) + suffix;
      return;
    }

    const duration = 1600;
    const startTime = performance.now();

    function step(now) {
      const progress = Math.min((now - startTime) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3); 
      const current = target * eased;

      counter.textContent = formatCounterValue(current, decimals) + suffix;

      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        counter.textContent = formatCounterValue(target, decimals) + suffix;
      }
    }

    requestAnimationFrame(step);
  });
}

function formatCounterValue(value, decimals) {
  if (decimals > 0) {
    return value.toFixed(decimals);
  }
  return Math.floor(value).toLocaleString('en-US');
}


const SPECIALTY_ICONS = {
  cardiology:
    '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-6.7-4.2-9.3-8.6C.8 9 2.3 5 6.2 5c2.2 0 3.7 1.2 4.7 2.6C11.9 6.2 13.4 5 15.6 5c3.9 0 5.4 4 3.5 7.4C16.5 16.8 12 21 12 21Z"/><path d="M6 13h2.6l1.5-3 2 5 1.6-4.3L14.5 13H18" stroke="#fff" stroke-width="1.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  dentistry:
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3c-2 0-3 1-4 1-2 0-3.5 1.6-3.5 4 0 2 .7 3 1 5.5.3 2.3.7 5.5 2 5.5 1.2 0 1.2-3 1.8-5 .4-1.4.9-2 1.7-2s1.3.6 1.7 2c.6 2 .6 5 1.8 5 1.3 0 1.7-3.2 2-5.5.3-2.5 1-3.5 1-5.5C17.5 5.6 16 4 14 4c-1 0-2-1-4-1Z" stroke-linejoin="round"/></svg>',
  dermatology:
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3c4 3 7 6.4 7 10.2A7 7 0 0 1 5 13.2C5 9.4 8 6 12 3Z" stroke-linejoin="round"/><path d="M12 8.5c1.6 1.4 2.8 2.9 2.8 4.6A2.8 2.8 0 0 1 12 16" stroke-linecap="round"/></svg>',
  pediatrics:
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="4.5" r="1.6"/><circle cx="15" cy="4.5" r="1.6"/><circle cx="12" cy="12" r="7"/><circle cx="9" cy="11" r="1.1" fill="currentColor" stroke="none"/><circle cx="15" cy="11" r="1.1" fill="currentColor" stroke="none"/><path d="M9.3 14.8c.7.7 1.7 1 2.7 1s2-.3 2.7-1" stroke-linecap="round"/></svg>',
  ophthalmology:
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s4-6.5 10-6.5S22 12 22 12s-4 6.5-10 6.5S2 12 2 12Z" stroke-linejoin="round"/><circle cx="12" cy="12" r="3.2"/></svg>',
  orthopedics:
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 7a2.5 2.5 0 1 1 3.8 2.1l3.4 3.4A2.5 2.5 0 1 1 17 15.8l-3.4-3.4L10.2 15.8A2.5 2.5 0 1 1 7 12l3.4-3.4L7 7Z" stroke-linejoin="round" stroke-linecap="round"/></svg>',
  general:
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M3 12h18" stroke-linecap="round"/></svg>',
};


const CARD_SPACING = 190;
const SLIDE_MS = 600;

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function initSpecialtiesCarousel() {
  const dataEl = document.getElementById('specialties-data');
  const track = document.getElementById('specialties-track');
  const prevBtn = document.getElementById('specialties-prev');
  const nextBtn = document.getElementById('specialties-next');
  if (!dataEl || !track) return;

  let specialties = [];
  try {
    specialties = JSON.parse(dataEl.textContent || '[]');
  } catch (e) {
    specialties = [];
  }
  if (!specialties.length) return;

  const total = specialties.length;
  const half = Math.floor(total / 2);

  let activeIndex = specialties.findIndex((s) => s.name === 'Cardiology');
  if (activeIndex === -1) activeIndex = Math.floor(total / 2);

  let isAnimating = false;

  function maxOffsetForWidth() {
    const w = window.innerWidth;
    if (w < 640) return 0;
    if (w < 900) return Math.min(1, Math.floor((total - 1) / 2));
    return Math.min(2, Math.floor((total - 1) / 2));
  }

  function canonical(o) {
    let m = ((o % total) + total) % total;
    if (m > half) m -= total;
    return m;
  }

  function cardMarkup(item) {
    const iconSvg = SPECIALTY_ICONS[item.icon] || SPECIALTY_ICONS.general;
    const countLabel = item.count + (item.count === 1 ? ' Doctor' : ' Doctors');

    return `
      <div class="specialty-icon">
        <span class="icon-ticks left"><span></span><span></span></span>
        ${iconSvg}
        <span class="icon-ticks right"><span></span><span></span></span>
      </div>
      <h3 class="specialty-name">${escapeHtml(item.name)}</h3>
      <p class="specialty-tagline">${escapeHtml(item.tagline)}</p>
      <span class="specialty-dash"></span>
      <div class="specialty-count">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="3.4"/><path d="M4.5 20c0-4 3.4-6.8 7.5-6.8s7.5 2.8 7.5 6.8" stroke-linecap="round"/></svg>
        <span class="specialty-count-text">${countLabel}</span>
      </div>
      <a class="specialty-view-btn" href="doctors.php?specialty%5B%5D=${encodeURIComponent(item.id)}">
        View Doctors
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    `;
  }

  track.innerHTML = '';
  const cards = specialties.map((item) => {
    const card = document.createElement('div');
    card.className = 'specialty-card';
    card.innerHTML = cardMarkup(item);
    track.appendChild(card);
    return card;
  });

  const offsets = specialties.map((_, i) => canonical(i - activeIndex));

  function paint(card, offset, maxOffset) {
    const isActive = offset === 0;
    const isVisible = Math.abs(offset) <= maxOffset;
    const clampedAbs = Math.min(Math.abs(offset), maxOffset + 1);

    const translateX = offset * CARD_SPACING;
    const translateY = clampedAbs * 22;
    const rotate = offset * 6;
    const scale = isActive ? 1.15 : 0.86 - (clampedAbs - 1) * 0.08;

    card.style.transform =
      `translateX(${translateX}px) translateY(${translateY}px) rotate(${rotate}deg) scale(${scale})`;
    card.style.opacity = isVisible ? '1' : '0';
    card.style.pointerEvents = isVisible ? '' : 'none';
    card.style.zIndex = String(10 - Math.abs(offset));
    card.classList.toggle('is-active', isActive);
  }

  function layout() {
    const maxOffset = maxOffsetForWidth();
    cards.forEach((card, i) => paint(card, offsets[i], maxOffset));
  }

  function step(direction) {
    if (isAnimating) return;
    isAnimating = true;

    const maxOffset = maxOffsetForWidth();

    cards.forEach((card, i) => {
      const raw = offsets[i] + direction;
      const wrapped = canonical(raw);

      if (wrapped !== raw) {
        const mirroredCurrent = wrapped - direction;
        card.style.transition = 'none';
        paint(card, mirroredCurrent, maxOffset);
       
        void card.offsetWidth;
        card.style.transition = '';
      }

      offsets[i] = wrapped;
    });

    layout();

    setTimeout(() => {
      isAnimating = false;
    }, SLIDE_MS);
  }

  prevBtn?.addEventListener('click', () => {
    activeIndex = (activeIndex - 1 + total) % total;
    step(1);
  });

  nextBtn?.addEventListener('click', () => {
    activeIndex = (activeIndex + 1) % total;
    step(-1);
  });

  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(layout, 150);
  });

  layout();
}
//// about
function initAboutTabs() {
  const tabs = document.querySelectorAll('.about-tab');
  if (!tabs.length) return;

  const panels = document.querySelectorAll('.about-tab-panel');

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      const target = tab.getAttribute('data-tab');

      tabs.forEach((t) => {
        t.classList.remove('active');
        t.setAttribute('aria-selected', 'false');
      });
      tab.classList.add('active');
      tab.setAttribute('aria-selected', 'true');

      panels.forEach((panel) => {
        panel.classList.toggle('active', panel.getAttribute('data-panel') === target);
      });
    });
  });
}


//responsive
document.addEventListener('DOMContentLoaded', () => {
  const toggleBtn = document.querySelector('.nav-toggle');
  const navLinks = document.querySelector('.nav-links');

  if (toggleBtn && navLinks) {
    toggleBtn.addEventListener('click', () => {
      navLinks.classList.toggle('is-open');
      toggleBtn.classList.toggle('is-active');
    });
  }
});
