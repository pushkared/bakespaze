@extends('layouts.app')

@section('title', 'Workspaces')

@section('content')
<section class="workspace-page">
  <div class="vo-pattern"></div>
  <div class="workspace-inner">
    <header class="workspace-head">
      <div>
        <div class="eyebrow">Spaces</div>
        <h1>Manage Workspaces</h1>
        <p class="muted">Create new spaces, switch between them, and assign teammates.</p>
      </div>
      @if(session('status'))
        <div class="pill success">{{ session('status') }}</div>
      @endif
      <form class="workspace-create" method="POST" action="{{ route('workspaces.store') }}">
        @csrf
        <input type="text" name="name" placeholder="Workspace name" required>
        <button type="submit">Create</button>
      </form>
    </header>

    @php
      $canAssign = $workspaces->contains(function ($ws) {
          $member = $ws->memberships->firstWhere('user_id', auth()->id());
          return $member && $member->role === 'admin';
      });
    @endphp
    @if($canAssign)
      <div class="assign-panel">
        <div>
          <div class="eyebrow">Assign</div>
          <h2>Assign users to a workspace</h2>
          <p class="muted">Select a user, choose a workspace, and set their role.</p>
        </div>
        <form class="assign-form" method="POST" action="{{ route('workspaces.assign') }}">
          @csrf
          <div class="multi-select">
            <div class="multi-search">
              <input type="search" id="user-search" placeholder="Search users" autocomplete="off">
              <button type="button" id="clear-selection">Clear</button>
            </div>
            <div class="multi-options" id="user-options">
              @foreach($users as $u)
                <label class="option-row">
                  <input type="checkbox" name="user_id[]" value="{{ $u->id }}">
                  <span>{{ $u->name }} ({{ $u->email }})</span>
                </label>
              @endforeach
            </div>
          </div>
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
    @endif

    <div class="workspace-grid">
      @forelse($workspaces as $ws)
        @php
          $currentMember = $ws->memberships->firstWhere('user_id', auth()->id());
          $isWorkspaceAdmin = $currentMember && $currentMember->role === 'admin';
        @endphp
        <div class="workspace-card">
          <button class="ws-top ws-toggle" type="button" aria-expanded="false">
            <div>
              <div class="ws-name">{{ $ws->name }}</div>
              <div class="ws-slug">#{{ $ws->slug }}</div>
            </div>
            <span class="ws-caret" aria-hidden="true"></span>
          </button>
          <div class="ws-body">
            <div class="ws-actions">
              <form method="POST" action="{{ route('workspaces.switch') }}">
                @csrf
                <input type="hidden" name="workspace_id" value="{{ $ws->id }}">
                <button type="submit" class="pill-btn small">Switch</button>
              </form>
              @if($isWorkspaceAdmin)
                <form method="POST" action="{{ route('workspaces.destroy', $ws) }}" onsubmit="return confirm('Delete workspace?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="pill-btn small ghost">Delete</button>
                </form>
              @endif
            </div>
            <div class="ws-members">
              @forelse($ws->memberships as $member)
                <div class="member-pill">
                  <div class="avatar-fallback">{{ strtoupper(substr($member->user->name,0,1)) }}</div>
                  <div class="member-meta">
                    <div class="member-name">{{ $member->user->name }}</div>
                    <div class="member-role">{{ strtoupper($member->role) }}</div>
                  </div>
                  @if($isWorkspaceAdmin && $member->user->id !== auth()->id())
                    <form method="POST" action="{{ route('workspaces.members.remove', [$ws, $member->user]) }}" onsubmit="return confirm('Remove this user from the workspace?')">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="pill-btn small ghost">Remove</button>
                    </form>
                  @endif
                </div>
              @empty
                <div class="muted">No members yet.</div>
              @endforelse
            </div>
          </div>
        </div>
      @empty
        <div class="empty-state">
          <div class="eyebrow">Nothing here</div>
          <p class="muted">Create your first workspace to get started.</p>
        </div>
      @endforelse
    </div>
  </div>
</section>
@push('scripts')
<script>
  (function(){
    const search = document.getElementById('user-search');
    const options = document.querySelectorAll('#user-options .option-row');
    const clearBtn = document.getElementById('clear-selection');
    if (search) {
      search.addEventListener('input', () => {
        const term = search.value.toLowerCase();
        options.forEach(opt => {
          const text = opt.textContent.toLowerCase();
          opt.style.display = text.includes(term) ? '' : 'none';
        });
      });
    }
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        document.querySelectorAll('#user-options input[type="checkbox"]').forEach(cb => cb.checked = false);
        if (search) search.value = '';
        options.forEach(opt => opt.style.display = '');
      });
    }

    const cards = document.querySelectorAll('.workspace-card');
    cards.forEach((card) => {
      const toggle = card.querySelector('.ws-toggle');
      const body = card.querySelector('.ws-body');
      if (!toggle || !body) return;
      toggle.addEventListener('click', () => {
        const isOpen = card.classList.contains('is-open');
        document.querySelectorAll('.workspace-card.is-open').forEach((openCard) => {
          if (openCard !== card) {
            openCard.classList.remove('is-open');
            const openToggle = openCard.querySelector('.ws-toggle');
            if (openToggle) openToggle.setAttribute('aria-expanded', 'false');
          }
        });
        card.classList.toggle('is-open', !isOpen);
        toggle.setAttribute('aria-expanded', String(!isOpen));
      });
    });
  })();
</script>
@endpush
@endsection
