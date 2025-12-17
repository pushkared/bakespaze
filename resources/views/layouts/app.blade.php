<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>@yield('title', 'Virtual Office')</title>
  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <meta name="theme-color" content="#0b0b0b">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <link rel="apple-touch-icon" href="{{ asset('images/icon-180.png') }}">
  <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
  @stack('head')
</head>
<body class="vo-body">
  <div class="app-shell vo-shell">

    <aside class="sidebar vo-sidebar" aria-label="Main navigation">
      <div class="sidebar-inner">
        <div class="sidebar-brand">
          <div class="logo-mark">
            <img src="{{ asset('images/logo-infinity.svg') }}" alt="Logo">
          </div>
        </div>

        <label class="sidebar-search">
          <span class="icon-search" aria-hidden="true"></span>
          <input type="search" placeholder="Search" aria-label="Search" />
        </label>

        <nav class="sidebar-menu" role="navigation" aria-label="Primary">
          <a class="menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="menu-icon dashboard" aria-hidden="true"></span><span class="menu-text">Dashboard</span></a>
          <a class="menu-link {{ request()->routeIs('virtual-office') ? 'active' : '' }}" href="{{ route('virtual-office') }}"><span class="menu-icon virtual" aria-hidden="true"></span><span class="menu-text">Virtual Office</span></a>
          <a class="menu-link {{ request()->routeIs('tasks.*') ? 'active' : '' }}" href="{{ route('tasks.index') }}"><span class="menu-icon analytics" aria-hidden="true"></span><span class="menu-text">Tasks</span></a>
          <a class="menu-link {{ request()->routeIs('workspaces.index') ? 'active' : '' }}" href="{{ route('workspaces.index') }}"><span class="menu-icon settings" aria-hidden="true"></span><span class="menu-text">Workspaces</span></a>
          <a class="menu-link" href="#"><span class="menu-icon chat" aria-hidden="true"></span><span class="menu-text">Chat</span></a>
          <a class="menu-link" href="/calendar"><span class="menu-icon calendar" aria-hidden="true"></span><span class="menu-text">Calendar</span></a>
          <a class="menu-link" href="/attendance"><span class="menu-icon attendance" aria-hidden="true"></span><span class="menu-text">Attendance</span></a>
          <a class="menu-link" href="#"><span class="menu-icon analytics" aria-hidden="true"></span><span class="menu-text">Analytics</span></a>
          <a class="menu-link" href="#"><span class="menu-icon settings" aria-hidden="true"></span><span class="menu-text">Settings</span></a>
        </nav>
      </div>
      <div class="sidebar-footer">
        <div class="footer-date">Fri 5 Dec</div>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="menu-link logout-link">
            <span class="menu-icon logout" aria-hidden="true"></span>
            <span class="menu-text">Logout</span>
          </button>
        </form>
      </div>
    </aside>
    <div class="sidebar-overlay" aria-hidden="true"></div>

    <div class="main vo-main">
      <header class="topbar vo-topbar">
        <div class="top-left">
          <button class="hamburger" type="button" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
          </button>
          <div class="brand-lockup">
            <img src="{{ asset('images/logo-infinity.svg') }}" alt="Logo">
          </div>
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
        </div>
        <div class="top-right">
          <button class="create-task">+ Create Task</button>
          <div class="top-date">{{ \Carbon\Carbon::now()->format('D d M') }}</div>
          <div class="top-logo">
            <img src="{{ asset('images/logo-infinity.svg') }}" alt="Logo">
          </div>
        </div>
      </header>

      <main class="content-area">
        @yield('content')
      </main>
    </div>
  </div>

  <button class="floating-task" type="button" aria-label="Open tasks">
    <img src="{{ asset('images/tasklist-icon.svg') }}" alt="">
  </button>
  <div class="floating-panel" id="floating-panel">
    <div class="floating-head">
      <div>
        <div class="eyebrow">Workspace Tasks</div>
        <div class="floating-title">{{ $currentWorkspace->name ?? 'Tasks' }}</div>
      </div>
      <button class="close-floating" type="button" aria-label="Close panel">×</button>
    </div>
    <div class="floating-list">
      @forelse(($panelTasks ?? collect()) as $task)
        <div class="float-task">
          <div class="float-main">
            <div class="float-title">{{ $task->title }}</div>
            <div class="float-meta">
              <span>{{ $task->due_date ? $task->due_date->format('d M') : 'No due' }}</span>
              @if($task->assignees->first())
                <span class="avatar-chip small">{{ strtoupper(substr($task->assignees->first()->name,0,1)) }}</span>
              @endif
            </div>
          </div>
          <div class="float-actions">
            @if($task->status !== 'completed')
            <form method="POST" action="{{ route('tasks.update', $task) }}">
              @csrf
              <input type="hidden" name="title" value="{{ $task->title }}">
              <input type="hidden" name="description" value="{{ $task->description }}">
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
      const toggle = () => panel && panel.classList.toggle('open');
      if (btn && panel) btn.addEventListener('click', toggle);
      if (closeBtn) closeBtn.addEventListener('click', toggle);
    });
  </script>
  @stack('scripts')
</body>
</html>
