<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Notifications\AttendanceWindowClosingNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendAttendanceReminders extends Command
{
    protected $signature = 'attendance:remind';
    protected $description = 'Send web push reminders before the punch-in window expires.';

    public function handle(): int
    {
        $now = Carbon::now('Asia/Kolkata');
        $start = $now->copy()->setTime(9, 0);
        $end = $now->copy()->setTime(11, 0);
        $remindAt = $end->copy()->subMinutes(30);

        if (!$now->between($remindAt, $end)) {
            return Command::SUCCESS;
        }

        $date = $now->toDateString();
        $punchedIn = AttendanceRecord::whereDate('work_date', $date)
            ->whereNotNull('clock_in')
            ->pluck('user_id')
            ->all();

        User::whereNotIn('id', $punchedIn)
            ->chunk(200, function ($users) use ($date) {
                foreach ($users as $user) {
                    $cacheKey = 'attendance_reminder_'.$user->id.'_'.$date;
                    if (Cache::get($cacheKey)) {
                        continue;
                    }
                    try {
                        $user->notify(new AttendanceWindowClosingNotification());
                    } catch (\Throwable $e) {
                        report($e);
                    }
                    Cache::put($cacheKey, true, now()->addHours(6));
                }
            });

        return Command::SUCCESS;
    }
}
