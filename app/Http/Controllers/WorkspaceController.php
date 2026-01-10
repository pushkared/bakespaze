<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\Membership;
use App\Models\User;
use App\Models\Organization;
use App\Notifications\WorkspaceInviteNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkspaceController extends Controller
{
    protected array $managerRoles = ['admin','manager'];
    protected array $workspaceAdminRoles = ['admin'];

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
        ]);

        $orgId = Workspace::value('organization_id');
        if (!$orgId) {
            $orgId = Organization::first()->id ?? Organization::create(['name'=>'Default Org','slug'=>'default-org','plan'=>'free'])->id;
        }

        $workspace = Workspace::create([
            'organization_id' => $orgId,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::random(4),
            'timezone' => 'UTC',
            'is_default' => false,
        ]);

        // auto-add creator to the workspace as admin
        if ($user = auth()->user()) {
            Membership::firstOrCreate(
                ['user_id' => $user->id, 'workspace_id' => $workspace->id],
                ['role' => 'admin', 'status' => 'accepted', 'accepted_at' => now()]
            );
        }

        return back()->with('status', 'Workspace created.');
    }

    public function update(Request $request, Workspace $workspace)
    {
        $this->ensureWorkspaceAdmin($workspace, $request->user());
        $data = $request->validate([
            'name' => ['required','string','max:255'],
        ]);

        $workspace->update(['name' => $data['name']]);

        return back()->with('status', 'Workspace updated.');
    }

    public function destroy(Workspace $workspace)
    {
        $user = auth()->user();
        $membership = $user ? $workspace->memberships()->where('user_id', $user->id)->first() : null;
        abort_unless($membership && in_array($membership->role, $this->workspaceAdminRoles, true), 403);
        $workspace->delete();

        return back()->with('status', 'Workspace deleted.');
    }

    public function assignUser(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required','array'],
            'user_id.*' => ['exists:users,id'],
            'workspace_id' => ['required','exists:workspaces,id'],
            'role' => ['required','in:member,manager,admin'],
        ]);

        $workspace = Workspace::find($data['workspace_id']);
        $this->ensureWorkspaceAdmin($workspace, $request->user());

        foreach ($data['user_id'] as $uid) {
            $membership = Membership::where('user_id', $uid)
                ->where('workspace_id', $data['workspace_id'])
                ->first();
            $shouldNotify = false;

            if ($membership) {
                $membership->role = $data['role'];
                if ($membership->status === 'pending') {
                    $shouldNotify = true;
                }
                $membership->save();
            } else {
                $membership = Membership::create([
                    'user_id' => $uid,
                    'workspace_id' => $data['workspace_id'],
                    'role' => $data['role'],
                    'status' => 'pending',
                ]);
                $shouldNotify = true;
            }

            if ($shouldNotify) {
                $user = User::find($uid);
                if ($user && $workspace) {
                    try {
                        $user->notify(new WorkspaceInviteNotification($workspace));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }
        }

        return back()->with('status', 'Users assigned to workspace.');
    }

    public function index()
    {
        $user = auth()->user();
        $pendingInvites = Membership::with('workspace')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->get();
        $workspaces = Workspace::with(['memberships.user' => function ($q) {
            $q->select('id','name','email','role');
        }])->whereHas('memberships', function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->where('status', 'accepted');
        })->orderByDesc('created_at')->get();

        $users = User::orderBy('name')->get(['id','name','email']);

        return view('workspaces.index', [
            'workspaces' => $workspaces,
            'users' => $users,
            'pendingInvites' => $pendingInvites,
        ]);
    }

    public function switch(Request $request)
    {
        $user = auth()->user();
        $data = $request->validate([
            'workspace_id' => ['required','exists:workspaces,id'],
        ]);

        $hasAccess = Membership::where('user_id', $user->id)
            ->where('workspace_id', $data['workspace_id'])
            ->where('status', 'accepted')
            ->exists();

        abort_unless($hasAccess, 403);

        session(['current_workspace_id' => $data['workspace_id']]);

        return back()->with('status', 'Workspace switched.');
    }

    public function accept(Request $request, Workspace $workspace)
    {
        $user = $request->user();
        $membership = Membership::where('user_id', $user->id)
            ->where('workspace_id', $workspace->id)
            ->first();
        if (!$membership || $membership->status !== 'pending') {
            return back()->withErrors('No pending invitation found.');
        }

        $membership->status = 'accepted';
        $membership->accepted_at = now();
        $membership->save();

        if (!session('current_workspace_id')) {
            session(['current_workspace_id' => $workspace->id]);
        }

        return back()->with('status', 'Workspace invitation accepted.');
    }

    public function removeUser(Workspace $workspace, User $user, Request $request)
    {
        $this->ensureWorkspaceAdmin($workspace, $request->user());
        $workspace->memberships()->where('user_id', $user->id)->delete();

        return back()->with('status', 'User removed from workspace.');
    }

    protected function ensureWorkspaceAdmin(?Workspace $workspace, ?User $user): void
    {
        abort_unless($workspace && $user, 403);
        $membership = $workspace->memberships()->where('user_id', $user->id)->first();
        abort_unless($membership && in_array($membership->role, $this->workspaceAdminRoles, true), 403);
    }
}
