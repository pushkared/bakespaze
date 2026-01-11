<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Notifications\AttendanceWindowClosingNotification;
use App\Models\AppSetting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendAttendanceReminders extends Command
{
    protected $signature = 'attendance:remind';
    protected $description = 'Send web push reminders before the punch-in window expires.';

    public function handle(): int
    {
        $settings = AppSetting::current();
        $timezone = $settings->timezone ?? 'Asia/Kolkata';
        $now = Carbon::now($timezone);
        $start = $now->copy()->setTimeFromTimeString($settings->punch_in_start ?? '09:00:00');
        $end = $now->copy()->setTimeFromTimeString($settings->punch_in_end ?? '11:00:00');
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
                    if (!$user->notifications_enabled) {
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
