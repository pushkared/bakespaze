@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<section class="dash-stage">
  <div class="vo-pattern"></div>

  <div class="dash-content">
    <div class="dash-header">
      <button class="create-task solid" onclick="window.location='{{ route('tasks.index') }}'">+ Create Task</button>
      <div class="dash-greeting">
        <div class="greet-line">Hey {{ auth()->user()->name ?? 'there' }},</div>
        <div class="greet-line">{{ $greeting ?? 'Welcome' }}!</div>
        {{-- <div class="muted">{{ $todayDate ?? '' }} - {{ $currentTime ?? '' }}</div> --}}
      </div>
    </div>

    <div class="dash-tasks">
      <h2>Tasks For The Day!</h2>
      <div class="dash-task-list">
        @forelse(($tasks ?? collect()) as $task)
          <a class="dash-task-item" href="{{ route('tasks.index') }}">
            <span class="dash-task-box" aria-hidden="true"></span>
            <span class="dash-task-pill">{{ Str::limit($task->title, 48) }}</span>
          </a>
        @empty
          <div class="muted">No tasks yet. Create one to get started.</div>
        @endforelse
      </div>
      <div class="task-actions">
        <a class="pill-btn" href="{{ route('tasks.index') }}">View All</a>
      </div>
    </div>

    <div class="punch-actions">
      @if(empty($punchState['punched_in']))
        @if(!empty($punchState['can_punch_in']))
          <form method="POST" action="{{ route('attendance.punchin') }}">
            @csrf
            <button class="pill-btn">Punch In</button>
          </form>
        @else
          <div class="muted">Punch in available 9:00 AM – 11:00 AM IST.</div>
        @endif
      @else
        <div class="muted">Punched in at {{ $punchState['punched_at'] }}</div>
        <div class="punch-actions-row">
          <form method="POST" action="{{ route('attendance.punchout') }}">
            @csrf
            <button class="pill-btn">Punch Out</button>
          </form>
          @if(empty($punchState['lunch_started']))
            <form method="POST" action="{{ route('attendance.lunchstart') }}">
              @csrf
              <button class="pill-btn ghost">Lunch Start</button>
            </form>
          @elseif(empty($punchState['lunch_ended']))
            <form method="POST" action="{{ route('attendance.lunchend') }}">
              @csrf
              <button class="pill-btn ghost">Lunch End</button>
            </form>
          @endif
        </div>
      @endif
    </div>
  </div>
</section>
@endsection
