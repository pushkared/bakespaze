@extends('layouts.app')

@section('title', 'Virtual Office')

@section('content')
<section class="vo-stage">
  <div class="vo-pattern"></div>
  <div class="vo-board room-stage">
    @php
      $seatClasses = ['seat-boss','seat-vikas','seat-pushkar','seat-anmol','seat-manjot','seat-gaurav','seat-right','seat-empty1','seat-empty2','seat-empty3'];
      @endphp

    @foreach(($members ?? collect())->take(10) as $index => $member)
      @php
        $seatClass = $seatClasses[$index] ?? 'seat-dynamic';
        $summary = $summaries[$member->user->id] ?? ['logged_today' => false, 'currently_in' => false, 'status_label' => 'Not Logged In', 'punch_in_time' => null, 'hours_today' => '0h 0m', 'tasks' => []];
        $theme = $summary['logged_today'] ? 'seat-green' : 'seat-red';
      @endphp
      <div class="seat-card {{ $theme }} {{ $seatClass }}"
        data-detail="detail-{{ $member->user->id }}"
        data-user="{{ $member->user->name }}"
        data-role="{{ ucfirst($member->role ?? 'Member') }}"
        data-punched="{{ $summary['logged_today'] ? '1' : '0' }}"
      >
        <div class="avatar">
          <img src="{{ asset('images/user-icon.svg') }}" alt="{{ $member->user->name }}">
        </div>
        <div class="name">{{ $member->user->name }}</div>
        <div class="role">{{ ucfirst($member->role ?? 'Member') }}</div>
      </div>
      <div class="seat-detail" id="detail-{{ $member->user->id }}">
        <div class="detail-head">
          <div>
            <div class="detail-name">{{ $member->user->name }}</div>
            <div class="detail-role">{{ ucfirst($member->role ?? 'Member') }}</div>
          </div>
          <div class="detail-status {{ $summary['logged_today'] ? 'online' : 'offline' }}">
            {{ $summary['status_label'] }}
          </div>
        </div>
        <div class="detail-meta">
          <div class="meta-block">
            <div class="meta-label">Hours Today</div>
            <div class="meta-value">{{ $summary['hours_today'] }}</div>
          </div>
          <div class="meta-block">
            <div class="meta-label">Punch In</div>
            <div class="meta-value">{{ $summary['punch_in_time'] ?? '—' }}</div>
          </div>
          <div class="meta-block">
            <div class="meta-label">Punch Out</div>
            <div class="meta-value">
              @if(!empty($summary['punch_out_time']))
                {{ $summary['punch_out_time'] }}
              @elseif(!empty($summary['currently_in']))
                Active
              @else
                —
              @endif
            </div>
          </div>
        </div>
        <div class="detail-tasks">
          <div class="detail-label">Today’s Tasks</div>
          @forelse($summary['tasks'] as $task)
            <div class="task-line">
              <div class="task-title">{{ $task['title'] }}</div>
              <div class="task-meta">
                <span class="task-pill status-{{ $task['status'] }}">{{ ucfirst($task['status']) }}</span>
                @if($task['due'])
                  <span class="task-pill pill-ghost">{{ $task['due'] }}</span>
                @endif
              </div>
            </div>
          @empty
            <div class="muted">No tasks due today.</div>
          @endforelse
        </div>
      </div>
    @endforeach

    <div class="board-table meeting-table">
      <div class="table-message">
        @if($nextMeeting)
          <div class="table-title">{{ $nextMeeting['title'] }}</div>
          <div class="table-time">{{ $nextMeeting['time'] }}</div>
        @else
          <div class="table-title">No upcoming meeting</div>
          <div class="table-time">Connect Google Calendar to sync</div>
        @endif
      </div>
    </div>
  </div>
  <div class="seat-sidepanel" id="seat-panel">
    <div class="panel-inner">
      <div class="panel-placeholder muted">Hover a teammate to see details.</div>
      <div class="panel-body" id="seat-panel-body"></div>
    </div>
  </div>
</section>
@push('scripts')
<script>
(function(){
  const seats = document.querySelectorAll('.seat-card[data-detail]');
  const panel = document.getElementById('seat-panel');
  const panelBody = document.getElementById('seat-panel-body');
  const board = document.querySelector('.vo-board');

  function closePanel(){
    if(panel) panel.classList.remove('open');
  }
  function openPanel(detail){
    if(!panel || !panelBody || !detail) return;
    panelBody.innerHTML = detail.innerHTML;
    panel.classList.add('open');
  }

  seats.forEach(seat => {
    const detailId = seat.dataset.detail;
    const detail = document.getElementById(detailId);

    seat.addEventListener('mouseenter', () => {
      if(window.innerWidth <= 900) return;
      closeMobileDetails();
      openPanel(detail);
    });

    seat.addEventListener('click', () => {
      if(window.innerWidth > 900) return;
      const drawer = seat.nextElementSibling;
      if(!drawer || !drawer.classList.contains('seat-detail')) return;
      closeMobileDetails();
      drawer.classList.add('open');
    });
  });

  function closeMobileDetails(){
    document.querySelectorAll('.seat-detail.open').forEach(el => el.classList.remove('open'));
  }

  if(board){
    board.addEventListener('mouseleave', () => {
      if(window.innerWidth > 900) closePanel();
    });
  }
})();
</script>
@endpush
@endsection
