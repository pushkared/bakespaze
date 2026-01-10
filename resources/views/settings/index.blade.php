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

    @php
      $startTime = $settings->punch_in_start ? substr($settings->punch_in_start, 0, 5) : '09:00';
      $endTime = $settings->punch_in_end ? substr($settings->punch_in_end, 0, 5) : '11:00';
      [$startHour24, $startMinute] = array_pad(explode(':', $startTime), 2, '00');
      [$endHour24, $endMinute] = array_pad(explode(':', $endTime), 2, '00');
      $startHour24 = (int) $startHour24;
      $endHour24 = (int) $endHour24;
      $startPeriod = $startHour24 >= 12 ? 'PM' : 'AM';
      $endPeriod = $endHour24 >= 12 ? 'PM' : 'AM';
      $startHour12 = $startHour24 % 12 ?: 12;
      $endHour12 = $endHour24 % 12 ?: 12;
    @endphp
    @if(!empty($isAdmin))
      <div class="ios-settings-card settings-section">
        <button type="button" class="settings-disclosure" aria-expanded="false" aria-controls="settings-attendance-panel">
          <div>
            <div class="eyebrow">Admin</div>
            <h2>Attendance &amp; Timezone</h2>
            <p class="muted">Only admins can change attendance windows and time settings.</p>
          </div>
        </button>
        <div class="settings-panel" id="settings-attendance-panel">
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
      </div>

      <div class="ios-settings-card settings-section">
        <button type="button" class="settings-disclosure" aria-expanded="false" aria-controls="settings-leave-panel">
          <div>
            <div class="eyebrow">Admin</div>
            <h2>Leave Policy</h2>
            <p class="muted">Manage yearly allowances for each leave category.</p>
          </div>
        </button>
        <div class="settings-panel" id="settings-leave-panel">
          <form class="settings-panel-form" method="POST" action="{{ route('leaves.categories.update') }}">
            @csrf
            <div class="settings-grid leave-policy-grid">
              @foreach(($leaveCategories ?? collect()) as $category)
                <label>
                  <span>{{ $category->name }} allowance</span>
                  <input type="hidden" name="categories[{{ $category->id }}][id]" value="{{ $category->id }}">
                  <input type="number" name="categories[{{ $category->id }}][yearly_allowance]" min="0" max="365" value="{{ $category->yearly_allowance }}">
                </label>
              @endforeach
            </div>
            <div class="form-actions">
              <button type="submit" class="pill-btn solid">Save Allowances</button>
            </div>
          </form>
        </div>
      </div>

      <div class="ios-settings-card settings-section">
        <button type="button" class="settings-disclosure" aria-expanded="false" aria-controls="settings-holidays-panel">
          <div>
            <div class="eyebrow">Admin</div>
            <h2>Public Holidays</h2>
            <p class="muted">Add or remove holidays for leave calculations.</p>
          </div>
        </button>
        <div class="settings-panel" id="settings-holidays-panel">
          <form class="leave-holiday-form" method="POST" action="{{ route('leaves.holidays.store') }}">
            @csrf
            <input type="text" name="name" placeholder="Holiday name" required>
            <input type="date" name="date" required>
            <button type="submit" class="pill-btn solid">Add Holiday</button>
          </form>
          <div class="leave-holiday-list">
            @forelse(($leaveHolidays ?? collect()) as $holiday)
              <div class="leave-holiday-item">
                <div>
                  <div class="leave-history-title">{{ $holiday->name }}</div>
                  <div class="leave-history-meta">{{ $holiday->date->format('d M Y') }}</div>
                </div>
                <form method="POST" action="{{ route('leaves.holidays.delete', $holiday) }}">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="pill-btn ghost">Remove</button>
                </form>
              </div>
            @empty
              <div class="muted">No holidays added.</div>
            @endforelse
          </div>
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
@push('scripts')
<script>
  (function(){
    const disclosures = document.querySelectorAll('.settings-disclosure');
    disclosures.forEach((btn) => {
      const section = btn.closest('.settings-section');
      const panelId = btn.getAttribute('aria-controls');
      const panel = panelId ? document.getElementById(panelId) : null;
      btn.setAttribute('aria-expanded', 'false');
      if (panel) {
        panel.setAttribute('aria-hidden', 'true');
      }
      btn.addEventListener('click', () => {
        const isOpen = section.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (panel) {
          panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        }
      });
    });

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
@endsection
