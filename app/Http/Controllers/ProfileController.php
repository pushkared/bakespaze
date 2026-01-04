<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\MessageAttachment;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        return view('profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'confirmed', 'min:8'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return back()->with('status', 'Profile updated.');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        DB::transaction(function () use ($user) {
            $taskIds = Task::where('creator_id', $user->id)->pluck('id');

            $taskAttachmentPaths = TaskAttachment::whereIn('task_id', $taskIds)
                ->orWhere('user_id', $user->id)
                ->pluck('path')
                ->unique();

            $messageAttachmentPaths = MessageAttachment::where('user_id', $user->id)
                ->pluck('path')
                ->unique();

            if ($taskAttachmentPaths->isNotEmpty()) {
                Storage::disk('public')->delete($taskAttachmentPaths->all());
            }
            if ($messageAttachmentPaths->isNotEmpty()) {
                Storage::disk('public')->delete($messageAttachmentPaths->all());
            }

            $user->delete();
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Account deleted.');
    }
}
