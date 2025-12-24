@extends('layouts.app')
@section('content')
<section class="users-page">
  <div class="vo-pattern"></div>
  <div class="users-shell">
    <header class="users-head">
      <div>
        <div class="eyebrow">Team</div>
        <h1>Users & Roles</h1>
        <p class="muted">Assign roles with predefined access: Super Admin (full), HR (all attendance), Manager (team attendance), Employee (self).</p>
      </div>
      @if(session('status'))
        <div class="pill success">{{ session('status') }}</div>
      @endif
    </header>

    <div class="users-grid">
      @foreach($users as $user)
        <form method="POST" action="{{ route('users.update', $user) }}" class="user-card">
          @csrf
          <div class="user-card__head">
            <div class="user-name">{{ $user->name }}</div>
            <div class="user-email">{{ $user->email }}</div>
          </div>
          <label class="field">
            <span class="label">Name</span>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
          </label>
          <label class="field">
            <span class="label">Email</span>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
          </label>
          <label class="field">
            <span class="label">Role</span>
            <select name="role" required>
              @foreach($roles as $role)
                <option value="{{ $role }}" @selected($user->role === $role)>{{ ucwords(str_replace('_',' ', $role)) }}</option>
              @endforeach
            </select>
            <small class="muted">
              @switch($user->role)
                @case('super_admin') Full control across app. @break
                @case('hr') View all attendance. @break
                @case('manager') View team attendance. @break
                @default View own attendance.
              @endswitch
            </small>
          </label>
          <label class="field">
            <span class="label">Department</span>
            <input type="text" name="department" value="{{ old('department', $user->department) }}" placeholder="e.g. Engineering">
          </label>
          <div class="user-card__actions">
            <button type="submit" class="pill-btn solid">Save</button>
          </div>
        </form>
      @endforeach
    </div>
  </div>
</section>
@endsection
