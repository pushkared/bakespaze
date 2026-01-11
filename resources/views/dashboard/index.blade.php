@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="dash-count-top">
  <div class="task-count-strip">
    <a class="task-count-item" href="{{ route('tasks.index', array_filter(['status' => 'open', 'workspace_id' => $workspace?->id])) }}">
      <span class="task-count-text">Open</span>
      <span class="task-count-badge open">{{ $taskCounts['open'] ?? 0 }}</span>
    </a>
    <a class="task-count-item" href="{{ route('tasks.index', array_filter(['status' => 'ongoing', 'workspace_id' => $workspace?->id])) }}">
      <span class="task-count-text">Ongoing</span>
      <span class="task-count-badge ongoing">{{ $taskCounts['ongoing'] ?? 0 }}</span>
    </a>
    <a class="task-count-item" href="{{ route('tasks.index', array_filter(['status' => 'completed', 'workspace_id' => $workspace?->id])) }}">
      <span class="task-count-text">Completed</span>
      <span class="task-count-badge completed">{{ $taskCounts['completed'] ?? 0 }}</span>
    </a>
    <a class="task-count-item" href="{{ route('tasks.index', array_filter(['status' => 'overdue', 'workspace_id' => $workspace?->id])) }}">
      <span class="task-count-text">Overdue</span>
      <span class="task-count-badge overdue">{{ $taskCounts['overdue'] ?? 0 }}</span>
    </a>
  </div>
</div>

<section class="dash-stage">
  <div class="vo-pattern"></div>

  <div class="dash-content">

    <div class="dash-header">
      <div class="dash-create-wrap">
        <button class="create-task plain" id="dash-create-toggle" aria-label="Quick create">+</button>
        <div class="dash-create-panel" id="dash-create-panel">
          <a class="dash-create-item" href="{{ route('tasks.index') }}?open_modal=1">New Task</a>
          <a class="dash-create-item" href="{{ route('chat.index') }}">New Chat</a>
          <a class="dash-create-item" href="{{ route('calendar.index') }}">New Meet</a>
        </div>
      </div>
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
          @php
            $assigneeId = optional($task->assignees->first())->id;
            $isAssignee = $task->assignees->contains(auth()->id());
          @endphp
          <div class="dash-task-item {{ $task->status === 'completed' ? 'is-done' : '' }}"
               data-task-id="{{ $task->id }}"
               data-update-url="{{ route('tasks.update', $task) }}"
               data-task-title="{{ e($task->title) }}"
               data-task-desc="{{ e($task->description ?? '') }}"
               data-task-due="{{ $task->due_date }}"
               data-task-assignee="{{ $assigneeId }}"
               data-task-status="{{ $task->status }}">
            <button class="dash-task-box" type="button" aria-label="Mark task complete"
              {{ (!$isAssignee || $task->status === 'completed' || !$task->accepted_at) ? 'disabled' : '' }}
              @if(!$task->accepted_at && $isAssignee)
                data-disabled-message="Please accept the task first."
              @endif
            ></button>
            <a class="dash-task-pill" href="{{ route('tasks.index') }}">{{ Str::limit($task->title, 48) }}</a>
          </div>
        @empty
          <div class="muted">No tasks yet. Create one to get started.</div>
        @endforelse
      </div>
      <div class="task-actions">
        <a class="pill-btn" href="{{ route('tasks.index') }}">View All</a>
      </div>
    </div>

    <div class="punch-actions">
  @php
    $canPunchIn = !empty($punchState['can_punch_in']);
    $punchedIn = !empty($punchState['punched_in']);
    $disablePunchIn = !$canPunchIn || $punchedIn;
    $disablePunchOut = !$punchedIn || empty($punchState['can_punch_out']);
  @endphp
      <div class="punch-actions-row">
        <form method="POST" action="{{ route('attendance.punchin') }}">
          @csrf
          <button class="pill-btn {{ $disablePunchIn ? 'is-disabled' : '' }}" {{ $disablePunchIn ? 'disabled' : '' }}>
            {{ $punchedIn && $punchState['punched_at'] ? $punchState['punched_at'] : 'Punch In' }}
          </button>
        </form>
        <form method="POST" action="{{ route('attendance.punchout') }}">
          @csrf
      <button class="pill-btn {{ $disablePunchOut ? 'is-disabled' : '' }}" {{ $disablePunchOut ? 'disabled' : '' }}>Punch Out</button>
    </form>
    @if($punchedIn && $disablePunchOut)
      <div class="muted small">Punch out available after {{ $punchState['punch_out_after_hours'] ?? 8 }} hours of punching in.</div>
    @endif
  </div>
</div>
  </div>
</section>
@push('scripts')
<script>
  (function(){
    const toggle = document.getElementById('dash-create-toggle');
    const panel = document.getElementById('dash-create-panel');
    const wrap = document.querySelector('.dash-create-wrap');
    if (!toggle || !panel || !wrap) return;
    const closePanel = () => panel.classList.remove('open');
    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      panel.classList.toggle('open');
    });
    document.addEventListener('click', (e) => {
      if (!wrap.contains(e.target)) closePanel();
    });
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closePanel();
    });
  })();
</script>
@endpush
@endsection
