<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\AttendanceRecord;
use App\Models\Workspace;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $now = Carbon::now('Asia/Kolkata');

        $todayRecord = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('work_date', $now->toDateString())
            ->latest()
            ->first();

        $recentRecords = AttendanceRecord::where('user_id', $user->id)
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
            }
            $todayMinutes = max($todayMinutes, $liveMinutes);
        }

        $stats = [
            'today_hours' => $this->formatMinutes($todayMinutes),
            'week_hours' => $this->formatMinutes($weekMinutes),
            'sessions' => AttendanceRecord::where('user_id', $user->id)->whereDate('work_date', $now->toDateString())->count(),
        ];

        return view('attendance.index', [
            'user' => $user,
            'now' => $now,
            'stats' => $stats,
            'recent' => $recent,
            'todayRecord' => $todayRecord,
            'canPunchIn' => $this->canPunchIn($now),
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
        $now = Carbon::now('Asia/Kolkata');

        $open = AttendanceRecord::where('user_id', $user->id)->whereNull('clock_out')->latest()->first();
        if ($open) {
            $open->clock_out = $now;
            $open->minutes_worked = $open->computeMinutes();
            $open->save();
        }

        return back()->with('status', 'Punched out.');
    }

    public function lunchStart(Request $request)
    {
        $user = $request->user();
        $now = Carbon::now('Asia/Kolkata');

        $open = AttendanceRecord::where('user_id', $user->id)->whereNull('clock_out')->latest()->first();
        if ($open && !$open->lunch_start) {
            $open->lunch_start = $now;
            $open->save();
        }
        return back()->with('status', 'Lunch started.');
    }

    public function lunchEnd(Request $request)
    {
        $user = $request->user();
        $now = Carbon::now('Asia/Kolkata');

        $open = AttendanceRecord::where('user_id', $user->id)->whereNull('clock_out')->latest()->first();
        if ($open && $open->lunch_start && !$open->lunch_end) {
            $open->lunch_end = $now;
            $open->minutes_worked = $open->computeMinutes();
            $open->save();
        }
        return back()->with('status', 'Lunch ended.');
    }

    protected function formatMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%dh %dm', $h, $m);
    }

    protected function canPunchIn(Carbon $now): bool
    {
        $start = $now->copy()->setTime(13, 0, 0);
        $end = $now->copy()->setTime(16, 0, 0);
        return $now->between($start, $end, true);
    }
}
