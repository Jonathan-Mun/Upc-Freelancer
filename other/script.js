/* ================================================================
   FREELANCE UPC — HEADER INTERACTIONS
================================================================ */

// ----------------------------------------------------------------
// DEMO: Switcher visiteur / connecté
// ----------------------------------------------------------------
function showHeader(mode) {
  const visitor = document.getElementById('header-visitor');
  const auth    = document.getElementById('header-auth');
  const btnV    = document.getElementById('btn-visitor');
  const btnA    = document.getElementById('btn-auth');

  if (mode === 'visitor') {
    visitor.classList.remove('hidden');
    auth.classList.add('hidden');
    btnV.classList.add('active');
    btnA.classList.remove('active');
  } else {
    auth.classList.remove('hidden');
    visitor.classList.add('hidden');
    btnA.classList.add('active');
    btnV.classList.remove('active');
  }
  closeAllDropdowns();
}

// ----------------------------------------------------------------
// DROPDOWNS — nav items (click-based)
// ----------------------------------------------------------------
function initDropdowns() {
  // Tous les éléments nav avec dropdown
  const navItems = document.querySelectorAll('.nav-item.has-dropdown');

  navItems.forEach(item => {
    const trigger = item.querySelector('.nav-link');
    if (!trigger) return;

    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = item.classList.contains('open');
      closeAllNavDropdowns();
      if (!isOpen) item.classList.add('open');
    });
  });

  // Icon buttons (notif, messages)
  const iconWraps = document.querySelectorAll('.icon-btn-wrap.has-dropdown');
  iconWraps.forEach(wrap => {
    const btn = wrap.querySelector('.icon-btn');
    if (!btn) return;

    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = wrap.classList.contains('open');
      closeAllDropdowns();
      if (!isOpen) wrap.classList.add('open');
    });
  });

  // Avatar dropdown
  const avatarWrap = document.querySelector('.avatar-wrap');
  if (avatarWrap) {
    const avatarBtn = avatarWrap.querySelector('.avatar-btn');
    avatarBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = avatarWrap.classList.contains('open');
      closeAllDropdowns();
      if (!isOpen) avatarWrap.classList.add('open');
    });
  }

  // Fermer sur clic extérieur
  document.addEventListener('click', closeAllDropdowns);

  // Empêcher fermeture sur clic dans le dropdown lui-même
  document.querySelectorAll('.dropdown').forEach(d => {
    d.addEventListener('click', e => e.stopPropagation());
  });
}

function closeAllNavDropdowns() {
  document.querySelectorAll('.nav-item.has-dropdown.open').forEach(el => {
    el.classList.remove('open');
  });
}

function closeAllDropdowns() {
  document.querySelectorAll('.nav-item.has-dropdown.open, .icon-btn-wrap.open, .avatar-wrap.open').forEach(el => {
    el.classList.remove('open');
  });
}

// ----------------------------------------------------------------
// SEARCH — focus / blur
// ----------------------------------------------------------------
function initSearch() {
  const input = document.getElementById('searchInput');
  if (!input) return;

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      input.blur();
    }
  });
}

// ----------------------------------------------------------------
// MOBILE MENU
// ----------------------------------------------------------------
function toggleMobileMenu(type) {
  const menu = document.getElementById(`mobile-${type}`);
  if (!menu) return;

  const isOpen = menu.classList.contains('open');
  menu.classList.toggle('open', !isOpen);

  // Burger animation
  const burger = document.getElementById(`burger-${type}`);
  if (burger) burger.classList.toggle('active', !isOpen);
}

// ----------------------------------------------------------------
// SCROLL — header condensé
// ----------------------------------------------------------------
function initScrollEffect() {
  const headers = document.querySelectorAll('.site-header');

  const onScroll = () => {
    const scrolled = window.scrollY > 12;
    headers.forEach(h => h.classList.toggle('scrolled', scrolled));
  };

  window.addEventListener('scroll', onScroll, { passive: true });
}

// ----------------------------------------------------------------
// THEME TOGGLE
// ----------------------------------------------------------------
function toggleTheme() {
  const body = document.body;
  const isLight = body.classList.toggle('light-mode');

  // Met à jour les boutons du toggle
  const opts = document.querySelectorAll('.theme-opt');
  opts.forEach(opt => {
    opt.classList.toggle('active',
      (opt.dataset.theme === 'light') === isLight
    );
  });

  // Persiste le choix
  try {
    localStorage.setItem('fupc-theme', isLight ? 'light' : 'dark');
  } catch(e) {}
}

// Restore theme au chargement
function restoreTheme() {
  try {
    const saved = localStorage.getItem('fupc-theme');
    if (saved === 'light') {
      document.body.classList.add('light-mode');
      const opts = document.querySelectorAll('.theme-opt');
      opts.forEach(opt => {
        opt.classList.toggle('active', opt.dataset.theme === 'light');
      });
    }
  } catch(e) {}
}

// ----------------------------------------------------------------
// KEYBOARD NAVIGATION
// ----------------------------------------------------------------
function initKeyboard() {
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAllDropdowns();
  });
}

// ----------------------------------------------------------------
// INIT
// ----------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
  initDropdowns();
  initSearch();
  initScrollEffect();
  initKeyboard();
  restoreTheme();

  // Fermer les menus mobiles au resize
  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
      document.querySelectorAll('.mobile-menu.open').forEach(m => m.classList.remove('open'));
      document.querySelectorAll('.burger.active').forEach(b => b.classList.remove('active'));
    }
  });
});
