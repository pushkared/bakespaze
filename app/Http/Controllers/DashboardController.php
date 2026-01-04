<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\AttendanceRecord;
use App\Models\AppSetting;

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
            ->where(function ($q) use ($request) {
                $q->where('creator_id', $request->user()->id)
                  ->orWhereHas('assignees', fn($a) => $a->where('users.id', $request->user()->id));
            })
            ->where('status', '!=', 'completed')
            ->orderByRaw('ISNULL(due_date), due_date asc')
            ->limit(5)
            ->get();

        $timezone = AppSetting::current()->timezone ?? 'Asia/Kolkata';
        $now = Carbon::now($timezone);
        $hour = (int)$now->format('H');
        $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');

        $todayRecord = AttendanceRecord::where('user_id', $request->user()->id)
            ->whereDate('work_date', $now->toDateString())
            ->latest()
            ->first();
        $punchState = [
            'punched_in' => $todayRecord && !$todayRecord->clock_out,
            'punched_at' => $todayRecord && $todayRecord->clock_in ? Carbon::parse($todayRecord->clock_in)->format('h:i A') : null,
            'lunch_started' => $todayRecord && $todayRecord->lunch_start,
            'lunch_ended' => $todayRecord && $todayRecord->lunch_end,
            'lunch_start_at' => $todayRecord && $todayRecord->lunch_start ? Carbon::parse($todayRecord->lunch_start)->format('h:i A') : null,
            'lunch_end_at' => $todayRecord && $todayRecord->lunch_end ? Carbon::parse($todayRecord->lunch_end)->format('h:i A') : null,
            'can_punch_in' => !$todayRecord || $todayRecord->clock_out ? $this->canPunchIn($now) : false,
        ];

        return view('dashboard.index', [
            'workspace' => $workspace,
            'tasks' => $tasks,
            'greeting' => $greeting,
            'todayDate' => $now->format('D d M'),
            'currentTime' => $now->format('h:i A'),
            'punchState' => $punchState,
        ]);
    }

    protected function canPunchIn(Carbon $now): bool
    {
        $settings = AppSetting::current();
        $startTime = $settings->punch_in_start ?? '09:00:00';
        $endTime = $settings->punch_in_end ?? '11:00:00';
        $start = $now->copy()->setTimeFromTimeString($startTime);
        $end = $now->copy()->setTimeFromTimeString($endTime);
        return $now->between($start, $end, true);
    }
}
