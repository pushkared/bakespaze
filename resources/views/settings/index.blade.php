@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<section class="settings-page">
  <div class="vo-pattern"></div>
  <div class="settings-shell">
    <header class="settings-head">
      <div>
        <div class="eyebrow">Settings</div>
        <h1>Attendance & Timezone</h1>
        <p class="muted">Only admins can change attendance windows and time settings.</p>
      </div>
    </header>

    <form class="settings-card" method="POST" action="{{ route('settings.update') }}">
      @csrf
      <div class="settings-grid">
        <label>
          <span>Punch-in start</span>
          <input type="time" name="punch_in_start" value="{{ substr($settings->punch_in_start, 0, 5) }}" required>
        </label>
        <label>
          <span>Punch-in end</span>
          <input type="time" name="punch_in_end" value="{{ substr($settings->punch_in_end, 0, 5) }}" required>
        </label>
        <label>
          <span>Break duration</span>
          <select name="break_duration_minutes" required>
            @foreach($breakOptions as $minutes)
              <option value="{{ $minutes }}" @selected($settings->break_duration_minutes == $minutes)>
                {{ $minutes === 30 ? '30 minutes' : ($minutes / 60) . ' hour' . ($minutes > 60 ? 's' : '') }}
              </option>
            @endforeach
          </select>
        </label>
        <label>
          <span>Timezone</span>
          <select name="timezone" required>
            @foreach($timezones as $tz)
              <option value="{{ $tz }}" @selected($settings->timezone === $tz)>{{ $tz }}</option>
            @endforeach
          </select>
        </label>
      </div>
      <div class="form-actions">
        <button type="submit" class="pill-btn solid">Save Settings</button>
      </div>
    </form>
  </div>
</section>
@endsection
