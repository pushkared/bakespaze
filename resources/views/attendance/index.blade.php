@extends('layouts.app')

@section('title', 'Attendance')

@section('content')
<section class="attendance-page">
  <div class="vo-pattern"></div>
  <div class="attendance-inner">
    @php
      $profileAvatar = $user->avatar_url ?? null;
      if ($profileAvatar && \Illuminate\Support\Str::startsWith($profileAvatar, ['http://', 'https://']) === false) {
        $profileAvatar = \Illuminate\Support\Facades\Storage::url($profileAvatar);
      }
    @endphp
    <div class="attendance-header">
      <div class="profile-pill">
        <div class="avatar">
          <img src="{{ $profileAvatar ?: asset('images/user-icon.svg') }}" alt="{{ $user->name ?? 'User' }}">
        </div>
        <div class="profile-meta">
          <div class="name">{{ $user->name ?? 'User' }}</div>
          <div class="role">{{ $user->role ?? 'Member' }}</div>
        </div>
      </div>
      <div class="att-actions-right">
        <button type="button" class="att-settings-btn" id="att-settings-btn" aria-label="Attendance settings"></button>
        <div class="att-date">{{ $now->format('D d M') }}</div>
      </div>
    </div>
    @php
      $startTime = $settings->punch_in_start ? substr($settings->punch_in_start, 0, 5) : '09:00';
      $endTime = $settings->punch_in_end ? substr($settings->punch_in_end, 0, 5) : '11:00';
      $punchOutAfter = (int)($settings->punch_out_after_hours ?? 8);
      $autoOutTime = $settings->auto_punch_out_time ? substr($settings->auto_punch_out_time, 0, 5) : '23:55';
      [$startHour24, $startMinute] = array_pad(explode(':', $startTime), 2, '00');
      [$endHour24, $endMinute] = array_pad(explode(':', $endTime), 2, '00');
      [$autoHour24, $autoMinute] = array_pad(explode(':', $autoOutTime), 2, '00');
      $startHour24 = (int) $startHour24;
      $endHour24 = (int) $endHour24;
      $autoHour24 = (int) $autoHour24;
      $startPeriod = $startHour24 >= 12 ? 'PM' : 'AM';
      $endPeriod = $endHour24 >= 12 ? 'PM' : 'AM';
      $autoPeriod = $autoHour24 >= 12 ? 'PM' : 'AM';
      $startHour12 = $startHour24 % 12 ?: 12;
      $endHour12 = $endHour24 % 12 ?: 12;
      $autoHour12 = $autoHour24 % 12 ?: 12;
      $autoMinuteOptions = ['00','15','30','45','55'];
      $startLabel = \Carbon\Carbon::createFromFormat('H:i', $startTime)->format('h:i A');
      $endLabel = \Carbon\Carbon::createFromFormat('H:i', $endTime)->format('h:i A');
    @endphp
    <div class="att-settings-panel" id="att-settings-panel" aria-hidden="true">
      @if(!empty($isAdmin))
        <form class="settings-panel-form" method="POST" action="{{ route('settings.update') }}">
          @csrf
          <div class="settings-grid">
            <label>
              <span>Punch-in start</span>
              <input type="hidden" name="punch_in_start" id="punch_in_start" value="{{ $startTime }}">
              <div class="time-picker" data-target="punch_in_start">
                <select class="time-hour">
                  @for($h = 1; $h <= 12; $h++)
                    <option value="{{ $h }}" @selected($h === $startHour12)>{{ $h }}</option>
                  @endfor
                </select>
                <select class="time-minute">
                  @foreach(['00','15','30','45'] as $m)
                    <option value="{{ $m }}" @selected($m === $startMinute)>{{ $m }}</option>
                  @endforeach
                </select>
                <select class="time-period">
                  <option value="AM" @selected($startPeriod === 'AM')>AM</option>
                  <option value="PM" @selected($startPeriod === 'PM')>PM</option>
                </select>
              </div>
            </label>
            <label>
              <span>Punch-in end</span>
              <input type="hidden" name="punch_in_end" id="punch_in_end" value="{{ $endTime }}">
              <div class="time-picker" data-target="punch_in_end">
                <select class="time-hour">
                  @for($h = 1; $h <= 12; $h++)
                    <option value="{{ $h }}" @selected($h === $endHour12)>{{ $h }}</option>
                  @endfor
                </select>
                <select class="time-minute">
                  @foreach(['00','15','30','45'] as $m)
                    <option value="{{ $m }}" @selected($m === $endMinute)>{{ $m }}</option>
                  @endforeach
                </select>
                <select class="time-period">
                  <option value="AM" @selected($endPeriod === 'AM')>AM</option>
                  <option value="PM" @selected($endPeriod === 'PM')>PM</option>
                </select>
              </div>
            </label>
            <label>
              <span>Punch-out after (hours)</span>
              <input type="number" name="punch_out_after_hours" min="1" max="24" step="1" value="{{ $punchOutAfter }}">
            </label>
            <label>
              <span>Auto punch-out time</span>
              <input type="hidden" name="auto_punch_out_time" id="auto_punch_out_time" value="{{ $autoOutTime }}">
              <div class="time-picker" data-target="auto_punch_out_time">
                <select class="time-hour">
                  @for($h = 1; $h <= 12; $h++)
                    <option value="{{ $h }}" @selected($h === $autoHour12)>{{ $h }}</option>
                  @endfor
                </select>
                <select class="time-minute">
                  @foreach($autoMinuteOptions as $m)
                    <option value="{{ $m }}" @selected($m === $autoMinute)>{{ $m }}</option>
                  @endforeach
                </select>
                <select class="time-period">
                  <option value="AM" @selected($autoPeriod === 'AM')>AM</option>
                  <option value="PM" @selected($autoPeriod === 'PM')>PM</option>
                </select>
              </div>
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
          </div>
          <div class="form-actions">
            <button type="submit" class="pill-btn solid">Save Settings</button>
          </div>
        </form>
      @else
        <div class="att-settings-note">Admin permissions are required to update attendance settings.</div>
      @endif
    </div>
    @if(!empty($isAdmin))
      <form method="GET" action="{{ route('attendance.index') }}" class="att-user-filter">
        <select name="user_id" onchange="this.form.submit()">
          @foreach(($users ?? collect()) as $u)
            <option value="{{ $u->id }}" @selected(($selectedUserId ?? 0) == $u->id)>{{ $u->name }} ({{ $u->email }})</option>
          @endforeach
        </select>
      </form>
    @endif

    <div class="att-grid">
      <div class="att-card">
        <div class="avatar large">
          <img src="{{ $profileAvatar ?: asset('images/user-icon.svg') }}" alt="{{ $user->name ?? 'User' }}">
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

    @if(($viewer->id ?? null) == ($user->id ?? null))
      <div class="att-actions">
        @if(!$todayRecord || $todayRecord->clock_out)
          @if($canPunchIn)
            <form method="POST" action="{{ route('attendance.punchin') }}">
              @csrf
              <button class="pill-btn solid" type="submit">Punch In</button>
            </form>
          @else
            <div class="muted small">Punch in available {{ $startLabel }} - {{ $endLabel }}.</div>
          @endif
        @else
          <form method="POST" action="{{ route('attendance.punchout') }}">
            @csrf
            <button class="pill-btn ghost {{ empty($canPunchOut) ? 'is-disabled' : '' }}" type="submit" {{ empty($canPunchOut) ? 'disabled' : '' }}>Punch Out</button>
          </form>
          @if(empty($canPunchOut))
            <div class="muted small">Punch out available after {{ $punchOutAfterHours }} hours of punching in.</div>
          @endif
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
    @endif
  </div>
</section>
@endsection

@push('scripts')
<script>
  (function(){
    const settingsBtn = document.getElementById('att-settings-btn');
    const settingsPanel = document.getElementById('att-settings-panel');
    if (settingsBtn && settingsPanel) {
      settingsBtn.addEventListener('click', () => {
        const isOpen = settingsPanel.classList.toggle('open');
        settingsPanel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
      });
    }

    const pickers = document.querySelectorAll('.time-picker');
    const to24 = (hour, minute, period) => {
      let h = parseInt(hour, 10) % 12;
      if (period === 'PM') h += 12;
      return `${String(h).padStart(2, '0')}:${minute}`;
    };
    pickers.forEach((picker) => {
      const targetId = picker.dataset.target;
      const target = document.getElementById(targetId);
      if (!target) return;
      const hour = picker.querySelector('.time-hour');
      const minute = picker.querySelector('.time-minute');
      const period = picker.querySelector('.time-period');
      const sync = () => {
        target.value = to24(hour.value, minute.value, period.value);
      };
      hour.addEventListener('change', sync);
      minute.addEventListener('change', sync);
      period.addEventListener('change', sync);
      sync();
    });
  })();
</script>
@endpush
