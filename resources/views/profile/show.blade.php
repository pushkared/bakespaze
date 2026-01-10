@extends('layouts.app')

@section('title', 'Profile')

@section('content')
<section class="profile-page">
  <div class="vo-pattern"></div>
  <div class="profile-shell">
    <header class="profile-head ios-settings-head">
      <div class="eyebrow">Settings</div>
      <h1>Profile</h1>
    </header>

    <div class="ios-settings-card profile-hero">
      <div class="profile-avatar">
        <label class="avatar-upload" for="profile-avatar-input" @if($viewer->id !== $user->id) aria-disabled="true" @endif>
          @if(!empty($avatarUrl))
            <img src="{{ $avatarUrl }}" alt="{{ $user->name }}">
          @else
            <div class="avatar-fallback">{{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}</div>
          @endif
          @if($viewer->id === $user->id)
            <span class="avatar-edit">Edit</span>
          @endif
        </label>
      </div>
      <div class="profile-info">
        <div class="profile-name">{{ $user->name }}</div>
        <div class="profile-email">{{ $user->email }}</div>
        <div class="profile-meta-row">
          <div class="profile-meta">
            <span class="label">Punched In</span>
            <span class="value">{{ $punchInTime ?? 'Not punched' }}</span>
          </div>
          <div class="profile-meta">
            <span class="label">Total Hours</span>
            <span class="value">{{ $todayHours }}</span>
          </div>
        </div>
      </div>
      @if($viewer->id !== $user->id)
        <div class="profile-actions">
          <a class="ios-row-action" href="{{ route('chat.index') }}?user={{ $user->id }}">Chat</a>
          <a class="ios-row-action" href="{{ route('tasks.index') }}?open_modal=1&assign_to={{ $user->id }}">Assign Task</a>
        </div>
      @endif
    </div>

    @if($viewer->id === $user->id)
      <div class="ios-settings-card">
        @if(session('status'))
          <div class="profile-alert">{{ session('status') }}</div>
        @endif
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="ios-form">
          @csrf
          <input type="file" id="profile-avatar-input" name="avatar" accept="image/*" hidden>
          <div class="ios-row">
            <span>Name</span>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
          </div>
          <div class="ios-row">
            <span>Email</span>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" readonly>
          </div>
          <div class="ios-row actions">
            <button type="submit" class="pill-btn solid">Update Profile</button>
          </div>
        </form>
      </div>
    @else
      <input type="file" id="profile-avatar-input" hidden>
    @endif

    <div class="ios-settings-card">
      <div class="ios-section-title">Assigned Tasks</div>
      <div class="profile-task-list">
        @forelse($assignedTasks as $task)
          <div class="profile-task ios-row">
            <div>
              <div class="title">{{ $task->title }}</div>
              <div class="meta">
                {{ $task->workspace?->name ?? 'Workspace' }}
                - {{ $task->due_date ? $task->due_date->format('d M') : 'No due' }}
                - {{ $task->status ?? 'open' }}
              </div>
            </div>
          </div>
        @empty
          @if(!$hasSharedWorkspace)
            <div class="muted">Tasks are visible only within shared workspaces.</div>
          @else
          <div class="muted">No assigned tasks.</div>
          @endif
        @endforelse
      </div>
    </div>

    <div class="ios-settings-card">
      <div class="ios-section-title">Today Tasks</div>
      <div class="profile-task-list">
        @forelse($todayTasks as $task)
          <div class="profile-task ios-row">
            <div>
              <div class="title">{{ $task->title }}</div>
              <div class="meta">
                {{ $task->workspace?->name ?? 'Workspace' }}
                - {{ $task->due_date ? $task->due_date->format('d M') : 'No due' }}
                - {{ $task->status ?? 'open' }}
              </div>
            </div>
          </div>
        @empty
          @if(!$hasSharedWorkspace)
            <div class="muted">Tasks are visible only within shared workspaces.</div>
          @else
          <div class="muted">No tasks due today.</div>
          @endif
        @endforelse
      </div>
    </div>
  </div>
</section>
@push('scripts')
<script>
  (function(){
    const avatarInput = document.getElementById('profile-avatar-input');
    const avatarTrigger = document.querySelector('.avatar-upload');
    if (avatarInput && avatarTrigger) {
      avatarTrigger.addEventListener('click', (e) => {
        if (avatarTrigger.getAttribute('aria-disabled') === 'true') return;
        avatarInput.click();
      });
    }
  })();
</script>
@endpush
@endsection
