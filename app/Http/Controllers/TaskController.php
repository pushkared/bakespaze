<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Workspace;
use App\Models\Membership;
use App\Models\User;
use App\Models\TaskComment;
use App\Models\TaskAttachment;
use App\Models\TaskActivity;
use App\Notifications\TaskAcceptedNotification;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCompletedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    protected function currentWorkspace(Request $request): Workspace
    {
        $user = $request->user();
        $workspaceId = session('current_workspace_id');

        $workspace = Workspace::whereHas('memberships', fn($q) => $q->where('user_id', $user->id)->where('status', 'accepted'))
            ->when($workspaceId, fn($q) => $q->where('id', $workspaceId))
            ->first();

        if (!$workspace) {
            $workspace = Workspace::whereHas('memberships', fn($q) => $q->where('user_id', $user->id)->where('status', 'accepted'))
                ->orderBy('name')->firstOrFail();
        }

        return $workspace;
    }

    protected function ensureTaskAccess(Task $task, Request $request): void
    {
        $user = $request->user();
        $isMember = Membership::where('workspace_id', $task->workspace_id)
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->exists();
        $isAppAdmin = in_array($user->role, ['admin', 'super_admin'], true);
        abort_unless($isMember || $isAppAdmin, 403);
    }

    protected function ensureTaskAdmin(Task $task, Request $request): void
    {
        $user = $request->user();
        $isAppAdmin = in_array($user->role, ['admin', 'super_admin'], true);
        $isWorkspaceAdmin = Membership::where('workspace_id', $task->workspace_id)
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->where('status', 'accepted')
            ->exists();
        abort_unless($isAppAdmin || $isWorkspaceAdmin, 403);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $allowedWorkspaceIds = Membership::where('user_id', $user->id)
            ->where('status', 'accepted')
            ->pluck('workspace_id');
        $allowedWorkspaceIds = $allowedWorkspaceIds->unique()->values()->all();
        $isAdmin = in_array($user->role, ['admin', 'super_admin'], true);

        $workspaceFilter = $request->input('workspace_id');
        $search = $request->input('q');
        $dueFrom = $request->input('due_from');
        $dueTo = $request->input('due_to');
        $statusFilter = $request->input('status');
        $assigneeFilter = $request->input('assignee_id');

        $today = now()->toDateString();
        $tasks = Task::with(['assignees','creator','comments.user','attachments','activities.actor','workspace.memberships'])
            ->whereIn('workspace_id', $allowedWorkspaceIds)
            ->where(function ($q) use ($user) {
                $q->where('creator_id', $user->id)
                  ->orWhereHas('assignees', fn($a) => $a->where('users.id', $user->id));
            })
            ->when($workspaceFilter, fn($q) => $q->where('workspace_id', $workspaceFilter))
            ->when($search, fn($q) => $q->where('title', 'like', '%'.$search.'%'))
            ->when($dueFrom, fn($q) => $q->whereDate('due_date', '>=', $dueFrom))
            ->when($dueTo, fn($q) => $q->whereDate('due_date', '<=', $dueTo))
            ->when($statusFilter === 'completed', fn($q) => $q->where('status', 'completed')
                ->where(function ($q2) use ($today) {
                    $q2->whereNull('due_date')
                       ->orWhereDate('due_date', '>=', $today);
                }))
            ->when($statusFilter === 'open', fn($q) => $q->where('status', 'open')
                ->where(function ($q2) use ($today) {
                    $q2->whereNull('due_date')
                       ->orWhereDate('due_date', '>=', $today);
                }))
            ->when($statusFilter === 'ongoing', fn($q) => $q->where('status', 'ongoing')
                ->where(function ($q2) use ($today) {
                    $q2->whereNull('due_date')
                       ->orWhereDate('due_date', '>=', $today);
                }))
            ->when($statusFilter === 'overdue', fn($q) => $q->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today))
            ->when($assigneeFilter, fn($q) => $q->whereHas('assignees', fn($a) => $a->where('users.id', $assigneeFilter)))
            ->orderByDesc('updated_at')
            ->get();

        $workspaces = Workspace::whereIn('id', $allowedWorkspaceIds)->orderByDesc('created_at')->get();

        $members = User::whereHas('memberships', fn($q) => $q->whereIn('workspace_id', $allowedWorkspaceIds)->where('status', 'accepted'))
            ->orderBy('name')
            ->get(['id','name','email']);

        return view('tasks.index', [
            'tasks' => $tasks,
            'workspaces' => $workspaces,
            'members' => $members,
            'filters' => [
                'workspace_id' => $workspaceFilter,
                'q' => $search,
                'due_from' => $dueFrom,
                'due_to' => $dueTo,
                'status' => $statusFilter,
                'assignee_id' => $assigneeFilter,
            ],
            'isAdmin' => $isAdmin,
        ]);
    }

    public function store(Request $request)
    {
        $workspace = $this->currentWorkspace($request);

        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'due_date' => ['nullable','date'],
            'assignee_id' => ['nullable','exists:users,id'],
            'attachments.*' => ['file','max:5120'],
        ]);

        $assigneeId = $data['assignee_id'] ?? null;
        $task = Task::create([
            'workspace_id' => $workspace->id,
            'creator_id' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => 'open',
        ]);

        if (!empty($assigneeId)) {
            $task->assignees()->sync([$assigneeId]);
            $assignee = User::find($assigneeId);
            if ($assignee && $assignee->notifications_enabled) {
                try {
                    $assignee->notify(new TaskAssignedNotification($task, $request->user()->name));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
            $this->logActivity($task, $request->user()->id, 'assigned', [
                'to_user_id' => $assigneeId,
                'to_name' => $assignee?->name,
            ]);
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

        $previousStatus = $task->status;
        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'due_date' => ['nullable','date'],
            'status' => ['nullable','string'],
            'assignee_id' => ['nullable','exists:users,id'],
        ]);
        if (array_key_exists('assignee_id', $data) && $data['assignee_id'] === '') {
            $data['assignee_id'] = null;
        }

        $currentAssigneeId = $task->assignees()->first()?->id;
        $targetAssigneeId = array_key_exists('assignee_id', $data) ? $data['assignee_id'] : $currentAssigneeId;
        if (
            ($data['status'] ?? null) === 'completed'
            && (int) $targetAssigneeId !== (int) $request->user()->id
        ) {
            abort(403);
        }
        if (!$task->accepted_at && ($data['status'] ?? null) === 'completed') {
            abort(403);
        }

        $newStatus = $data['status'] ?? $task->status;
        $previousAssigneeId = $task->assignees()->first()?->id;
        $previousAssigneeId = $previousAssigneeId ? (int) $previousAssigneeId : null;
        $previousAssignee = $previousAssigneeId ? User::find($previousAssigneeId) : null;
        $previousDueDate = $task->due_date ? $task->due_date->format('Y-m-d') : null;

        $task->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => $newStatus,
        ]);

        if ($previousStatus !== 'completed' && $newStatus === 'completed' && $previousAssigneeId) {
            $assignee = User::find($previousAssigneeId);
            if ($assignee && $assignee->notifications_enabled) {
                try {
                    $assignee->notify(new TaskCompletedNotification($task, $request->user()->name));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $newDueDate = $task->due_date ? $task->due_date->format('Y-m-d') : null;
        if ($previousDueDate !== $newDueDate) {
            $this->logActivity($task, $request->user()->id, 'due_date_changed', [
                'from' => $previousDueDate,
                'to' => $newDueDate,
            ]);
        }

        if (array_key_exists('assignee_id', $data)) {
            $incomingAssigneeId = $data['assignee_id'];
            $incomingAssigneeId = $incomingAssigneeId !== null ? (int) $incomingAssigneeId : null;
            if ($newStatus === 'completed' && empty($incomingAssigneeId)) {
                $incomingAssigneeId = $previousAssigneeId;
            }
            $task->assignees()->sync($incomingAssigneeId ? [$incomingAssigneeId] : []);
            if (!empty($incomingAssigneeId) && $incomingAssigneeId !== $previousAssigneeId) {
                $assignee = User::find($incomingAssigneeId);
                if ($assignee && $assignee->notifications_enabled) {
                    try {
                        $assignee->notify(new TaskAssignedNotification($task, $request->user()->name));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
                $task->update([
                    'status' => 'open',
                    'accepted_at' => null,
                    'accepted_by_user_id' => null,
                ]);
                $this->logActivity($task, $request->user()->id, $previousAssigneeId ? 'reassigned' : 'assigned', [
                    'from_user_id' => $previousAssigneeId,
                    'from_name' => $previousAssignee?->name,
                    'to_user_id' => $assignee?->id,
                    'to_name' => $assignee?->name,
                ]);
            } elseif (empty($incomingAssigneeId) && $previousAssigneeId && $newStatus !== 'completed') {
                $task->update([
                    'status' => 'open',
                    'accepted_at' => null,
                    'accepted_by_user_id' => null,
                ]);
                $this->logActivity($task, $request->user()->id, 'unassigned', [
                    'from_user_id' => $previousAssigneeId,
                    'from_name' => $previousAssignee?->name,
                ]);
            }
        }

        return back()->with('status', 'Task updated.');
    }

    public function accept(Request $request, Task $task)
    {
        $this->ensureTaskAccess($task, $request);
        abort_if($task->accepted_at, 403);
        $assigneeId = $task->assignees()->first()?->id;
        abort_unless($assigneeId && (int)$assigneeId === (int)$request->user()->id, 403);

        $task->update([
            'status' => 'ongoing',
            'accepted_at' => now(),
            'accepted_by_user_id' => $request->user()->id,
        ]);

        $this->logActivity($task, $request->user()->id, 'accepted', [
            'due_date' => $task->due_date ? $task->due_date->format('Y-m-d') : null,
        ]);

        $assignerId = $this->latestAssignerId($task);
        if ($assignerId && (int) $assignerId !== (int) $request->user()->id) {
            $assigner = User::find($assignerId);
            if ($assigner && $assigner->notifications_enabled) {
                try {
                    $assigner->notify(new TaskAcceptedNotification($task));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return back()->with('status', 'Task accepted.');
    }

    protected function logActivity(Task $task, ?int $actorId, string $type, array $payload = []): void
    {
        TaskActivity::create([
            'task_id' => $task->id,
            'actor_id' => $actorId,
            'type' => $type,
            'payload' => $payload,
        ]);
    }

    protected function latestAssignerId(Task $task): ?int
    {
        $activity = TaskActivity::where('task_id', $task->id)
            ->whereIn('type', ['assigned', 'reassigned'])
            ->latest()
            ->first();

        if ($activity && $activity->actor_id) {
            return (int) $activity->actor_id;
        }

        return $task->creator_id ? (int) $task->creator_id : null;
    }

    public function destroy(Request $request, Task $task)
    {
        $this->ensureTaskAccess($task, $request);
        $this->ensureTaskAdmin($task, $request);
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
