<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\Membership;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $user = $request->user();
        $term = trim($request->get('q', ''));
        if (strlen($term) < 2) {
            return response()->json(['users' => [], 'tasks' => []]);
        }

        $workspaceIds = Membership::where('user_id', $user->id)->pluck('workspace_id');

        $users = User::select('id', 'name', 'email', 'role')
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role ?? '',
            ]);

        $tasks = Task::select('tasks.id', 'tasks.title', 'tasks.due_date', 'tasks.status', 'tasks.workspace_id')
            ->with('workspace:id,name')
            ->whereIn('tasks.workspace_id', $workspaceIds)
            ->where(function ($q) use ($term) {
                $q->where('tasks.title', 'like', "%{$term}%")
                  ->orWhere('tasks.description', 'like', "%{$term}%");
            })
            ->orderByRaw('ISNULL(tasks.due_date), tasks.due_date asc')
            ->limit(6)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'due_date' => optional($t->due_date)->format('d M'),
                'status' => $t->status ?? 'open',
                'workspace' => $t->workspace?->name ?? 'Workspace',
            ]);

        return response()->json([
            'users' => $users,
            'tasks' => $tasks,
        ]);
    }
}
