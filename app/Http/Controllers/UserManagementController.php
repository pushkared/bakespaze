<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    protected array $managerRoles = ['admin', 'manager'];

    protected function authorizeManager()
    {
        $user = auth()->user();
        abort_unless($user && in_array($user->role, $this->managerRoles), 403, 'Unauthorized');
    }

    public function index()
    {
        $this->authorizeManager();
        $users = User::select('id', 'name', 'email', 'role', 'department')->orderBy('name')->get();
        $roles = ['admin', 'manager', 'employee'];
        return view('users.index', compact('users', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeManager();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:admin,manager,employee'],
            'department' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update($data);

        return back()->with('status', 'User updated.');
    }
}
