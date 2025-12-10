@extends('layouts.app')
<script src="{{ asset('js/dashboard.js') }}"></script>
@section('content')
<div class="content-area">
  <h2 style="color:#fff;margin-bottom:16px;">Users & Roles</h2>
  @if(session('status'))
    <div style="padding:10px;background:rgba(54,255,76,0.1);color:#9f9;">{{ session('status') }}</div>
  @endif
  <div style="display:grid; gap:12px;">
    @foreach($users as $user)
      <form method="POST" action="{{ route('users.update', $user) }}" style="padding:12px;border:1px solid rgba(255,255,255,0.06);border-radius:12px;background:rgba(0,0,0,0.4);color:#fff;display:grid;gap:8px;">
        @csrf
        <div><strong>{{ $user->name }}</strong> ({{ $user->email }})</div>
        <label>
          <div>Name</div>
          <input type="text" name="name" value="{{ old('name', $user->name) }}" required style="width:100%;padding:8px;border-radius:8px;border:1px solid #333;">
        </label>
        <label>
          <div>Email</div>
          <input type="email" name="email" value="{{ old('email', $user->email) }}" required style="width:100%;padding:8px;border-radius:8px;border:1px solid #333;">
        </label>
        <label>
          <div>Role</div>
          <select name="role" style="width:100%;padding:8px;border-radius:8px;border:1px solid #333;">
            @foreach($roles as $role)
              <option value="{{ $role }}" @selected($user->role === $role)>{{ ucfirst($role) }}</option>
            @endforeach
          </select>
        </label>
        <label>
          <div>Department</div>
          <input type="text" name="department" value="{{ old('department', $user->department) }}" placeholder="e.g. Engineering" style="width:100%;padding:8px;border-radius:8px;border:1px solid #333;">
        </label>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
          <button type="submit" style="padding:10px 16px;border:none;border-radius:10px;background:linear-gradient(90deg,#36ff4c,#1bc536);color:#032008;font-weight:700;cursor:pointer;">Save</button>
        </div>
      </form>
    @endforeach
  </div>
</div>
@endsection
