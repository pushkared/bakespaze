<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\AppSetting;
use App\Models\Membership;
use Carbon\Carbon;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return $this->renderProfile($request->user(), $request->user());
    }

    public function show(Request $request, User $user)
    {
        return $this->renderProfile($user, $request->user());
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['nullable', 'confirmed', 'min:8'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $user->name = $data['name'];
        if (!empty($data['email'])) {
            $user->email = $data['email'];
        }
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = $path;
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

    protected function renderProfile(User $profileUser, User $viewer)
    {
        $timezone = AppSetting::current()->timezone ?? 'Asia/Kolkata';
        $now = Carbon::now($timezone);
        $todayRecord = AttendanceRecord::where('user_id', $profileUser->id)
            ->whereDate('work_date', $now->toDateString())
            ->latest()
            ->first();

        $todayMinutes = $todayRecord?->minutes_worked ?? 0;
        if ($todayRecord && $todayRecord->clock_in && !$todayRecord->clock_out) {
            $clockIn = Carbon::parse($todayRecord->clock_in);
            $liveMinutes = $clockIn->diffInMinutes($now);
            $lunchStart = $todayRecord->lunch_start ? Carbon::parse($todayRecord->lunch_start) : null;
            $lunchEnd = $todayRecord->lunch_end ? Carbon::parse($todayRecord->lunch_end) : null;
            if ($lunchStart && $lunchEnd) {
                $liveMinutes -= $lunchStart->diffInMinutes($lunchEnd);
            } elseif ($lunchStart) {
                $liveMinutes -= $lunchStart->diffInMinutes($now);
            }
            $liveMinutes -= (int)($todayRecord->break_minutes_used ?? 0);
            $liveMinutes = max(0, $liveMinutes);
            $todayMinutes = max($todayMinutes, $liveMinutes);
        }

        $punchInTime = $todayRecord && $todayRecord->clock_in
            ? Carbon::parse($todayRecord->clock_in)->timezone($timezone)->format('h:i A')
            : null;

        $workspaceId = session('current_workspace_id');
        $allowedWorkspaceIds = collect();
        if ($workspaceId) {
            $shared = Membership::where('workspace_id', $workspaceId)
                ->whereIn('user_id', [$viewer->id, $profileUser->id])
                ->distinct()
                ->count('user_id') === 2;
            if ($shared) {
                $allowedWorkspaceIds = collect([$workspaceId]);
            }
        } else {
            $viewerWorkspaces = Membership::where('user_id', $viewer->id)->pluck('workspace_id');
            $profileWorkspaces = Membership::where('user_id', $profileUser->id)->pluck('workspace_id');
            $allowedWorkspaceIds = $viewerWorkspaces->intersect($profileWorkspaces)->values();
        }

        $assignedTasksQuery = Task::with(['workspace:id,name'])
            ->whereHas('assignees', fn($q) => $q->where('users.id', $profileUser->id));
        if ($allowedWorkspaceIds->isNotEmpty()) {
            $assignedTasksQuery->whereIn('workspace_id', $allowedWorkspaceIds);
        } else {
            $assignedTasksQuery->whereRaw('1 = 0');
        }
        $assignedTasks = $assignedTasksQuery
            ->orderByRaw('ISNULL(due_date), due_date asc')
            ->limit(10)
            ->get();

        $todayTasks = $assignedTasks->filter(function ($task) use ($now) {
            return $task->due_date && $task->due_date->isSameDay($now);
        })->values();

        $avatarUrl = $profileUser->avatar_url;
        if ($avatarUrl && !Str::startsWith($avatarUrl, ['http://', 'https://'])) {
            $avatarUrl = Storage::url($avatarUrl);
        }

        return view('profile.show', [
            'user' => $profileUser,
            'viewer' => $viewer,
            'avatarUrl' => $avatarUrl,
            'punchInTime' => $punchInTime,
            'todayHours' => $this->formatMinutes($todayMinutes),
            'assignedTasks' => $assignedTasks,
            'todayTasks' => $todayTasks,
        ]);
    }

    protected function formatMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%dh %dm', $h, $m);
    }
}
