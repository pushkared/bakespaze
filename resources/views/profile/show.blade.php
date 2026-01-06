@extends('layouts.app')

@section('title', 'Profile')

@section('content')
<section class="profile-page">
  <div class="vo-pattern"></div>
  <div class="profile-shell">
    <header class="profile-head">
      <div class="profile-card">
        <div class="profile-avatar">
          @if(!empty($avatarUrl))
            <img src="{{ $avatarUrl }}" alt="{{ $user->name }}">
          @else
            <div class="avatar-fallback">{{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}</div>
          @endif
        </div>
        <div class="profile-info">
          <div class="eyebrow">Profile</div>
          <h1>{{ $user->name }}</h1>
          <div class="muted">{{ $user->email }}</div>
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
            <a class="pill-btn solid" href="{{ route('chat.index') }}?user={{ $user->id }}">Chat</a>
            <a class="pill-btn ghost" href="{{ route('tasks.index') }}?open_modal=1&assign_to={{ $user->id }}">Assign Task</a>
          </div>
        @endif
      </div>
    </header>

    <div class="profile-grid">
      <div class="profile-panel">
        <h3>Assigned Tasks</h3>
        <div class="profile-task-list">
          @forelse($assignedTasks as $task)
            <div class="profile-task">
              <div class="title">{{ $task->title }}</div>
              <div class="meta">
                {{ $task->workspace?->name ?? 'Workspace' }}
                - {{ $task->due_date ? $task->due_date->format('d M') : 'No due' }}
                - {{ $task->status ?? 'open' }}
              </div>
            </div>
          @empty
            <div class="muted">No assigned tasks.</div>
          @endforelse
        </div>
      </div>
      <div class="profile-panel">
        <h3>Today Tasks</h3>
        <div class="profile-task-list">
          @forelse($todayTasks as $task)
            <div class="profile-task">
              <div class="title">{{ $task->title }}</div>
              <div class="meta">
                {{ $task->workspace?->name ?? 'Workspace' }}
                - {{ $task->due_date ? $task->due_date->format('d M') : 'No due' }}
                - {{ $task->status ?? 'open' }}
              </div>
            </div>
          @empty
            <div class="muted">No tasks due today.</div>
          @endforelse
        </div>
      </div>
    </div>

    @if($viewer->id === $user->id)
      <div class="profile-panel profile-edit">
        <h3>Edit Profile</h3>
        @if(session('status'))
          <div class="profile-alert">{{ session('status') }}</div>
        @endif
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="profile-form">
          @csrf
          <label>
            <span>Name</span>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
          </label>
          <label>
            <span>Email</span>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" readonly>
          </label>
          <label>
            <span>Profile Image</span>
            <input type="file" name="avatar" accept="image/*">
          </label>
          <button type="submit" class="pill-btn solid">Update Profile</button>
        </form>
      </div>
    @endif
  </div>
</section>
@endsection
