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
            <div class="event-row detail-trigger"
              data-id="{{ $event['id'] }}"
              data-title="{{ $event['title'] }}"
              data-start="{{ $event['start'] }}"
              data-end="{{ $event['end'] }}"
              data-meet="{{ $event['hangoutLink'] ?? '' }}"
              data-desc="{{ $event['description'] ?? '' }}"
              data-attendees="{{ implode(',', (array)($event['attendees'] ?? [])) }}"
              data-organizer="{{ !empty($event['isOrganizer']) ? '1' : '0' }}"
            >
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
              data-id="{{ $event['id'] }}"
              data-title="{{ $event['title'] }}"
              data-start="{{ $event['start'] }}"
              data-end="{{ $event['end'] }}"
              data-meet="{{ $event['hangoutLink'] ?? '' }}"
              data-desc="{{ $event['description'] ?? '' }}"
              data-attendees="{{ implode(',', (array)($event['attendees'] ?? [])) }}"
              data-organizer="{{ !empty($event['isOrganizer']) ? '1' : '0' }}"
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
                    data-id="{{ $event['id'] }}"
                    data-title="{{ $event['title'] }}"
                    data-start="{{ $event['start'] }}"
                    data-end="{{ $event['end'] }}"
                    data-meet="{{ $event['hangoutLink'] ?? '' }}"
                    data-desc="{{ $event['description'] ?? '' }}"
                    data-attendees="{{ implode(',', (array)($event['attendees'] ?? [])) }}"
                    data-organizer="{{ !empty($event['isOrganizer']) ? '1' : '0' }}"
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
                    data-id="{{ $event['id'] }}"
                    data-title="{{ $event['title'] }}"
                    data-start="{{ $event['start'] }}"
                    data-end="{{ $event['end'] }}"
                    data-meet="{{ $event['hangoutLink'] ?? '' }}"
                    data-desc="{{ $event['description'] ?? '' }}"
                    data-attendees="{{ implode(',', (array)($event['attendees'] ?? [])) }}"
                    data-organizer="{{ !empty($event['isOrganizer']) ? '1' : '0' }}"
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
      <button type="button" class="pill-btn small ghost" id="event-copy-link" hidden>Copy Meet Link</button>
      <div id="event-modal-desc" class="muted"></div>
      <div id="event-modal-attendees" class="event-attendees"></div>
      <div class="event-actions" id="event-modal-actions" hidden>
        <button type="button" class="pill-btn small ghost" id="event-edit-toggle">Edit</button>
        <form method="POST" id="event-delete-form" data-action-template="{{ route('calendar.events.destroy', 'EVENT_ID') }}" onsubmit="return confirm('Delete this event?')">
          @csrf
          @method('DELETE')
          <button type="submit" class="pill-btn small">Delete</button>
        </form>
      </div>
      <form method="POST" id="event-edit-form" data-action-template="{{ route('calendar.events.update', 'EVENT_ID') }}" hidden>
        @csrf
        @method('PUT')
        <label>
          <span>Title</span>
          <input type="text" name="title" required maxlength="255">
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
          <span>Description</span>
          <textarea name="description" rows="3"></textarea>
        </label>
        <div class="form-actions">
          <button type="button" class="pill-btn ghost" id="event-edit-cancel">Cancel</button>
          <button type="submit" class="pill-btn solid">Update</button>
        </div>
      </form>
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
    const copyBtn = document.getElementById('event-copy-link');
    const closeDetail = document.getElementById('event-modal-close');
    const actionWrap = document.getElementById('event-modal-actions');
    const editToggle = document.getElementById('event-edit-toggle');
    const editForm = document.getElementById('event-edit-form');
    const editCancel = document.getElementById('event-edit-cancel');
    const deleteForm = document.getElementById('event-delete-form');

    const toLocalInput = (value) => {
      if (!value) return '';
      const d = new Date(value);
      if (Number.isNaN(d.getTime())) return '';
      const pad = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    };
    const applyAction = (form, id) => {
      if (!form || !form.dataset.actionTemplate || !id) return;
      form.action = form.dataset.actionTemplate.replace('EVENT_ID', encodeURIComponent(id));
    };

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
          if (copyBtn) {
            copyBtn.hidden = false;
            copyBtn.dataset.link = row.dataset.meet;
            copyBtn.textContent = 'Copy Meet Link';
          }
        } else {
          detailMeet.innerHTML = '<span class="muted">No meet link</span>';
          if (copyBtn) {
            copyBtn.hidden = true;
            copyBtn.dataset.link = '';
          }
        }
        const isOrganizer = row.dataset.organizer === '1';
        if (actionWrap) actionWrap.hidden = !isOrganizer;
        if (editForm) editForm.hidden = true;
        if (isOrganizer && editForm) {
          const titleInput = editForm.querySelector('input[name="title"]');
          const startInput = editForm.querySelector('input[name="start"]');
          const endInput = editForm.querySelector('input[name="end"]');
          const descInput = editForm.querySelector('textarea[name="description"]');
          if (titleInput) titleInput.value = row.dataset.title || '';
          if (startInput) startInput.value = toLocalInput(row.dataset.start);
          if (endInput) endInput.value = toLocalInput(row.dataset.end);
          if (descInput) descInput.value = row.dataset.desc || '';
          applyAction(editForm, row.dataset.id);
          applyAction(deleteForm, row.dataset.id);
        }
        detailModal.classList.add('open');
      });
    });
    if (editToggle && editForm) {
      editToggle.addEventListener('click', () => {
        editForm.hidden = !editForm.hidden;
      });
    }
    if (editCancel && editForm) {
      editCancel.addEventListener('click', () => {
        editForm.hidden = true;
      });
    }
    if (closeDetail) {
      closeDetail.addEventListener('click', () => {
        detailModal.classList.remove('open');
        if (editForm) editForm.hidden = true;
      });
    }

    if (copyBtn) {
      copyBtn.addEventListener('click', async () => {
        const link = copyBtn.dataset.link || '';
        if (!link) return;
        try {
          await navigator.clipboard.writeText(link);
          copyBtn.textContent = 'Copied';
          setTimeout(() => { copyBtn.textContent = 'Copy Meet Link'; }, 1200);
        } catch (err) {
          const temp = document.createElement('textarea');
          temp.value = link;
          document.body.appendChild(temp);
          temp.select();
          document.execCommand('copy');
          temp.remove();
          copyBtn.textContent = 'Copied';
          setTimeout(() => { copyBtn.textContent = 'Copy Meet Link'; }, 1200);
        }
      });
    }

    const scheduleReminders = () => {
      if (!('Notification' in window)) return;
      if (Notification.permission !== 'granted') return;
      const rows = document.querySelectorAll('.detail-trigger');
      const events = new Map();
      rows.forEach((row) => {
        const id = row.dataset.id;
        const start = row.dataset.start;
        if (!id || !start) return;
        if (!events.has(id)) {
          events.set(id, {
            id,
            title: row.dataset.title || 'Meeting',
            start,
            meet: row.dataset.meet || '',
          });
        }
      });

      const now = Date.now();
      events.forEach((event) => {
        const startTime = Date.parse(event.start);
        if (!startTime || Number.isNaN(startTime)) return;
        if (startTime <= now) return;
        const remindAt = startTime - (10 * 60 * 1000);
        const delay = remindAt - now;
        const notify = () => {
          const body = event.meet ? `Join: ${event.meet}` : 'Join the call.';
          new Notification('Meeting in 10 minutes', {
            body: `${event.title} - ${body}`,
            tag: `meeting-${event.id}`,
          });
        };
        if (delay <= 0) {
          notify();
        } else {
          setTimeout(notify, delay);
        }
      });
    };
    scheduleReminders();

    const params = new URLSearchParams(window.location.search);
    const eventId = params.get('event');
    if (eventId) {
      const safeId = window.CSS && CSS.escape ? CSS.escape(eventId) : eventId.replace(/"/g, '\\"');
      const target = document.querySelector(`.detail-trigger[data-id="${safeId}"]`);
      if (target) {
        target.click();
      }
    }
  })();
</script>
@endpush
@endsection
