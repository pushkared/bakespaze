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
      <form class="workspace-create" method="POST" action="{{ route('workspaces.store') }}">
        @csrf
        <input type="text" name="name" placeholder="Workspace name" required>
        <button type="submit">Create</button>
      </form>
    </header>

    <div class="workspace-grid">
      @forelse($workspaces as $ws)
        <div class="workspace-card">
          <div class="ws-top">
            <div>
              <div class="ws-name">{{ $ws->name }}</div>
              <div class="ws-slug">#{{ $ws->slug }}</div>
            </div>
            <form method="POST" action="{{ route('workspaces.switch') }}">
              @csrf
              <input type="hidden" name="workspace_id" value="{{ $ws->id }}">
              <button type="submit" class="pill-btn small">Switch</button>
            </form>
          </div>
          <div class="ws-members">
            @forelse($ws->memberships as $member)
              <div class="member-pill">
                <div class="avatar-fallback">{{ strtoupper(substr($member->user->name,0,1)) }}</div>
                <div class="member-meta">
                  <div class="member-name">{{ $member->user->name }}</div>
                  <div class="member-role">{{ strtoupper($member->role) }}</div>
                </div>
              </div>
            @empty
              <div class="muted">No members yet.</div>
            @endforelse
          </div>
        </div>
      @empty
        <div class="empty-state">
          <div class="eyebrow">Nothing here</div>
          <p class="muted">Create your first workspace to get started.</p>
        </div>
      @endforelse
    </div>

    <div class="assign-panel">
      <div>
        <div class="eyebrow">Assign</div>
        <h2>Assign users to a workspace</h2>
        <p class="muted">Select a user, choose a workspace, and set their role.</p>
      </div>
      <form class="assign-form" method="POST" action="{{ route('workspaces.assign') }}">
        @csrf
        <select name="user_id" required>
          <option value="">Select user</option>
          @foreach($users as $u)
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
</section>
@endsection
