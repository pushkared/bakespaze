
document.addEventListener('DOMContentLoaded', () => {
  // Sidebar toggle for mobile
  const hamburger = document.querySelector('.hamburger');
  const sidebar = document.querySelector('.vo-sidebar') || document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');

  const setSidebar = (open) => {
    if (!sidebar) return;
    document.body.classList.toggle('sidebar-open', open);
    if (overlay) overlay.classList.toggle('open', open);
  };

  if (hamburger && sidebar) {
    hamburger.addEventListener('click', (e) => {
      e.stopPropagation();
      setSidebar(!sidebar.classList.contains('open'));
    });
  }
  if (overlay) {
    overlay.addEventListener('click', () => setSidebar(false));
  }
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') setSidebar(false);
  });
  document.addEventListener('click', (e) => {
    if (sidebar && !sidebar.contains(e.target) && !hamburger.contains(e.target)) setSidebar(false);
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
  if (table) {
    table.addEventListener('click', () => {
      const targetUrl = table.getAttribute('data-event-url');
      if (targetUrl) {
        window.location.href = targetUrl;
      }
    });
  }

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

  // Floating task interaction handled in layout script.

  // Dashboard quick complete
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  document.querySelectorAll('.dash-task-item .dash-task-box').forEach((btn) => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      const item = btn.closest('.dash-task-item');
      if (!item || item.classList.contains('is-done')) return;
      if (!window.confirm('Are you sure you want to mark this task complete?')) {
        return;
      }
      const updateUrl = item.dataset.updateUrl;
      if (!updateUrl) return;

      item.classList.add('is-completing');
      btn.disabled = true;

      const formData = new FormData();
      formData.append('_token', csrfToken);
      formData.append('title', item.dataset.taskTitle || 'Task');
      formData.append('description', item.dataset.taskDesc || '');
      if (item.dataset.taskDue) formData.append('due_date', item.dataset.taskDue);
      formData.append('status', 'completed');
      if (item.dataset.taskAssignee) formData.append('assignee_id', item.dataset.taskAssignee);

      try {
        const response = await fetch(updateUrl, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
          },
        });
        if (!response.ok) {
          throw new Error('Failed to update task');
        }
        item.classList.add('is-done');
        setTimeout(() => {
          item.classList.add('is-fade');
          setTimeout(() => item.remove(), 500);
        }, 300);
      } catch (err) {
        item.classList.remove('is-completing');
        btn.disabled = false;
      }
    });
  });

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
