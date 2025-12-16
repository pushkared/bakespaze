@extends('layouts.app')

@section('title', 'Tasks')

@section('content')
<section class="tasks-page">
  <div class="vo-pattern"></div>
  <div class="tasks-shell">
    <header class="tasks-head">
      <div>
        <div class="eyebrow">Tasks</div>
        <h1>{{ $workspace->name }} Tasks</h1>
        <p class="muted">Create, assign, comment, and attach files just like Asana — styled for our workspace.</p>
      </div>
      <button class="pill-btn solid" id="open-task-modal">+ New Task</button>
    </header>

    <div class="tasks-columns">
      <div class="task-list">
        @forelse($tasks as $task)
          <article class="task-card">
            <div class="task-top">
              <div>
                <div class="task-title">{{ $task->title }}</div>
                @if($task->due_date)
                  <div class="task-due">Due {{ $task->due_date->format('d M') }}</div>
                @endif
              </div>
              <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Delete task?')">
                @csrf
                @method('DELETE')
                <button class="pill-btn small ghost" type="submit">Delete</button>
              </form>
            </div>

            <div class="task-body">
              @if($task->description)
                <div class="task-desc" aria-label="Description">{!! $task->description !!}</div>
              @endif
              <div class="task-meta">
                <div class="assignees">
                  @forelse($task->assignees as $assignee)
                    <span class="avatar-chip">{{ strtoupper(substr($assignee->name,0,1)) }}</span>
                  @empty
                    <span class="muted">Unassigned</span>
                  @endforelse
                </div>
              </div>

              @if($task->attachments->count())
                <div class="task-attachments">
                  @foreach($task->attachments as $file)
                    <a class="attachment-pill" href="{{ Storage::url($file->path) }}" target="_blank">{{ $file->original_name }}</a>
                  @endforeach
                </div>
              @endif

              <div class="task-comments">
                @foreach($task->comments as $comment)
                  <div class="comment-row">
                    <div class="avatar-chip">{{ strtoupper(substr($comment->user->name,0,1)) }}</div>
                    <div class="comment-body">
                      <div class="comment-meta">{{ $comment->user->name }} · {{ $comment->created_at->diffForHumans() }}</div>
                      <div class="comment-text">{{ $comment->body }}</div>
                    </div>
                  </div>
                @endforeach
                <form method="POST" action="{{ route('tasks.comment', $task) }}" class="comment-form">
                  @csrf
                  <input type="text" name="body" placeholder="Add a comment..." required>
                  <button type="submit" class="pill-btn small">Post</button>
                </form>
              </div>
            </div>
          </article>
        @empty
          <div class="empty-state">
            <div class="eyebrow">No tasks yet</div>
            <p class="muted">Create your first task to get started.</p>
          </div>
        @endforelse
      </div>
    </div>
  </div>
</section>

<div class="modal-backdrop" id="task-modal">
  <div class="modal-card">
    <header class="modal-head">
      <h3>New Task</h3>
      <button type="button" class="close-modal" aria-label="Close">×</button>
    </header>
    <form class="task-form" method="POST" action="{{ route('tasks.store') }}" enctype="multipart/form-data" onsubmit="syncEditor()">
      @csrf
      <label>
        <span>Title</span>
        <input type="text" name="title" required maxlength="255">
      </label>
      <label>
        <span>Description</span>
        <div id="task-desc-editor" class="wysiwyg" contenteditable="true" aria-label="Task description"></div>
        <textarea name="description" id="task-desc-input" hidden></textarea>
      </label>
      <label>
        <span>Due date</span>
        <input type="date" name="due_date">
      </label>
      <label>
        <span>Assign to</span>
        <select name="assignees[]" multiple>
          @foreach($members as $member)
            <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</option>
          @endforeach
        </select>
        <small class="muted">Only workspace members are shown.</small>
      </label>
      <label>
        <span>Attachments</span>
        <input type="file" name="attachments[]" multiple>
      </label>
      <div class="form-actions">
        <button type="button" class="pill-btn ghost close-modal">Cancel</button>
        <button type="submit" class="pill-btn solid">Create Task</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
  (function(){
    const modal = document.getElementById('task-modal');
    const openBtn = document.getElementById('open-task-modal');
    const closers = modal ? modal.querySelectorAll('.close-modal') : [];
    const editor = document.getElementById('task-desc-editor');
    const hidden = document.getElementById('task-desc-input');

    function openModal() {
      if (!modal) return;
      modal.classList.add('open');
    }
    function closeModal() {
      if (!modal) return;
      modal.classList.remove('open');
    }
    window.syncEditor = function() {
      if (hidden && editor) {
        hidden.value = editor.innerHTML;
      }
      return true;
    };
    if (openBtn) openBtn.addEventListener('click', openModal);
    closers.forEach(btn => btn.addEventListener('click', closeModal));
    if (modal) {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
      });
    }
  })();
</script>
@endpush
@endsection
