@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<section class="settings-page">
  <div class="vo-pattern"></div>
  <div class="settings-shell">
    <header class="settings-head ios-settings-head">
      <div class="eyebrow">Settings</div>
      <h1>Settings</h1>
      <p class="muted">Manage your account and workspace preferences.</p>
    </header>

    <div class="ios-settings-card">
      <a class="ios-row ios-row-link" href="{{ route('profile.edit') }}">
        <span>Update Profile</span>
        <span class="ios-row-meta">Edit name and photo</span>
      </a>
    </div>
    <form class="ios-settings-card" method="POST" action="{{ route('settings.notifications') }}">
      @csrf
      <div class="ios-row">
        <div>
          <div class="eyebrow">Notifications</div>
          <div class="settings-title">App notifications</div>
          <div class="muted">Enable or disable push and bell alerts.</div>
        </div>
        <label class="switch">
          <input type="checkbox" name="notifications_enabled" {{ auth()->user()?->notifications_enabled ? 'checked' : '' }}>
          <span class="slider"></span>
        </label>
      </div>
      <div class="form-actions">
        <button type="submit" class="pill-btn solid">Save Preference</button>
      </div>
    </form>

    @if(!empty($isAdmin))
      <form class="ios-settings-card" method="POST" action="{{ route('settings.timezone') }}">
        @csrf
        <div class="ios-row stack-on-mobile timezone-row">
          <div>
            <div class="eyebrow">Admin</div>
            <div class="settings-title">Timezone</div>
            <div class="muted">Sets the default timezone for attendance and reminders.</div>
          </div>
          <select name="timezone" class="ios-select">
            @foreach($timezones as $tz)
              <option value="{{ $tz }}" @selected($settings->timezone === $tz)>{{ $tz }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-actions">
          <button type="submit" class="pill-btn solid">Save Timezone</button>
        </div>
      </form>
    @else
      <div class="ios-settings-card">
        <div class="ios-row stack-on-mobile timezone-row">
          <div>
            <div class="eyebrow">General</div>
            <div class="settings-title">Timezone</div>
            <div class="muted">Managed by admin</div>
          </div>
          <span class="ios-row-meta">{{ $settings->timezone }}</span>
        </div>
      </div>
    @endif
    <form class="ios-settings-card danger" method="POST" action="{{ route('account.destroy') }}" onsubmit="return confirm('Delete your account? This will remove your tasks and workspace access.');">
      @csrf
      @method('DELETE')
      <div>
        <div class="eyebrow">Account</div>
        <h2>Delete account</h2>
        <p class="muted">This removes your account, tasks you created, and your workspace memberships.</p>
      </div>
      <div class="form-actions">
        <button type="submit" class="pill-btn danger">Delete Account</button>
      </div>
    </form>
  </div>
</section>
@endsection
