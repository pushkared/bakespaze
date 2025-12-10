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

    protected function authorizeManager()
    {
        $user = auth()->user();
        abort_unless($user && in_array($user->role ?? '', $this->managerRoles), 403, 'Unauthorized');
    }

    public function store(Request $request)
    {
        $this->authorizeManager();

        $data = $request->validate([
            'name' => ['required','string','max:255'],
        ]);

        $orgId = Workspace::value('organization_id');
        if (!$orgId) {
            $orgId = Organization::first()->id ?? Organization::create(['name'=>'Default Org','slug'=>'default-org','plan'=>'free'])->id;
        }

        Workspace::create([
            'organization_id' => $orgId,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::random(4),
            'timezone' => 'UTC',
            'is_default' => false,
        ]);

        return back()->with('status', 'Workspace created.');
    }

    public function update(Request $request, Workspace $workspace)
    {
        $this->authorizeManager();

        $data = $request->validate([
            'name' => ['required','string','max:255'],
        ]);

        $workspace->update(['name' => $data['name']]);

        return back()->with('status', 'Workspace updated.');
    }

    public function destroy(Workspace $workspace)
    {
        $this->authorizeManager();
        $workspace->delete();

        return back()->with('status', 'Workspace deleted.');
    }

    public function assignUser(Request $request)
    {
        $this->authorizeManager();

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
}
