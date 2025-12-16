<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Workspace;
use App\Models\Membership;
use App\Models\User;
use App\Models\TaskComment;
use App\Models\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    protected function currentWorkspace(Request $request): Workspace
    {
        $user = $request->user();
        $workspaceId = session('current_workspace_id');

        $workspace = Workspace::whereHas('memberships', fn($q) => $q->where('user_id', $user->id))
            ->when($workspaceId, fn($q) => $q->where('id', $workspaceId))
            ->first();

        if (!$workspace) {
            $workspace = Workspace::whereHas('memberships', fn($q) => $q->where('user_id', $user->id))
                ->orderBy('name')->firstOrFail();
        }

        return $workspace;
    }

    protected function ensureTaskAccess(Task $task, Request $request): void
    {
        $user = $request->user();
        abort_unless(
            Membership::where('workspace_id', $task->workspace_id)->where('user_id', $user->id)->exists(),
            403
        );
    }

    public function index(Request $request)
    {
        $workspace = $this->currentWorkspace($request);

        $tasks = Task::with(['assignees','creator','comments.user','attachments'])
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->get();

        $members = User::whereHas('memberships', fn($q) => $q->where('workspace_id', $workspace->id))
            ->orderBy('name')
            ->get(['id','name','email']);

        return view('tasks.index', compact('tasks','workspace','members'));
    }

    public function store(Request $request)
    {
        $workspace = $this->currentWorkspace($request);

        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'due_date' => ['nullable','date'],
            'assignees' => ['array'],
            'assignees.*' => ['exists:users,id'],
            'attachments.*' => ['file','max:5120'],
        ]);

        $task = Task::create([
            'workspace_id' => $workspace->id,
            'creator_id' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => 'open',
        ]);

        if (!empty($data['assignees'])) {
            $task->assignees()->sync($data['assignees']);
        }

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('tasks', 'public');
                TaskAttachment::create([
                    'task_id' => $task->id,
                    'user_id' => $request->user()->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        return back()->with('status', 'Task created.');
    }

    public function update(Request $request, Task $task)
    {
        $this->ensureTaskAccess($task, $request);

        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'due_date' => ['nullable','date'],
            'status' => ['nullable','string'],
            'assignees' => ['array'],
            'assignees.*' => ['exists:users,id'],
        ]);

        $task->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'] ?? $task->status,
        ]);

        if (isset($data['assignees'])) {
            $task->assignees()->sync($data['assignees']);
        }

        return back()->with('status', 'Task updated.');
    }

    public function destroy(Request $request, Task $task)
    {
        $this->ensureTaskAccess($task, $request);
        $task->delete();
        return back()->with('status', 'Task deleted.');
    }

    public function comment(Request $request, Task $task)
    {
        $this->ensureTaskAccess($task, $request);

        $data = $request->validate([
            'body' => ['required','string'],
        ]);

        TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return back()->with('status', 'Comment added.');
    }

    public function attach(Request $request, Task $task)
    {
        $this->ensureTaskAccess($task, $request);

        $data = $request->validate([
            'attachments.*' => ['file','max:5120'],
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('tasks', 'public');
                TaskAttachment::create([
                    'task_id' => $task->id,
                    'user_id' => $request->user()->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        return back()->with('status', 'Attachment uploaded.');
    }
}
