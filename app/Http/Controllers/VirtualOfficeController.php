<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\Membership;
use Illuminate\Http\Request;

class VirtualOfficeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $workspaceId = session('current_workspace_id');

        $workspace = Workspace::with(['memberships.user' => function ($q) {
            $q->select('id','name','role','email');
        }])
        ->whereHas('memberships', fn($q) => $q->where('user_id', $user->id))
        ->when($workspaceId, fn($q) => $q->where('id', $workspaceId))
        ->first();

        // Fallback to first assigned workspace
        if (!$workspace) {
            $workspace = Workspace::with(['memberships.user' => function ($q) {
                $q->select('id','name','role','email');
            }])->whereHas('memberships', fn($q) => $q->where('user_id', $user->id))
            ->orderBy('name')->first();
        }

        abort_unless($workspace, 403);

        $members = $workspace->memberships->take(10);

        return view('virtual-office.index', [
            'workspace' => $workspace,
            'members' => $members,
        ]);
    }
}
