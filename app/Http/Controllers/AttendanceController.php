<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\AttendanceRecord;
use App\Models\Workspace;
use App\Models\User;
use App\Models\AppSetting;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $viewer = $request->user();
        $isAdmin = in_array($viewer->role, ['admin', 'super_admin'], true);
        $workspaceId = session('current_workspace_id');
        $availableUsers = collect();
        if ($isAdmin) {
            $availableUsers = User::when($workspaceId, fn($q) => $q->whereHas('memberships', fn($m) => $m->where('workspace_id', $workspaceId)))
                ->orderBy('name')
                ->get(['id','name','email']);
        }
        $selectedUserId = $isAdmin ? (int)($request->input('user_id') ?: $viewer->id) : $viewer->id;
        $selectedUser = $isAdmin
            ? ($availableUsers->firstWhere('id', $selectedUserId) ?? $viewer)
            : $viewer;

        $timezone = AppSetting::current()->timezone ?? 'Asia/Kolkata';
        $now = Carbon::now($timezone);

        $todayRecord = AttendanceRecord::where('user_id', $selectedUser->id)
            ->whereDate('work_date', $now->toDateString())
            ->latest()
            ->first();
        $canPunchOut = $todayRecord && !$todayRecord->clock_out
            && $now->copy()->setTime(19, 0, 0)->lte($now);
        $settings = AppSetting::current();
        $breakLimit = (int)($settings->break_duration_minutes ?? 30);
        $breakUsed = (int)($todayRecord?->break_minutes_used ?? 0);
        $breakActive = $todayRecord && $todayRecord->lunch_start && !$todayRecord->lunch_end;
        if ($breakActive) {
            $live = Carbon::parse($todayRecord->lunch_start)->timezone($timezone)->diffInMinutes($now);
            $breakUsed = min($breakLimit, $breakUsed + $live);
        }

        $recentRecords = AttendanceRecord::where('user_id', $selectedUser->id)
            ->whereDate('work_date', '>=', $now->copy()->subDays(6)->toDateString())
            ->orderBy('work_date', 'desc')
            ->get()
            ->groupBy('work_date');

        $recent = collect();
        for ($i = 0; $i < 7; $i++) {
            $day = $now->copy()->subDays($i);
            $records = $recentRecords->get($day->toDateString(), collect());
            $minutes = $records->sum('minutes_worked');
            $recent->push([
                'label' => $day->format('D d M'),
                'hours' => $this->formatMinutes($minutes),
            ]);
        }
        $recent = $recent->reverse()->values();

        $weekMinutes = $recent->sum(function ($r) {
            [$h, $m] = explode('h', $r['hours']);
            $h = (int)trim($h);
            $m = (int)str_replace('m', '', trim($m));
            return ($h * 60) + $m;
        });

        $todayMinutes = $todayRecord?->minutes_worked ?? 0;
        if ($todayRecord && $todayRecord->clock_in && !$todayRecord->clock_out) {
            $clockIn = \Carbon\Carbon::parse($todayRecord->clock_in);
            $liveMinutes = $clockIn->diffInMinutes($now);
            $lunchStart = $todayRecord->lunch_start ? \Carbon\Carbon::parse($todayRecord->lunch_start) : null;
            $lunchEnd = $todayRecord->lunch_end ? \Carbon\Carbon::parse($todayRecord->lunch_end) : null;
            if ($lunchStart && $lunchEnd) {
                $liveMinutes -= $lunchStart->diffInMinutes($lunchEnd);
            } elseif ($lunchStart) {
                $liveMinutes -= $lunchStart->diffInMinutes($now);
            }
            $liveMinutes -= (int)($todayRecord->break_minutes_used ?? 0);
            $liveMinutes = max(0, $liveMinutes);
            $todayMinutes = max($todayMinutes, $liveMinutes);
        }

        $stats = [
            'today_hours' => $this->formatMinutes($todayMinutes),
            'week_hours' => $this->formatMinutes($weekMinutes),
            'sessions' => AttendanceRecord::where('user_id', $selectedUser->id)->whereDate('work_date', $now->toDateString())->count(),
        ];

        return view('attendance.index', [
            'user' => $selectedUser,
            'viewer' => $viewer,
            'isAdmin' => $isAdmin,
            'users' => $availableUsers,
            'selectedUserId' => $selectedUser->id,
            'now' => $now,
            'stats' => $stats,
            'recent' => $recent,
            'todayRecord' => $todayRecord,
            'canPunchIn' => $this->canPunchIn($now),
            'canPunchOut' => $canPunchOut,
            'breakLimit' => $breakLimit,
            'breakUsed' => $breakUsed,
            'breakActive' => $breakActive,
        ]);
    }

    public function punchIn(Request $request)
    {
        $user = $request->user();
        $now = Carbon::now('Asia/Kolkata');

        if (!$this->canPunchIn($now)) {
            return back()->withErrors('Punch in allowed only between 9:00 AM and 11:00 AM IST.');
        }

        // close any open record
        $open = AttendanceRecord::where('user_id', $user->id)->whereNull('clock_out')->latest()->first();
        if ($open) {
            $open->clock_out = $now;
            $open->minutes_worked = $open->computeMinutes();
            $open->save();
        }

        AttendanceRecord::create([
            'user_id' => $user->id,
            'workspace_id' => null,
            'work_date' => $now->toDateString(),
            'clock_in' => $now,
        ]);

        return back()->with('status', 'Punched in.');
    }

    public function punchOut(Request $request)
    {
        $user = $request->user();
        $settings = AppSetting::current();
        $timezone = $settings->timezone ?? 'Asia/Kolkata';
        $now = Carbon::now($timezone);
        $cutoff = $now->copy()->setTime(19, 0, 0);

        $open = AttendanceRecord::where('user_id', $user->id)->whereNull('clock_out')->latest()->first();
        if ($open) {
            if ($now->lt($cutoff)) {
                return back()->withErrors('Punch out is available after 7:00 PM.');
            }
            if ($open->lunch_start && !$open->lunch_end) {
                $limit = (int)($settings->break_duration_minutes ?? 30);
                $lunchStart = Carbon::parse($open->lunch_start)->timezone($timezone);
                $duration = $lunchStart->diffInMinutes($now);
                $open->break_minutes_used = min($limit, (int)($open->break_minutes_used ?? 0) + $duration);
                $open->lunch_start = null;
                $open->lunch_end = null;
            }
            $open->clock_out = $now;
            $open->minutes_worked = $open->computeMinutes();
            $open->save();
        }

        return back()->with('status', 'Punched out.');
    }

    public function lunchStart(Request $request)
    {
        $user = $request->user();
        $timezone = AppSetting::current()->timezone ?? 'Asia/Kolkata';
        $now = Carbon::now($timezone);

        $open = AttendanceRecord::where('user_id', $user->id)->whereNull('clock_out')->latest()->first();
        if ($open && $open->lunch_start && !$open->lunch_end) {
            return back()->withErrors('Break already in progress.');
        }
        if ($open) {
            $limit = (int)(AppSetting::current()->break_duration_minutes ?? 30);
            $used = (int)($open->break_minutes_used ?? 0);
            if ($used >= $limit) {
                return back()->withErrors('Break limit reached.');
            }
            $open->lunch_start = $now;
            $open->lunch_end = null;
            $open->save();
        }
        return back()->with('status', 'Break started.');
    }

    public function lunchEnd(Request $request)
    {
        $user = $request->user();
        $settings = AppSetting::current();
        $timezone = $settings->timezone ?? 'Asia/Kolkata';
        $now = Carbon::now($timezone);

        $open = AttendanceRecord::where('user_id', $user->id)->whereNull('clock_out')->latest()->first();
        if ($open && $open->lunch_start && !$open->lunch_end) {
            $limit = (int)($settings->break_duration_minutes ?? 30);
            $lunchStart = Carbon::parse($open->lunch_start)->timezone($timezone);
            $duration = $lunchStart->diffInMinutes($now);
            $open->break_minutes_used = min($limit, (int)($open->break_minutes_used ?? 0) + $duration);
            $open->lunch_start = null;
            $open->lunch_end = null;
            $open->minutes_worked = $open->computeMinutes();
            $open->save();
        }
        return back()->with('status', 'Break ended.');
    }

    protected function formatMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%dh %dm', $h, $m);
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
