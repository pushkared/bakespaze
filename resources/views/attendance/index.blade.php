@extends('layouts.app')

@section('title', 'Attendance')

@section('content')
<section class="attendance-page">
  <div class="vo-pattern"></div>
  <div class="attendance-inner">
    <div class="attendance-header">
      <div class="profile-pill">
        <div class="avatar">
          <img src="{{ asset('images/user-icon.svg') }}" alt="{{ $user->name ?? 'User' }}">
        </div>
        <div class="profile-meta">
          <div class="name">{{ $user->name ?? 'User' }}</div>
          <div class="role">{{ $user->role ?? 'Member' }}</div>
        </div>
      </div>
      <div class="att-date">{{ $now->format('D d M') }}</div>
    </div>

    <div class="att-grid">
      <div class="att-card">
        <div class="avatar large">
          <img src="{{ asset('images/user-icon.svg') }}" alt="{{ $user->name ?? 'User' }}">
        </div>
        <div class="att-title">{{ $user->name ?? 'User' }}</div>
        <div class="att-sub">{{ $user->role ?? 'Member' }}</div>
      </div>
      <div class="att-card">
        <div class="att-title-sm">Today's Hours</div>
        <div class="att-hours">{{ $stats['today_hours'] }}</div>
      </div>
      <div class="att-card">
        <div class="att-title-sm">Weekly Hours</div>
        <div class="att-hours">{{ $stats['week_hours'] }}</div>
      </div>
      <div class="att-card">
        <div class="att-title-sm">Total Sessions</div>
        <div class="att-hours">{{ $stats['sessions'] }}</div>
      </div>
    </div>

    <div class="att-activity">
      <div class="att-activity-head">Recent Activity (Last 7 Days)</div>
      <div class="att-activity-grid">
        @foreach($recent as $entry)
          <div class="att-activity-pill">
            <div class="att-activity-date">{{ $entry['label'] }}</div>
            <div class="att-activity-hours">{{ $entry['hours'] }}</div>
          </div>
        @endforeach
      </div>
    </div>

    <div class="att-actions">
      @if(!$todayRecord || $todayRecord->clock_out)
        @if($canPunchIn)
          <form method="POST" action="{{ route('attendance.punchin') }}">
            @csrf
            <button class="pill-btn solid" type="submit">Punch In</button>
          </form>
        @else
          <div class="muted small">Punch in available 9:00 AM – 11:00 AM IST.</div>
        @endif
      @else
        <form method="POST" action="{{ route('attendance.punchout') }}">
          @csrf
          <button class="pill-btn ghost" type="submit">Punch Out</button>
        </form>
        @if($breakActive)
          <form method="POST" action="{{ route('attendance.lunchend') }}">
            @csrf
            <button class="pill-btn solid" type="submit">End Break</button>
          </form>
        @elseif($breakLimit > 0 && $breakUsed >= $breakLimit)
          <button class="pill-btn solid is-disabled" disabled>Break Used</button>
        @else
          <form method="POST" action="{{ route('attendance.lunchstart') }}">
            @csrf
            <button class="pill-btn solid" type="submit">Take Break</button>
          </form>
        @endif
      @endif
    </div>
  </div>
</section>
@endsection
