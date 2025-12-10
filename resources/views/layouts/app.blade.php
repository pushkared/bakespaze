<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>@yield('title', 'Bakespaze Â· Dashboard')</title>
  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <meta name="theme-color" content="#0b0b0b">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <link rel="apple-touch-icon" href="{{ asset('images/icon-180.png') }}">
  <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
  @stack('head')
</head>
<body class="page">
  <div class="app-shell">

    {{-- SIDEBAR (common) --}}
    <aside class="sidebar" aria-label="Main navigation">
      <div class="sidebar-top">
        <img src="{{ asset('images/logo.png') }}" alt="Bakespaze" class="sidebar-logo">
        <!-- <div class="brand">Bakespaze</div> -->
      </div>

      <nav class="sidebar-nav" role="navigation" aria-label="Primary">
        <ul>
          <li class="search-row">
            <input type="search" placeholder="Search" aria-label="Search" />
          </li>
          <li class="nav-item"><a href="#"><span class="nav-dot"></span> Dashboard</a></li>
          <li class="nav-item active"><a href="#"><span class="nav-dot"></span> Virtual Office</a></li>
          <li class="nav-item"><a href="#"><span class="nav-dot"></span> Chat</a></li>
          <li class="nav-item"><a href="#"><span class="nav-dot"></span> Calendar</a></li>
          <li class="nav-item"><a href="#"><span class="nav-dot"></span> Attendance</a></li>
          <li class="nav-item"><a href="#"><span class="nav-dot"></span> Analytics</a></li>
          <li class="nav-item"><a href="#"><span class="nav-dot"></span> Settings</a></li>
        </ul>
      </nav>

      <div class="sidebar-footer">
        <div class="company">BizFiz Media</div>
        <div class="date">Fri, Dec 5</div>
      </div>
    </aside>
    <div class="sidebar-overlay" aria-hidden="true"></div>

    {{-- MAIN (common topbar + dynamic content) --}}
    <div class="main">
      <header class="topbar">
        <div class="top-left">
          <button class="hamburger" type="button" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
          </button>
          <div class="company-select">BizFiz Media</div>
          @if(!empty($isManager) && $isManager)
          <div class="workspace-menu">
            <button class="workspace-trigger" type="button" aria-label="Open workspace menu">
              <span class="workspace-name">{{ optional($workspaces->first())->name ?? 'Workspace' }}</span>
              <span class="workspace-caret">▾</span>
            </button>
            <div class="workspace-panel">
              <form method="POST" action="{{ route('workspaces.store') }}" class="workspace-add">
                @csrf
                <input type="text" name="name" placeholder="Add workspace" required>
                <button type="submit">Add</button>
              </form>

              <div class="workspace-list">
                @forelse($workspaces as $ws)
                  <div class="workspace-row">
                    <form method="POST" action="{{ route('workspaces.update', $ws) }}" class="workspace-edit">
                      @csrf
                      <input type="text" name="name" value="{{ $ws->name }}" required>
                      <button type="submit">Save</button>
                    </form>
                    <form method="POST" action="{{ route('workspaces.destroy', $ws) }}" class="workspace-delete" onsubmit="return confirm('Delete workspace?')">
                      @csrf
                      @method('DELETE')
                      <button type="submit" aria-label="Delete workspace">✕</button>
                    </form>
                  </div>
                @empty
                  <div class="workspace-empty">No workspaces yet.</div>
                @endforelse
              </div>

              <div class="workspace-assign">
                <div class="assign-title">Assign user to workspace</div>
                <form method="POST" action="{{ route('workspaces.assign') }}">
                  @csrf
                  <select name="user_id" required>
                    <option value="">Select user</option>
                    @foreach(($workspaceUsers ?? collect()) as $u)
                      <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                  </select>
                  <select name="workspace_id" required>
                    <option value="">Select workspace</option>
                    @foreach($workspaces as $ws)
                      <option value="{{ $ws->id }}">{{ $ws->name }}</option>
                    @endforeach
                  </select>
                  <select name="role" required>
                    <option value="member">Member</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                  </select>
                  <button type="submit">Assign</button>
                </form>
              </div>
            </div>
          </div>
          @endif
        </div>
        <div class="top-right">
          <div class="user-menu">
            <button class="user-menu__trigger" type="button" aria-label="Open profile menu">
              <div class="user-pill">
                <span class="user-name">{{ auth()->user()->name ?? 'User' }}</span>
                <span class="user-role">{{ auth()->user()->role ?? 'Workspace' }}</span>
              </div>
              <span class="user-menu__caret">▾</span>
            </button>
            <div class="user-menu__panel">
              <a href="{{ route('profile.edit') }}" class="user-menu__item">Edit profile</a>
              @if(auth()->check() && in_array(auth()->user()->role ?? '', ['admin','manager']))
                <a href="{{ route('users.index') }}" class="user-menu__item">User list & roles</a>
              @endif
              <a href="{{ route('departments.index') }}" class="user-menu__item">Departments</a>
              <form method="POST" action="{{ route('logout') }}" class="user-menu__item logout-form">
                @csrf
                <button type="submit">Logout</button>
              </form>
            </div>
          </div>
          <button class="create-task">+ Create Task</button>
        </div>
      </header>

      <main class="content-area">
        @yield('content')
      </main>
    </div>

  </div>

  <div class="mobile-splash" aria-hidden="true">
    <div class="mobile-splash__inner">
      <img src="{{ asset('images/logo.png') }}" alt="Bakespaze" class="splash-logo">
      <div class="splash-text">Loading workspace...</div>
    </div>
  </div>

  <script src="{{ asset('js/dashboard.js') }}"></script>
  <script>
    // inline safety toggle in case bundled JS is cached/blocked
    (function() {
      const hamburger = document.querySelector('.hamburger');
      const sidebar = document.querySelector('.sidebar');
      const overlay = document.querySelector('.sidebar-overlay');
      if (!hamburger || !sidebar) return;
      const setSidebar = (open) => {
        sidebar.classList.toggle('open', open);
        document.body.classList.toggle('sidebar-open', open);
        if (overlay) overlay.classList.toggle('open', open);
      };
      hamburger.addEventListener('click', () => setSidebar(!sidebar.classList.contains('open')));
      if (overlay) overlay.addEventListener('click', () => setSidebar(false));

      // user menu fallback toggle
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
          if (userMenu && !userMenu.contains(e.target)) closeUserMenu();
        });
        window.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') closeUserMenu();
        });
      }
    })();
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
  </script>
  @stack('scripts')
</body>
</html>



