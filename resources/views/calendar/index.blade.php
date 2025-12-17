@extends('layouts.app')

@section('title', 'Calendar')

@section('content')
<section class="calendar-page">
  <div class="vo-pattern"></div>
  <div class="calendar-inner">
    <header class="tasks-head">
      <div>
        <div class="eyebrow">Calendar</div>
        <h1>Schedule</h1>
        <p class="muted">Sync with Google Calendar to view and create events with Meet links.</p>
      </div>
      @if(empty(auth()->user()->google_access_token))
        <a class="pill-btn solid" href="{{ route('calendar.connect') }}">Connect Google Calendar</a>
      @else
        <a class="pill-btn ghost" href="{{ route('calendar.connect') }}">Reconnect</a>
      @endif
    </header>

    @if(!empty(auth()->user()->google_access_token))
    <div class="calendar-grid">
      <div class="calendar-card">
        <h3>Create Event</h3>
        <form class="task-form" method="POST" action="{{ route('calendar.events.store') }}">
          @csrf
          <label>
            <span>Title</span>
            <input type="text" name="title" required>
          </label>
          <label>
            <span>Description</span>
            <textarea name="description" rows="3"></textarea>
          </label>
          <label>
            <span>Start</span>
            <input type="datetime-local" name="start" required>
          </label>
          <label>
            <span>End</span>
            <input type="datetime-local" name="end" required>
          </label>
          <label>
            <span>Invite (workspace users)</span>
            <div class="multi-select">
              <div class="multi-search">
                <input type="search" id="cal-user-search" placeholder="Search users">
                <button type="button" id="cal-user-clear">Clear</button>
              </div>
              <div class="multi-options" id="cal-user-options">
                @foreach($users as $u)
                  <label class="option-row">
                    <input type="checkbox" name="attendee_ids[]" value="{{ $u->id }}">
                    <span>{{ $u->name }} ({{ $u->email }})</span>
                  </label>
                @endforeach
              </div>
            </div>
          </label>
          <label>
            <span>Other attendees (comma separated emails)</span>
            <input type="text" name="attendees" placeholder="user1@example.com, user2@example.com">
          </label>
          <label class="inline-checkbox">
            <input type="checkbox" name="create_meet" value="1" checked>
            <span>Generate Google Meet link</span>
          </label>
          <div class="form-actions">
            <button type="submit" class="pill-btn solid">Create</button>
          </div>
        </form>
      </div>
      <div class="calendar-card">
        <h3>Upcoming Events</h3>
        <div class="calendar-events">
          @forelse($events as $event)
            <div class="event-row">
              <div class="event-title">{{ $event['title'] }}</div>
              <div class="event-time">
                {{ \Carbon\Carbon::parse($event['start'])->setTimezone('Asia/Kolkata')->format('D d M, h:i A') }}
              </div>
              @if(!empty($event['hangoutLink']))
                <div class="event-link"><a href="{{ $event['hangoutLink'] }}" target="_blank">Join Google Meet</a></div>
              @endif
              @if(!empty($event['attendees']))
                <div class="event-attendees">
                  @foreach($event['attendees'] as $att)
                    <span class="event-chip">{{ $att }}</span>
                  @endforeach
                </div>
              @endif
            </div>
          @empty
            <div class="muted">No upcoming events from Google Calendar.</div>
          @endforelse
        </div>
      </div>
    </div>

    <div class="calendar-card">
      <div class="cal-tabs">
        <button class="cal-tab active" data-target="day-view">Day</button>
        <button class="cal-tab" data-target="week-view">Week</button>
        <button class="cal-tab" data-target="month-view">Month</button>
      </div>
      <div class="cal-views">
        <div id="day-view" class="cal-view active">
          @php
            $today = \Carbon\Carbon::today();
            $dayEvents = collect($events)->filter(fn($e) => \Carbon\Carbon::parse($e['start'])->isSameDay($today));
          @endphp
          @forelse($dayEvents as $event)
            <div class="event-row detail-trigger"
              data-title="{{ $event['title'] }}"
              data-start="{{ $event['start'] }}"
              data-end="{{ $event['end'] }}"
              data-meet="{{ $event['hangoutLink'] ?? '' }}"
              data-desc="{{ $event['description'] ?? '' }}"
              data-attendees="{{ implode(',', (array)($event['attendees'] ?? [])) }}"
            >
              <div class="event-title">{{ $event['title'] }}</div>
              <div class="event-time">
                {{ \Carbon\Carbon::parse($event['start'])->setTimezone('Asia/Kolkata')->format('D d M, h:i A') }}
                @if($event['end']) - {{ \Carbon\Carbon::parse($event['end'])->setTimezone('Asia/Kolkata')->format('h:i A') }} @endif
              </div>
            </div>
          @empty
            <div class="muted">No events today.</div>
          @endforelse
        </div>
        <div id="week-view" class="cal-view">
          @php
            $startWeek = \Carbon\Carbon::now()->startOfWeek();
            $weekDays = collect();
            for($d=0; $d<7; $d++){ $weekDays->push($startWeek->copy()->addDays($d)); }
          @endphp
          <div class="week-grid">
            @foreach($weekDays as $day)
              @php
                $dayEvents = collect($events)->filter(fn($e) => \Carbon\Carbon::parse($e['start'])->isSameDay($day));
              @endphp
              <div class="week-day">
                <div class="week-day-title">{{ $day->format('D d M') }}</div>
                @forelse($dayEvents as $event)
                  <div class="event-chip-row detail-trigger"
                    data-title="{{ $event['title'] }}"
                    data-start="{{ $event['start'] }}"
                    data-end="{{ $event['end'] }}"
                    data-meet="{{ $event['hangoutLink'] ?? '' }}"
                    data-desc="{{ $event['description'] ?? '' }}"
                    data-attendees="{{ implode(',', (array)($event['attendees'] ?? [])) }}"
                  >{{ $event['title'] }}</div>
                @empty
                  <div class="muted tiny">No events</div>
                @endforelse
              </div>
            @endforeach
          </div>
        </div>
        <div id="month-view" class="cal-view">
          @php
            $monthStart = \Carbon\Carbon::now()->startOfMonth()->startOfWeek();
            $monthEnd = \Carbon\Carbon::now()->endOfMonth()->endOfWeek();
            $days = [];
            for ($date = $monthStart->copy(); $date->lte($monthEnd); $date->addDay()) {
              $days[] = $date->copy();
            }
          @endphp
          <div class="month-grid">
            @foreach($days as $date)
              @php
                $dayEvents = collect($events)->filter(fn($e) => \Carbon\Carbon::parse($e['start'])->isSameDay($date));
              @endphp
              <div class="month-cell {{ $date->isSameMonth(\Carbon\Carbon::now()) ? '' : 'dim' }}">
                <div class="month-date">{{ $date->format('d') }}</div>
                @foreach($dayEvents->take(2) as $event)
                  <div class="event-chip-row detail-trigger"
                    data-title="{{ $event['title'] }}"
                    data-start="{{ $event['start'] }}"
                    data-end="{{ $event['end'] }}"
                    data-meet="{{ $event['hangoutLink'] ?? '' }}"
                    data-desc="{{ $event['description'] ?? '' }}"
                    data-attendees="{{ implode(',', (array)($event['attendees'] ?? [])) }}"
                  >{{ $event['title'] }}</div>
                @endforeach
                @if($dayEvents->count() > 2)
                  <div class="muted tiny">+{{ $dayEvents->count() - 2 }} more</div>
                @endif
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
    @else
      <div class="empty-state">
        <div class="eyebrow">Not connected</div>
        <p class="muted">Connect Google Calendar to sync events.</p>
        <a class="pill-btn solid" href="{{ route('calendar.connect') }}">Connect Google Calendar</a>
      </div>
    @endif
  </div>
</section>
<div class="event-modal" id="event-modal">
  <div class="event-modal__card">
    <div class="event-modal__head">
      <div id="event-modal-title" class="event-title">Event</div>
      <button type="button" class="event-modal__close" id="event-modal-close">×</button>
    </div>
    <div class="event-modal__body">
      <div id="event-modal-time" class="event-time"></div>
      <div id="event-modal-meet" class="event-link"></div>
      <div id="event-modal-desc" class="muted"></div>
      <div id="event-modal-attendees" class="event-attendees"></div>
    </div>
  </div>
</div>
@push('scripts')
<script>
  (function(){
    const search = document.getElementById('cal-user-search');
    const options = document.querySelectorAll('#cal-user-options .option-row');
    const clearBtn = document.getElementById('cal-user-clear');
    if (search) {
      search.addEventListener('input', () => {
        const term = search.value.toLowerCase();
        options.forEach(opt => {
          const text = opt.textContent.toLowerCase();
          opt.style.display = text.includes(term) ? '' : 'none';
        });
      });
    }
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        document.querySelectorAll('#cal-user-options input[type="checkbox"]').forEach(cb => cb.checked = false);
        if (search) search.value = '';
        options.forEach(opt => opt.style.display = '');
      });
    }

    document.querySelectorAll('.cal-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.cal-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.cal-view').forEach(v => v.classList.remove('active'));
        tab.classList.add('active');
        const target = document.getElementById(tab.dataset.target);
        if (target) target.classList.add('active');
      });
    });

    const detailModal = document.getElementById('event-modal');
    const detailTitle = document.getElementById('event-modal-title');
    const detailTime = document.getElementById('event-modal-time');
    const detailDesc = document.getElementById('event-modal-desc');
    const detailAtt = document.getElementById('event-modal-attendees');
    const detailMeet = document.getElementById('event-modal-meet');
    const closeDetail = document.getElementById('event-modal-close');

    document.querySelectorAll('.detail-trigger').forEach(row => {
      row.addEventListener('click', () => {
        if (!detailModal) return;
        detailTitle.textContent = row.dataset.title || 'Event';
        detailTime.textContent = [row.dataset.start, row.dataset.end].filter(Boolean).join(' - ');
        detailDesc.textContent = row.dataset.desc || 'No description';
        const attendees = (row.dataset.attendees || '').split(',').filter(Boolean);
        detailAtt.innerHTML = attendees.length ? attendees.map(a => `<span class="event-chip">${a}</span>`).join(' ') : '<span class="muted">No attendees</span>';
        if (row.dataset.meet) {
          detailMeet.innerHTML = `<a href="${row.dataset.meet}" target="_blank">Join Google Meet</a>`;
        } else {
          detailMeet.innerHTML = '<span class="muted">No meet link</span>';
        }
        detailModal.classList.add('open');
      });
    });
    if (closeDetail) {
      closeDetail.addEventListener('click', () => detailModal.classList.remove('open'));
    }
  })();
</script>
@endpush
@endsection
