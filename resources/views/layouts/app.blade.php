<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>@yield('title', 'Virtual Office')</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <meta name="theme-color" content="#0b0b0b">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <link rel="apple-touch-icon" href="{{ asset('images/icon-180.png') }}">
  <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
  @stack('head')
</head>
<body class="vo-body">
  <div class="app-shell vo-shell">

    <aside class="sidebar vo-sidebar" aria-label="Main navigation">
      <div class="sidebar-scroll">
      <div class="sidebar-inner">
        <div class="sidebar-brand">
          <div class="logo-mark">
            <img src="{{ asset('images/BakeSapze - Mobile - Logo.png') }}" alt="Logo">
          </div>
        </div>


        <nav class="sidebar-menu" role="navigation" aria-label="Primary">
          <a class="menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="menu-icon dashboard" aria-hidden="true"></span><span class="menu-text">Dashboard</span></a>
          <a class="menu-link {{ request()->routeIs('virtual-office') ? 'active' : '' }}" href="{{ route('virtual-office') }}"><span class="menu-icon virtual" aria-hidden="true"></span><span class="menu-text">Virtual Office</span></a>
          <a class="menu-link {{ request()->routeIs('tasks.*') ? 'active' : '' }}" href="{{ route('tasks.index') }}"><span class="menu-icon analytics" aria-hidden="true"></span><span class="menu-text">Tasks</span></a>
          <a class="menu-link {{ request()->routeIs('workspaces.index') ? 'active' : '' }}" href="{{ route('workspaces.index') }}"><span class="menu-icon settings" aria-hidden="true"></span><span class="menu-text">Workspaces</span></a>
          <a class="menu-link {{ request()->routeIs('chat.*') ? 'active' : '' }}" href="{{ route('chat.index') }}"><span class="menu-icon chat" aria-hidden="true"></span><span class="menu-text">Chat</span></a>
          <a class="menu-link" href="/calendar"><span class="menu-icon calendar" aria-hidden="true"></span><span class="menu-text">Calendar</span></a>
          <a class="menu-link" href="/attendance"><span class="menu-icon attendance" aria-hidden="true"></span><span class="menu-text">Attendance</span></a>
          <a class="menu-link {{ request()->routeIs('leaves.*') ? 'active' : '' }}" href="{{ route('leaves.index') }}"><span class="menu-icon leave" aria-hidden="true"></span><span class="menu-text">Leaves</span></a>
          <a class="menu-link" href="#"><span class="menu-icon analytics" aria-hidden="true"></span><span class="menu-text">Analytics</span></a>
          <a class="menu-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><span class="menu-icon settings" aria-hidden="true"></span><span class="menu-text">Settings</span></a>
        </nav>
      </div>
      <div class="sidebar-footer">
        <div class="footer-date">{{ \Carbon\Carbon::now()->format('D d M') }}</div>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="menu-link logout-link">
            <span class="menu-icon logout" aria-hidden="true"></span>
            <span class="menu-text">Logout</span>
          </button>
        </form>
      </div>
      </div>
    </aside>
    <div class="sidebar-overlay" aria-hidden="true"></div>

    <div class="main vo-main">
      <header class="topbar vo-topbar">
        <div class="top-left">
          <button class="hamburger desktop-hide" type="button" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
          </button>
          {{-- <div class="brand-lockup desktop-hide">
            <img src="{{ asset('images/logo-infinity.svg') }}" alt="Logo">
          </div> --}}
          <div class="workspace-switch workspace-menu">
            <button class="workspace-trigger" type="button" aria-label="Select workspace">
              <span class="workspace-name">{{ $currentWorkspace->name ?? 'Select workspace' }}</span>
              <span class="workspace-caret">&#9662;</span>
            </button>
            <div class="workspace-panel">
              @forelse($availableWorkspaces ?? collect() as $ws)
                <form method="POST" action="{{ route('workspaces.switch') }}" class="workspace-option">
                  @csrf
                  <input type="hidden" name="workspace_id" value="{{ $ws->id }}">
                  <button type="submit" class="{{ optional($currentWorkspace)->id === $ws->id ? 'active' : '' }}">
                    {{ $ws->name }}
                  </button>
                </form>
              @empty
                <div class="workspace-empty">No workspaces yet.</div>
              @endforelse
              <a class="workspace-manage" href="{{ route('workspaces.index') }}">Manage workspaces</a>
            </div>
          </div>
          <button class="mobile-search-btn" type="button" aria-label="Open search" id="mobile-search-btn">
            <span class="icon-search" aria-hidden="true"></span>
          </button>
        </div>
        <div class="top-right">
          <button class="create-task">+ Create Task</button>
          <div class="top-date">{{ \Carbon\Carbon::now()->format('D d M') }}</div>
          <div class="top-logo">
            <img src="{{ asset('images/BakeSapze - Mobile - Logo.png') }}" alt="Logo">
          </div>
          
        </div>
      </header>

      <main class="content-area">
        @yield('content')
      </main>
    </div>
  </div>

  <div class="mobile-search-overlay" id="mobile-search-overlay">
    <div class="search-bar">
      <span class="icon-search" aria-hidden="true"></span>
      <input type="search" id="mobile-search-input" placeholder="Search users, tasks, chats, meetings" autocomplete="off" />
      <button class="close-floating" type="button" id="mobile-search-close" aria-label="Close search"></button>
    </div>
    <div class="mobile-search-results" id="mobile-search-results"></div>
  </div>
  <div class="permission-banner hidden" id="permission-banner">
    <div class="permission-text">Enable notifications to get chat and task alerts.</div>
    <div class="permission-actions">
      <button class="pill-btn solid" type="button" id="permission-allow">Allow</button>
      <button class="pill-btn ghost" type="button" id="permission-dismiss">Not now</button>
    </div>
  </div>

  <button class="floating-task" type="button" aria-label="Open tasks">
    <img src="{{ asset('images/tasklist-icon.svg') }}" alt="">
  </button>
  {{-- <button class="floating-task floating-create" type="button" aria-label="Create task" id="floating-create-shortcut">
    +
  </button> --}}
  <div class="floating-panel" id="floating-panel">
    <div class="floating-head">
      <div>
        <div class="eyebrow">Workspace Tasks</div>
        <div class="floating-title">{{ $currentWorkspace->name ?? 'Tasks' }}</div>
      </div>
      <div class="float-actions">
        <button class="pill-btn small ghost" type="button" id="floating-create-task">+ Create Task</button>
      </div>
    </div>
    <div class="floating-list">
      @forelse(($panelTasks ?? collect()) as $task)
        @php
          $isAssignee = $task->assignees->contains(auth()->id());
        @endphp
        <div class="float-task">
          <div class="float-main">
            <div class="float-title">{{ $task->title }}</div>
            <div class="float-meta">
              <span>{{ $task->due_date ? $task->due_date->format('d M') : 'No due' }}</span>
            </div>
          </div>
          <div class="float-actions">
            @if($task->status !== 'completed' && $isAssignee)
            <form method="POST" action="{{ route('tasks.update', $task) }}">
              @csrf
              <input type="hidden" name="title" value="{{ $task->title }}">
              <input type="hidden" name="description" value="{{ e($task->description) }}">
              <input type="hidden" name="due_date" value="{{ $task->due_date }}">
              <input type="hidden" name="status" value="completed">
              <input type="hidden" name="assignee_id" value="{{ optional($task->assignees->first())->id }}">
              <button type="submit" class="pill-btn small">Mark Done</button>
            </form>
            @else
              <span class="status done">Done</span>
            @endif
            <a href="{{ route('tasks.index') }}" class="pill-btn small ghost">Details</a>
          </div>
        </div>
      @empty
        <div class="muted">No tasks found.</div>
      @endforelse
    </div>
  </div>

  <script src="{{ asset('js/push.js') }}"></script>
  <script src="{{ asset('js/dashboard.js') }}"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const hamburger = document.querySelector('.hamburger');
      const sidebar = document.querySelector('.vo-sidebar') || document.querySelector('.sidebar');
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
      if (overlay) overlay.addEventListener('click', () => setSidebar(false));
      window.addEventListener('keydown', (e) => { if (e.key === 'Escape') setSidebar(false); });

      const btn = document.querySelector('.floating-task');
      const panel = document.getElementById('floating-panel');
      const closeBtn = panel ? panel.querySelector('.close-floating') : null;
      const storageKey = 'floating_task_pos';
      let isDragging = false;
      let startX = 0;
      let startY = 0;
      let originX = 0;
      let originY = 0;

      const applyPosition = (x, y) => {
        if (!btn) return;
        const maxX = window.innerWidth - btn.offsetWidth;
        const maxY = window.innerHeight - btn.offsetHeight;
        const clampedX = Math.max(0, Math.min(x, maxX));
        const clampedY = Math.max(0, Math.min(y, maxY));
        btn.style.left = `${clampedX}px`;
        btn.style.top = `${clampedY}px`;
        btn.style.right = 'auto';
        btn.style.bottom = 'auto';
      };

      const restorePosition = () => {
        if (!btn) return;
        const saved = localStorage.getItem(storageKey);
        if (!saved) return;
        try {
          const pos = JSON.parse(saved);
          if (typeof pos.x === 'number' && typeof pos.y === 'number') {
            applyPosition(pos.x, pos.y);
          }
        } catch (e) {
          localStorage.removeItem(storageKey);
        }
      };

      const togglePanel = (open) => {
        if (!panel) return;
        panel.classList.toggle('open', open ?? !panel.classList.contains('open'));
      };

      if (btn && panel) {
        restorePosition();

        btn.addEventListener('pointerdown', (e) => {
          isDragging = false;
          startX = e.clientX;
          startY = e.clientY;
          const rect = btn.getBoundingClientRect();
          originX = rect.left;
          originY = rect.top;
          btn.setPointerCapture(e.pointerId);
          btn.style.touchAction = 'none';
        });

        btn.addEventListener('pointermove', (e) => {
          if (!btn.hasPointerCapture(e.pointerId)) return;
          e.preventDefault();
          const dx = e.clientX - startX;
          const dy = e.clientY - startY;
          if (Math.abs(dx) + Math.abs(dy) > 6) {
            isDragging = true;
          }
          if (isDragging) {
            applyPosition(originX + dx, originY + dy);
          }
        });

        btn.addEventListener('pointerup', (e) => {
          if (btn.hasPointerCapture(e.pointerId)) {
            btn.releasePointerCapture(e.pointerId);
          }
          btn.style.touchAction = '';
          if (isDragging) {
            const rect = btn.getBoundingClientRect();
            localStorage.setItem(storageKey, JSON.stringify({ x: rect.left, y: rect.top }));
            return;
          }
          togglePanel();
        });

        window.addEventListener('resize', () => {
          const rect = btn.getBoundingClientRect();
          if (rect.left || rect.top) {
            applyPosition(rect.left, rect.top);
          }
        });
      }

      if (closeBtn) closeBtn.addEventListener('click', () => togglePanel(false));
      document.addEventListener('click', (e) => {
        if (!panel || !panel.classList.contains('open')) return;
        if (panel.contains(e.target) || btn.contains(e.target)) return;
        togglePanel(false);
      });

      const floatCreate = document.getElementById('floating-create-task');
      const floatShortcut = document.getElementById('floating-create-shortcut');
      const handleCreate = () => {
        if (typeof window.openTaskModal === 'function') {
          window.openTaskModal();
          if (panel) panel.classList.add('open');
        } else {
          window.location = '{{ route('tasks.index') }}?open_modal=1';
        }
      };
      if (floatCreate) floatCreate.addEventListener('click', handleCreate);
      if (floatShortcut) floatShortcut.addEventListener('click', handleCreate);

      // Global search with suggestions
      const searchInput = document.getElementById('global-search');
      const dropdown = document.getElementById('global-search-dropdown');
      let searchTimer;
      const renderResults = (data) => {
        if (!dropdown) return;
        const userHtml = (data.users || []).map(u => `
          <div class="search-item" data-type="user" data-id="${u.id}">
            <div class="title">${u.name}</div>
            <div class="meta">${u.email}${u.role ? ' - ' + u.role : ''}</div>
          </div>
        `).join('') || '<div class="muted">No users found</div>';
        const taskHtml = (data.tasks || []).map(t => `
          <div class="search-item" data-type="task" data-id="${t.id}">
            <div class="title">${t.title}</div>
            <div class="meta">${t.workspace} - ${t.assigned_to || 'Unassigned'} - ${t.due_date || 'No due'} - ${t.status}</div>
          </div>
        `).join('') || '<div class="muted">No tasks found</div>';
        const chatHtml = (data.chats || []).map(c => `
          <div class="search-item" data-type="chat" data-id="${c.id}" data-peer-id="${c.peer_id || ''}" data-chat-type="${c.type || ''}">
            <div class="title">${c.title}</div>
            <div class="meta">${(c.participants || []).join(', ')}${c.last_message ? ' - ' + c.last_message : ''}</div>
          </div>
        `).join('') || '<div class="muted">No chats found</div>';
        const meetingHtml = (data.meetings || []).map(m => `
          <div class="search-item" data-type="meeting" data-id="${m.id}">
            <div class="title">${m.title}</div>
            <div class="meta">${m.start || 'No time'}${m.end ? ' - ' + m.end : ''}</div>
          </div>
        `).join('') || '<div class="muted">No meetings found</div>';
        dropdown.innerHTML = `
          <div class="search-section">
            <h5>Users</h5>
            ${userHtml}
          </div>
          <div class="search-section">
            <h5>Tasks</h5>
            ${taskHtml}
          </div>
          <div class="search-section">
            <h5>Chats</h5>
            ${chatHtml}
          </div>
          <div class="search-section">
            <h5>Meetings</h5>
            ${meetingHtml}
          </div>
        `;
        dropdown.hidden = false;
      };
      const hideDropdown = () => { if (dropdown) dropdown.hidden = true; };
      if (searchInput && dropdown) {
        searchInput.addEventListener('input', () => {
          const q = searchInput.value.trim();
          if (q.length < 2) { hideDropdown(); return; }
          clearTimeout(searchTimer);
          searchTimer = setTimeout(() => {
            fetch(`{{ route('search.global') }}?q=${encodeURIComponent(q)}`)
              .then(r => r.ok ? r.json() : Promise.reject())
              .then(renderResults)
              .catch(() => hideDropdown());
          }, 180);
        });
        document.addEventListener('click', (e) => {
          if (!dropdown.contains(e.target) && e.target !== searchInput) hideDropdown();
        });
        dropdown.addEventListener('click', (e) => {
          const item = e.target.closest('.search-item');
          if (!item) return;
          const type = item.dataset.type;
          const id = item.dataset.id;
          if (type === 'task') {
            window.location = '{{ route('tasks.index') }}#task-' + id;
          } else if (type === 'chat') {
            const peerId = item.dataset.peerId || '';
            if (peerId) {
              window.location = '{{ route('chat.index') }}?user=' + peerId;
            } else {
              window.location = '{{ route('chat.index') }}?conversation=' + id;
            }
          } else if (type === 'meeting') {
            window.location = '{{ route('calendar.index') }}?event=' + encodeURIComponent(id);
          } else {
            window.location = '{{ url('/users') }}/' + id;
          }
        });
      }

      // Mobile search overlay
      const mobileBtn = document.getElementById('mobile-search-btn');
      const mobileOverlay = document.getElementById('mobile-search-overlay');
      const mobileInput = document.getElementById('mobile-search-input');
      const mobileClose = document.getElementById('mobile-search-close');
      const mobileResults = document.getElementById('mobile-search-results');
      const renderMobileResults = (data) => {
        if (!mobileResults) return;
        const userHtml = (data.users || []).map(u => `<div class="search-item" data-type="user" data-id="${u.id}"><div class="title">${u.name}</div><div class="meta">${u.email}</div></div>`).join('') || '<div class="muted">No users</div>';
        const taskHtml = (data.tasks || []).map(t => `<div class="search-item" data-type="task" data-id="${t.id}"><div class="title">${t.title}</div><div class="meta">${t.workspace} - ${t.assigned_to || 'Unassigned'} - ${t.due_date || 'No due'} - ${t.status}</div></div>`).join('') || '<div class="muted">No tasks</div>';
        const chatHtml = (data.chats || []).map(c => `<div class="search-item" data-type="chat" data-id="${c.id}" data-peer-id="${c.peer_id || ''}" data-chat-type="${c.type || ''}"><div class="title">${c.title}</div><div class="meta">${(c.participants || []).join(', ')}${c.last_message ? ' - ' + c.last_message : ''}</div></div>`).join('') || '<div class="muted">No chats</div>';
        const meetingHtml = (data.meetings || []).map(m => `<div class="search-item" data-type="meeting" data-id="${m.id}"><div class="title">${m.title}</div><div class="meta">${m.start || 'No time'}${m.end ? ' - ' + m.end : ''}</div></div>`).join('') || '<div class="muted">No meetings</div>';
        mobileResults.innerHTML = `<div class="search-section"><h5>Users</h5>${userHtml}</div><div class="search-section"><h5>Tasks</h5>${taskHtml}</div><div class="search-section"><h5>Chats</h5>${chatHtml}</div><div class="search-section"><h5>Meetings</h5>${meetingHtml}</div>`;
      };
      const closeMobile = () => {
        if (mobileOverlay) mobileOverlay.classList.remove('open');
      };
      const openMobile = () => {
        if (mobileOverlay) {
          mobileOverlay.classList.add('open');
          if (mobileInput) mobileInput.focus();
        }
      };
      if (mobileBtn) mobileBtn.addEventListener('click', openMobile);
      if (mobileClose) mobileClose.addEventListener('click', closeMobile);
      if (mobileOverlay) mobileOverlay.addEventListener('click', (e) => {
        if (e.target === mobileOverlay) closeMobile();
      });
      if (mobileInput) {
        mobileInput.addEventListener('input', () => {
          const q = mobileInput.value.trim();
          if (q.length < 2) { if (mobileResults) mobileResults.innerHTML = ''; return; }
          clearTimeout(searchTimer);
          searchTimer = setTimeout(() => {
            fetch(`{{ route('search.global') }}?q=${encodeURIComponent(q)}`)
              .then(r => r.ok ? r.json() : Promise.reject())
              .then(renderMobileResults)
              .catch(() => { if (mobileResults) mobileResults.innerHTML = '<div class="muted">No results</div>'; });
          }, 180);
        });
      }
      if (mobileResults) {
        mobileResults.addEventListener('click', (e) => {
          const item = e.target.closest('.search-item');
          if (!item) return;
          const type = item.dataset.type;
          const id = item.dataset.id;
          if (type === 'task') {
            window.location = '{{ route('tasks.index') }}#task-' + id;
          } else if (type === 'chat') {
            const peerId = item.dataset.peerId || '';
            if (peerId) {
              window.location = '{{ route('chat.index') }}?user=' + peerId;
            } else {
              window.location = '{{ route('chat.index') }}?conversation=' + id;
            }
          } else if (type === 'meeting') {
            window.location = '{{ route('calendar.index') }}?event=' + encodeURIComponent(id);
          } else if (type === 'user') {
            window.location = '{{ url('/users') }}/' + id;
          } else {
            window.location = '{{ route('users.index') }}';
          }
        });
      }

      // Prevent double submit: disable button and show loading
      const setLoading = (btn) => {
        if (!btn || btn.dataset.loading === '1') return;
        btn.dataset.loading = '1';
        btn.classList.add('is-loading');
        if (btn.tagName.toLowerCase() === 'input') {
          btn.dataset.originalValue = btn.value;
          btn.value = btn.dataset.loadingText || 'Processing...';
        } else {
          const text = btn.textContent || '';
          if (text.trim().length) {
            btn.dataset.originalText = text;
            btn.textContent = btn.dataset.loadingText || 'Processing...';
          }
        }
        btn.disabled = true;
      };

      document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (e) => {
          if (form.id === 'chat-form') return;
          setTimeout(() => {
            if (e.defaultPrevented) return;
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) setLoading(submitBtn);
          }, 0);
        });
      });
      const banner = document.getElementById('permission-banner');
      const allowBtn = document.getElementById('permission-allow');
      const dismissBtn = document.getElementById('permission-dismiss');
      const dismissKey = 'permission_banner_dismissed';
      const ensureHidden = () => {
        if (Notification.permission !== 'default' && banner) {
          banner.classList.add('hidden');
        }
      };
      const shouldPrompt = () => {
        if (!('Notification' in window)) return false;
        if (localStorage.getItem(dismissKey) === '1') return false;
        if (Notification.permission !== 'default') return false;
        return true;
      };
      if (shouldPrompt() && banner) {
        banner.classList.remove('hidden');
      }
      if (allowBtn) {
        allowBtn.addEventListener('click', async () => {
          if (typeof window.requestPushPermission === 'function') {
            await window.requestPushPermission();
          }
          ensureHidden();
        });
      }
      if (dismissBtn) {
        dismissBtn.addEventListener('click', () => {
          localStorage.setItem(dismissKey, '1');
          if (banner) banner.classList.add('hidden');
        });
      }
    });
  </script>
  @stack('scripts')
</body>
</html>
