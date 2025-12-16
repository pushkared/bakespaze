<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\Membership;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkspaceController extends Controller
{
    protected array $managerRoles = ['admin','manager'];

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

        // auto-add creator to the workspace
        if ($user = auth()->user()) {
            Membership::firstOrCreate(
                ['user_id' => $user->id, 'workspace_id' => $workspace->id],
                ['role' => $user->role === 'admin' ? 'admin' : 'manager']
            );
        }

        return back()->with('status', 'Workspace created.');
    }

    public function update(Request $request, Workspace $workspace)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
        ]);

        $workspace->update(['name' => $data['name']]);

        return back()->with('status', 'Workspace updated.');
    }

    public function destroy(Workspace $workspace)
    {
        // allow deletion only if user belongs to workspace
        $user = auth()->user();
        abort_unless($user && $workspace->memberships()->where('user_id', $user->id)->exists(), 403);
        $workspace->delete();

        return back()->with('status', 'Workspace deleted.');
    }

    public function assignUser(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required','exists:users,id'],
            'workspace_id' => ['required','exists:workspaces,id'],
            'role' => ['required','in:member,manager,admin'],
        ]);

        Membership::updateOrCreate(
            ['user_id' => $data['user_id'], 'workspace_id' => $data['workspace_id']],
            ['role' => $data['role']]
        );

        return back()->with('status', 'User assigned to workspace.');
    }

    public function index()
    {
        $user = auth()->user();
        $workspaces = Workspace::with(['memberships.user' => function ($q) {
            $q->select('id','name','email','role');
        }])->whereHas('memberships', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->orderBy('name')->get();

        $users = User::orderBy('name')->get(['id','name','email']);

        return view('workspaces.index', [
            'workspaces' => $workspaces,
            'users' => $users,
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
            ->exists();

        abort_unless($hasAccess, 403);

        session(['current_workspace_id' => $data['workspace_id']]);

        return back()->with('status', 'Workspace switched.');
    }
}
