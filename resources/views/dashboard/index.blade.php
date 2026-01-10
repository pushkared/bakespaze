@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<section class="dash-stage">
  <div class="vo-pattern"></div>

  <div class="dash-content">
    <div class="task-count-strip">
        <div class="task-count-card">
          <div class="task-count-label">Total Tasks</div>
          <div class="task-count-value">{{ $taskCounts['total'] ?? 0 }}</div>
        </div>
        <div class="task-count-card">
          <div class="task-count-label">Completed</div>
          <div class="task-count-value">{{ $taskCounts['completed'] ?? 0 }}</div>
        </div>
        <div class="task-count-card">
          <div class="task-count-label">Open</div>
          <div class="task-count-value">{{ $taskCounts['open'] ?? 0 }}</div>
        </div>
        <div class="task-count-card">
          <div class="task-count-label">Overdue</div>
          <div class="task-count-value">{{ $taskCounts['overdue'] ?? 0 }}</div>
        </div>
    </div>

    <div class="dash-header">
      <div class="dash-greeting">
        <div class="greet-line">Hey {{ auth()->user()->name ?? 'there' }},</div>
        <div class="greet-line">{{ $greeting ?? 'Welcome' }}!</div>
        {{-- <div class="muted">{{ $todayDate ?? '' }} - {{ $currentTime ?? '' }}</div> --}}
      </div>
      <button class="create-task solid" onclick="window.location='{{ route('tasks.index') }}?open_modal=1'">+ Create Task</button>
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
      <div class="muted small">Punch out available after 7:00 PM.</div>
    @endif
  </div>
</div>
  </div>
</section>
@endsection
