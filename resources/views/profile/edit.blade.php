@extends('layouts.app')
<script src="{{ asset('js/dashboard.js') }}"></script>
@section('content')
<div class="content-area">
  <h2 style="color:#fff;margin-bottom:16px;">Edit Profile</h2>
  @if(session('status'))
    <div style="padding:10px;background:rgba(54,255,76,0.1);color:#9f9;">{{ session('status') }}</div>
  @endif
  <form method="POST" action="{{ route('profile.update') }}" style="max-width:480px; display:grid; gap:12px; color:#fff;">
    @csrf
    <label>
      <div>Name</div>
      <input type="text" name="name" value="{{ old('name', $user->name) }}" required style="width:100%;padding:10px;border-radius:8px;border:1px solid #333;">
    </label>
    <label>
      <div>Email</div>
      <input type="email" name="email" value="{{ old('email', $user->email) }}" required style="width:100%;padding:10px;border-radius:8px;border:1px solid #333;">
    </label>
    <label>
      <div>New Password (optional)</div>
      <input type="password" name="password" autocomplete="new-password" style="width:100%;padding:10px;border-radius:8px;border:1px solid #333;">
    </label>
    <label>
      <div>Confirm Password</div>
      <input type="password" name="password_confirmation" autocomplete="new-password" style="width:100%;padding:10px;border-radius:8px;border:1px solid #333;">
    </label>
    <button type="submit" style="padding:12px;border:none;border-radius:10px;background:linear-gradient(90deg,#36ff4c,#1bc536);color:#032008;font-weight:700;cursor:pointer;">Save changes</button>
  </form>
</div>
@endsection
