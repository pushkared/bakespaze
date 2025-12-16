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

    <div class="task-table-wrap">
    <div class="task-table">
      <div class="task-row head">
        <div>Title</div>
        <div>Description</div>
        <div>Due Date</div>
        <div>Status</div>
        <div>Assignee</div>
        <div>Actions</div>
      </div>
      @forelse($tasks as $task)
        <div class="task-row task-toggle" data-task="{{ $task->id }}">
          <div>{{ $task->title }}</div>
          <div class="line-clamp">{!! Str::limit(strip_tags($task->description), 90) !!}</div>
          <div>{{ $task->due_date ? $task->due_date->format('d M, Y') : '—' }}</div>
          <div>
            @if($task->status === 'completed')
              <span class="status done">Completed</span>
            @else
              <span class="status open">Open</span>
            @endif
          </div>
          <div>
            @if($task->assignees->first())
              <span class="avatar-chip">{{ strtoupper(substr($task->assignees->first()->name,0,1)) }}</span>
            @else
              <span class="muted">Unassigned</span>
            @endif
          </div>
          <div class="row-actions">
            @if($task->status !== 'completed')
            <form method="POST" action="{{ route('tasks.update', $task) }}" onsubmit="return confirm('Mark complete?')">
              @csrf
              <input type="hidden" name="title" value="{{ $task->title }}">
              <input type="hidden" name="description" value="{{ $task->description }}">
              <input type="hidden" name="due_date" value="{{ $task->due_date }}">
              <input type="hidden" name="status" value="completed">
              <input type="hidden" name="assignee_id" value="{{ optional($task->assignees->first())->id }}">
              <button class="pill-btn small ghost" type="submit">Mark Complete</button>
            </form>
            @endif
            <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Delete task?')">
              @csrf
              @method('DELETE')
              <button class="pill-btn small ghost" type="submit">Delete</button>
            </form>
          </div>
        </div>
        <div class="task-detail" id="detail-{{ $task->id }}">
          <div class="detail-inner">
            <div class="detail-block">
              <h4>Description</h4>
              <div class="task-desc">{!! $task->description ?? '<span class="muted">No description</span>' !!}</div>
            </div>
            <div class="detail-block">
              <h4>Attachments</h4>
              <div class="task-attachments">
                @forelse($task->attachments as $file)
                  <a class="attachment-pill" href="{{ Storage::url($file->path) }}" target="_blank">{{ $file->original_name }}</a>
                @empty
                  <span class="muted">No attachments</span>
                @endforelse
              </div>
              <form method="POST" action="{{ route('tasks.attach', $task) }}" enctype="multipart/form-data" class="attach-form">
                @csrf
                <input type="file" name="attachments[]" multiple>
                <button type="submit" class="pill-btn small">Upload</button>
              </form>
            </div>
            <div class="detail-block">
              <h4>Comments</h4>
              <div class="task-comments">
                @forelse($task->comments as $comment)
                  <div class="comment-row">
                    <div class="avatar-chip">{{ strtoupper(substr($comment->user->name,0,1)) }}</div>
                    <div class="comment-body">
                      <div class="comment-meta">{{ $comment->user->name }} · {{ $comment->created_at->diffForHumans() }}</div>
                      <div class="comment-text">{{ $comment->body }}</div>
                    </div>
                  </div>
                @empty
                  <span class="muted">No comments yet.</span>
                @endforelse
                <form method="POST" action="{{ route('tasks.comment', $task) }}" class="comment-form">
                  @csrf
                  <input type="text" name="body" placeholder="Add a comment..." required>
                  <button type="submit" class="pill-btn small">Post</button>
                </form>
              </div>
            </div>
          </div>
        </div>
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
        <div class="wysiwyg-toolbar">
          <button type="button" data-cmd="bold"><b>B</b></button>
          <button type="button" data-cmd="italic"><i>I</i></button>
          <button type="button" data-cmd="underline"><u>U</u></button>
          <button type="button" data-cmd="insertUnorderedList">• List</button>
        </div>
        <div id="task-desc-editor" class="wysiwyg" contenteditable="true" aria-label="Task description"></div>
        <textarea name="description" id="task-desc-input" hidden></textarea>
      </label>
      <label>
        <span>Due date</span>
        <input type="date" name="due_date" class="date-picker">
      </label>
      <label>
        <span>Assign to</span>
        <div class="assignee-select multi-select">
          <div class="multi-search">
            <input type="search" id="task-assignee-search" placeholder="Search member">
            <button type="button" id="task-assignee-clear">Clear</button>
          </div>
          <div class="multi-options" id="task-assignee-options">
            <label class="option-row">
              <input type="radio" name="assignee_id" value="" checked>
              <span>Unassigned</span>
            </label>
            @foreach($members as $member)
              <label class="option-row">
                <input type="radio" name="assignee_id" value="{{ $member->id }}">
                <span>{{ $member->name }} ({{ $member->email }})</span>
              </label>
            @endforeach
          </div>
        </div>
        <small class="muted">Search and pick one member.</small>
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
    const toolbar = document.querySelector('.wysiwyg-toolbar');
    const searchInput = document.getElementById('assignee-search');
    const select = document.getElementById('assignee-select');

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

    // basic toolbar actions
    if (toolbar && editor) {
      toolbar.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-cmd]');
        if (!btn) return;
        const cmd = btn.dataset.cmd;
        document.execCommand(cmd, false, null);
        editor.focus();
      });
    }

    // filter assignee select
    if (searchInput && select) {
      searchInput.addEventListener('input', () => {
        const term = searchInput.value.toLowerCase();
        Array.from(select.options).forEach(opt => {
          if (opt.value === '') return; // keep unassigned
          const match = opt.text.toLowerCase().includes(term);
          opt.hidden = !match;
        });
      });
    }

    // detail toggles
    document.querySelectorAll('.task-toggle').forEach(row => {
      row.addEventListener('click', () => {
        const id = row.dataset.task;
        const detail = document.getElementById('detail-' + id);
        if (detail) detail.classList.toggle('open');
      });
    });

    // Task assignee search (radio)
    const tSearch = document.getElementById('task-assignee-search');
    const tOptions = document.querySelectorAll('#task-assignee-options .option-row');
    const tClear = document.getElementById('task-assignee-clear');
    if (tSearch) {
      tSearch.addEventListener('input', () => {
        const term = tSearch.value.toLowerCase();
        tOptions.forEach(opt => {
          const text = opt.textContent.toLowerCase();
          opt.style.display = text.includes(term) ? '' : 'none';
        });
      });
    }
    if (tClear) {
      tClear.addEventListener('click', () => {
        const radios = document.querySelectorAll('#task-assignee-options input[type="radio"]');
        radios.forEach((r, idx) => r.checked = idx === 0); // unassigned
        if (tSearch) tSearch.value = '';
        tOptions.forEach(opt => opt.style.display = '');
      });
    }
  })();
</script>
@endpush
@endsection
