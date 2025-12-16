@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<section class="dash-stage">
  <div class="vo-pattern"></div>

  <div class="dash-content">
    <div class="dash-header">
      <div class="dash-greeting">
        <div class="greet-line">Hey {{ auth()->user()->name ?? 'there' }},</div>
        <div class="greet-line">Good Morning!</div>
      </div>
      <button class="create-task solid" onclick="window.location='{{ route('tasks.index') }}'">+ Create Task</button>
    </div>

    <div class="dash-tasks">
      <h2>Tasks For The Day!</h2>
      <div class="dash-task-grid">
        @forelse(($tasks ?? collect()) as $task)
          <article class="dash-task-card" onclick="window.location='{{ route('tasks.index') }}'">
            <div class="dash-task-top">
              <div class="dash-task-title">{{ $task->title }}</div>
              <div class="dash-task-status {{ $task->status === 'completed' ? 'done' : 'open' }}">
                {{ $task->status === 'completed' ? 'Completed' : 'Open' }}
              </div>
            </div>
            <div class="dash-task-desc">{{ Str::limit(strip_tags($task->description), 90) ?: 'No description yet.' }}</div>
            <div class="dash-task-meta">
              <div class="meta-item">
                <span class="label">Due</span>
                <span class="value">{{ $task->due_date ? $task->due_date->format('d M') : 'No due date' }}</span>
              </div>
              <div class="meta-item">
                <span class="label">Assignee</span>
                @if($task->assignees->first())
                  <span class="value with-avatar">
                    <span class="avatar-chip small">{{ strtoupper(substr($task->assignees->first()->name,0,1)) }}</span>
                    {{ $task->assignees->first()->name }}
                  </span>
                @else
                  <span class="value muted">Unassigned</span>
                @endif
              </div>
            </div>
          </article>
        @empty
          <div class="muted">No tasks yet. Create one to get started.</div>
        @endforelse
      </div>
      <div class="task-actions">
        <a class="pill-btn" href="{{ route('tasks.index') }}">View All</a>
      </div>
    </div>

    <div class="punch-actions">
      <button class="pill-btn">Punch In</button>
      <button class="pill-btn">Punch Out</button>
    </div>
  </div>
</section>
@endsection
