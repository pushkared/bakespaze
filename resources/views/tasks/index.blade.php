@extends('layouts.app')

@section('title', 'Tasks')

@section('content')
<section class="tasks-page">
  <div class="vo-pattern"></div>
  <div class="tasks-shell">
    <header class="tasks-head">
      <div>
        <div class="eyebrow">Tasks</div>
        @php
          $currentWs = collect($workspaces ?? [])->firstWhere('id', $filters['workspace_id'] ?? null);
        @endphp
        <h1>{{ $currentWs->name ?? 'All Workspace Tasks' }}</h1>
      </div>
      <button class="task-add-btn" type="button" id="open-task-modal" aria-label="Create task"></button>
    </header>

    <form class="task-filters" method="GET" action="{{ route('tasks.index') }}">
      <input type="search" name="q" placeholder="Search title" value="{{ $filters['q'] ?? '' }}">
      <select name="workspace_id" @if(($filters['workspace_id'] ?? '') === '') data-placeholder="1" @endif>
        <option value="" disabled @selected(($filters['workspace_id'] ?? '') === '')>Workspace</option>
        @foreach($workspaces as $ws)
          <option value="{{ $ws->id }}" @selected(($filters['workspace_id'] ?? '') == $ws->id)>{{ $ws->name }}</option>
        @endforeach
      </select>
      <select name="status" @if(($filters['status'] ?? '') === '') data-placeholder="1" @endif>
        <option value="" disabled @selected(($filters['status'] ?? '') === '')>Status</option>
        <option value="ongoing" @selected(($filters['status'] ?? '') === 'ongoing')>Ongoing</option>
        <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Completed</option>
      </select>
      @if(!empty($isAdmin))
        <select name="assignee_id" @if(($filters['assignee_id'] ?? '') === '') data-placeholder="1" @endif>
          <option value="" disabled @selected(($filters['assignee_id'] ?? '') === '')>Assigned to</option>
          @foreach($members as $member)
            <option value="{{ $member->id }}" @selected(($filters['assignee_id'] ?? '') == $member->id)>{{ $member->name }}</option>
          @endforeach
        </select>
      @endif
      <div class="date-field">
        <input type="date" name="due_from" value="{{ $filters['due_from'] ?? '' }}" placeholder="Start date" title="Start date">
        <span class="date-placeholder">Start date</span>
      </div>
      <div class="date-field">
        <input type="date" name="due_to" value="{{ $filters['due_to'] ?? '' }}" placeholder="End date" title="End date">
        <span class="date-placeholder">End date</span>
      </div>
    </form>

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
        @php
          $isAssignee = $task->assignees->contains(auth()->id());
          $memberRecord = $task->workspace->memberships->firstWhere('user_id', auth()->id());
          $isWorkspaceAdmin = $memberRecord && $memberRecord->role === 'admin';
          $isAppAdmin = in_array(auth()->user()->role, ['admin', 'super_admin'], true);
          $canDeleteTask = $isWorkspaceAdmin || $isAppAdmin;
        @endphp
        <div class="task-row task-toggle" data-task="{{ $task->id }}">
          <div>{{ $task->title }}</div>
          <div class="line-clamp">{!! Str::limit(strip_tags($task->description), 90) !!}</div>
          <div>{{ $task->due_date ? $task->due_date->format('d M, Y') : '—' }}</div>
          <div>
            @if($task->status === 'completed')
              <span class="status done">Completed</span>
            @elseif($task->status === 'pending')
              <span class="status pending">Pending</span>
            @else
              <span class="status open">Open</span>
            @endif
          </div>
          <div>
            @if($task->assignees->first())
              @php
                $assignee = $task->assignees->first();
                $assigneeAvatar = $assignee->avatar_url ?? null;
                if ($assigneeAvatar && \Illuminate\Support\Str::startsWith($assigneeAvatar, ['http://', 'https://']) === false) {
                  $assigneeAvatar = \Illuminate\Support\Facades\Storage::url($assigneeAvatar);
                }
              @endphp
              <div class="assignee-chip">
                <img src="{{ $assigneeAvatar ?: asset('images/user-icon.svg') }}" alt="{{ $assignee->name }}">
                <span>{{ $assignee->name }}</span>
              </div>
            @else
              <span class="muted">Unassigned</span>
            @endif
          </div>
          <div class="row-actions">
            <button
              type="button"
              class="pill-btn small task-edit"
              data-action="{{ route('tasks.update', $task) }}"
              data-title="{{ $task->title }}"
              data-desc="{{ e($task->description) }}"
              data-due="{{ $task->due_date ? $task->due_date->format('Y-m-d') : '' }}"
              data-assignee="{{ optional($task->assignees->first())->id }}"
            >Edit</button>
            @if($task->status !== 'completed')
            @if($isAssignee)
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
            @endif
            @if($canDeleteTask)
              <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Delete task?')">
                @csrf
                @method('DELETE')
                <button class="pill-btn small ghost" type="submit">Delete</button>
              </form>
            @endif
          </div>
        </div>
        <div class="task-detail" id="detail-{{ $task->id }}">
          <div class="detail-tabs" data-task="{{ $task->id }}">
            <button class="detail-tab active" type="button" data-target="overview-{{ $task->id }}">Overview</button>
            <button class="detail-tab" type="button" data-target="comments-{{ $task->id }}">Comments</button>
            <button class="detail-tab" type="button" data-target="activity-{{ $task->id }}">Activity</button>
          </div>
          <div class="detail-panels">
            <div class="detail-panel active" id="overview-{{ $task->id }}">
              @if($task->status === 'pending' && $isAssignee)
                <form method="POST" action="{{ route('tasks.accept', $task) }}" class="accept-form">
                  @csrf
                  <button type="submit" class="pill-btn solid">Accept Task</button>
                </form>
              @endif
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
            </div>
            <div class="detail-panel" id="comments-{{ $task->id }}">
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
            <div class="detail-panel" id="activity-{{ $task->id }}">
              <div class="detail-block">
                <h4>Activity</h4>
                <div class="activity-list">
                  @forelse($task->activities as $activity)
                    @php
                      $payload = $activity->payload ?? [];
                      $actorName = $activity->actor?->name ?? 'System';
                      $from = $payload['from'] ?? null;
                      $to = $payload['to'] ?? null;
                      $fromName = $payload['from_name'] ?? null;
                      $toName = $payload['to_name'] ?? null;
                      $due = $payload['due_date'] ?? null;
                      $formatDate = function ($value) {
                          return $value ? \Carbon\Carbon::parse($value)->format('d M') : 'No due';
                      };
                    @endphp
                    <div class="activity-row">
                      <div class="activity-meta">{{ $activity->created_at->diffForHumans() }} · {{ $actorName }}</div>
                      <div class="activity-text">
                        @if($activity->type === 'assigned')
                          Assigned to {{ $toName ?? 'user' }}.
                        @elseif($activity->type === 'reassigned')
                          Reassigned from {{ $fromName ?? 'user' }} to {{ $toName ?? 'user' }}.
                        @elseif($activity->type === 'unassigned')
                          Unassigned {{ $fromName ?? 'user' }}.
                        @elseif($activity->type === 'due_date_changed')
                          Due date changed from {{ $formatDate($from) }} to {{ $formatDate($to) }}.
                        @elseif($activity->type === 'accepted')
                          Task accepted. Due date: {{ $formatDate($due) }}.
                        @else
                          Updated task.
                        @endif
                      </div>
                    </div>
                  @empty
                    <div class="muted">No activity yet.</div>
                  @endforelse
                </div>
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
              <input type="radio" name="assignee_id" value="{{ auth()->id() }}" checked>
              <span>Myself ({{ auth()->user()->email }})</span>
            </label>
            @foreach($members as $member)
              @if($member->id === auth()->id())
                @continue
              @endif
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
    const form = modal ? modal.querySelector('form.task-form') : null;
    const modalTitle = modal ? modal.querySelector('.modal-head h3') : null;
    const submitBtn = modal ? modal.querySelector('.form-actions .pill-btn.solid') : null;

    function openModal() {
      if (!modal) return;
      modal.classList.add('open');
    }
    window.openTaskModal = openModal;
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
    if (window.location.search.includes('open_modal=1')) {
      openModal();
    }
    const params = new URLSearchParams(window.location.search);
    const assignTo = params.get('assign_to');
    const applyAssignee = (assigneeId) => {
      if (!assigneeId) return;
      const radios = document.querySelectorAll('input[name="assignee_id"]');
      radios.forEach(r => r.checked = (r.value === assigneeId));
    };
    if (assignTo) {
      openModal();
      applyAssignee(assignTo);
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

    // detail tabs
    document.querySelectorAll('.detail-tabs').forEach((tabs) => {
      tabs.addEventListener('click', (e) => {
        const btn = e.target.closest('.detail-tab');
        if (!btn) return;
        const targetId = btn.dataset.target;
        const container = tabs.closest('.task-detail');
        if (!container) return;
        container.querySelectorAll('.detail-tab').forEach(t => t.classList.remove('active'));
        container.querySelectorAll('.detail-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        const panel = container.querySelector('#' + targetId);
        if (panel) panel.classList.add('active');
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

    const dateFields = document.querySelectorAll('.task-filters .date-field');
    const syncDateField = (field) => {
      const input = field.querySelector('input[type="date"]');
      if (!input) return;
      field.classList.toggle('has-value', Boolean(input.value));
    };
    dateFields.forEach((field) => {
      syncDateField(field);
      const input = field.querySelector('input[type="date"]');
      if (!input) return;
      input.addEventListener('change', () => syncDateField(field));
      input.addEventListener('input', () => syncDateField(field));
    });

    const filterForm = document.querySelector('.task-filters');
    if (filterForm) {
      const searchInput = filterForm.querySelector('input[name="q"]');
      let searchTimer = null;
      if (searchInput) {
        searchInput.addEventListener('input', () => {
          if (searchTimer) clearTimeout(searchTimer);
          searchTimer = setTimeout(() => filterForm.submit(), 300);
        });
      }
      filterForm.querySelectorAll('select, input[type="date"]').forEach((input) => {
        input.addEventListener('change', () => filterForm.submit());
      });
    }

    // Edit task: prefill modal and switch action
    const taskEditButtons = document.querySelectorAll('.task-edit');
    taskEditButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        if (!form) return;
        form.action = btn.dataset.action;
        if (modalTitle) modalTitle.textContent = 'Edit Task';
        if (submitBtn) submitBtn.textContent = 'Update Task';
        const titleInput = form.querySelector('input[name=\"title\"]');
        const dueInput = form.querySelector('input[name=\"due_date\"]');
        const radios = form.querySelectorAll('input[name=\"assignee_id\"]');
        if (titleInput) titleInput.value = btn.dataset.title || '';
        if (editor) {
          const rawDesc = btn.dataset.desc || '';
          const decoder = document.createElement('textarea');
          decoder.innerHTML = rawDesc;
          editor.innerHTML = decoder.value;
        }
        if (hidden) hidden.value = editor ? editor.innerHTML : '';
        if (dueInput) dueInput.value = btn.dataset.due || '';
        const targetAssignee = btn.dataset.assignee || '';
        radios.forEach(r => r.checked = (r.value === targetAssignee));
        openModal();
      });
    });

    // Reset to create mode when opening fresh
    if (openBtn) openBtn.addEventListener('click', () => {
      if (!form) return;
      form.action = '{{ route('tasks.store') }}';
      if (modalTitle) modalTitle.textContent = 'New Task';
      if (submitBtn) submitBtn.textContent = 'Create Task';
      const titleInput = form.querySelector('input[name=\"title\"]');
      const dueInput = form.querySelector('input[name=\"due_date\"]');
      const radios = form.querySelectorAll('input[name=\"assignee_id\"]');
      if (titleInput) titleInput.value = '';
      if (editor) editor.innerHTML = '';
      if (hidden) hidden.value = '';
      if (dueInput) dueInput.value = '';
      radios.forEach((r, idx) => r.checked = idx === 0);
      if (assignTo) applyAssignee(assignTo);
    }, { once: false });
  })();
</script>
@endpush
@endsection
