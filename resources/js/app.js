import './bootstrap';


document.addEventListener('DOMContentLoaded', () => {
  // Sidebar toggle for mobile
  const hamburger = document.querySelector('.hamburger');
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');

  const setSidebar = (open) => {
    if (!sidebar) return;
    sidebar.classList.toggle('open', open);
    document.body.classList.toggle('sidebar-open', open);
    if (overlay) overlay.classList.toggle('open', open);
  };

  if (hamburger && sidebar) {
    hamburger.addEventListener('click', () => setSidebar(!sidebar.classList.contains('open')));
  }
  if (overlay) {
    overlay.addEventListener('click', () => setSidebar(false));
  }
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') setSidebar(false);
  });

  // Mobile splash overlay hide on ready
  const splash = document.querySelector('.mobile-splash');
  const hideSplash = () => {
    if (splash) splash.classList.add('hidden');
  };
  if (splash) {
    window.addEventListener('load', () => setTimeout(hideSplash, 400));
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.ready.then(() => setTimeout(hideSplash, 200));
    }
  }

  // Gentle parallax for meeting table on desktop
  const stage = document.querySelector('.room-stage');
  const table = document.querySelector('.meeting-table');

  let enabled = window.innerWidth >= 992 && !!stage && !!table;
  function onMove(e) {
    const rect = stage.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;
    const mx = (e.clientX - cx) / rect.width;
    const my = (e.clientY - cy) / rect.height;
    table.style.transform = `translate3d(${mx * 10}px, ${my * 6}px, 0)`;
  }

  if (enabled) {
    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseleave', () => { table.style.transform = ''; });
  }

  window.addEventListener('resize', () => {
    const now = window.innerWidth >= 992;
    if (now && !enabled) {
      enabled = true;
      window.addEventListener('mousemove', onMove);
    } else if (!now && enabled) {
      enabled = false;
      window.removeEventListener('mousemove', onMove);
      if (table) table.style.transform = '';
    }
  });

  // Floating task icon interaction
  const floating = document.querySelector('.floating-task');
  if (floating) {
    floating.addEventListener('click', () => {
      floating.classList.add('pulse');
      setTimeout(() => floating.classList.remove('pulse'), 600);
      // TODO: open task modal/panel here
    });
  }

  // User menu toggle
  const userMenu = document.querySelector('.user-menu');
  const userTrigger = document.querySelector('.user-menu__trigger');
  const userPanel = document.querySelector('.user-menu__panel');
  const closeUserMenu = () => userPanel && userPanel.classList.remove('open');
  if (userTrigger && userPanel) {
    userTrigger.addEventListener('click', (e) => {
      e.stopPropagation();
      userPanel.classList.toggle('open');
    });
    document.addEventListener('click', (e) => {
      if (!userMenu.contains(e.target)) closeUserMenu();
    });
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeUserMenu();
    });
  }

  // Workspace menu toggle (admin/manager)
  const wsMenu = document.querySelector('.workspace-menu');
  const wsTrigger = document.querySelector('.workspace-trigger');
  const wsPanel = document.querySelector('.workspace-panel');
  const closeWs = () => wsPanel && wsPanel.classList.remove('open');
  if (wsTrigger && wsPanel) {
    wsTrigger.addEventListener('click', (e) => {
      e.stopPropagation();
      wsPanel.classList.toggle('open');
    });
    document.addEventListener('click', (e) => {
      if (wsMenu && !wsMenu.contains(e.target)) closeWs();
    });
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeWs();
    });
  }

  // Small accessibility: allow Enter key to trigger hover effect for keyboard users
  document.querySelectorAll('.user-card').forEach(card => {
    card.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        card.classList.add('keyboard-activated');
        setTimeout(()=> card.classList.remove('keyboard-activated'), 400);
      }
    });
  });

  // Service worker registration (ensures PWA on mobile/desktop)
  if ('serviceWorker' in navigator) {
    const isLocalhost = ['localhost', '127.0.0.1'].includes(window.location.hostname);
    const isSecure = window.location.protocol === 'https:';
    if (isLocalhost || isSecure) {
      navigator.serviceWorker.register('/sw.js').catch((err) => {
        console.error('Service worker registration failed:', err);
      });
    }
  }
});

