<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected function currentWorkspace(Request $request): ?Workspace
    {
        $user = $request->user();
        $workspaceId = session('current_workspace_id');

        return Workspace::whereHas('memberships', fn($q) => $q->where('user_id', $user->id))
            ->when($workspaceId, fn($q) => $q->where('id', $workspaceId))
            ->orderBy('name')
            ->first();
    }

    public function index(Request $request)
    {
        $workspace = $this->currentWorkspace($request);

        $tasks = Task::with('assignees')
            ->when($workspace, fn($q) => $q->where('workspace_id', $workspace->id))
            ->orderByRaw('ISNULL(due_date), due_date asc')
            ->limit(10)
            ->get();

        return view('dashboard.index', [
            'workspace' => $workspace,
            'tasks' => $tasks,
        ]);
    }
}
