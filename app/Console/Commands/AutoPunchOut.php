<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoPunchOut extends Command
{
    protected $signature = 'attendance:auto-punch-out';
    protected $description = 'Auto punch-out users before midnight.';

    public function handle(): int
    {
        $settings = AppSetting::current();
        $timezone = $settings->timezone ?? 'Asia/Kolkata';
        $now = Carbon::now($timezone);
        $breakLimit = (int)($settings->break_duration_minutes ?? 30);

        $autoOutAt = $now->copy()->setTime(23, 55, 0);
        if ($now->lt($autoOutAt)) {
            return Command::SUCCESS;
        }

        AttendanceRecord::whereNull('clock_out')
            ->whereNotNull('clock_in')
            ->whereDate('work_date', $now->toDateString())
            ->orderBy('clock_in')
            ->chunk(200, function ($records) use ($autoOutAt, $breakLimit, $timezone) {
                foreach ($records as $record) {
                    $clockIn = Carbon::parse($record->clock_in)->timezone($timezone);
                    if ($autoOutAt->lt($clockIn)) {
                        continue;
                    }

                    if ($record->lunch_start && !$record->lunch_end) {
                        $lunchStart = Carbon::parse($record->lunch_start)->timezone($timezone);
                        $duration = $lunchStart->diffInMinutes($autoOutAt);
                        $record->break_minutes_used = min($breakLimit, (int)($record->break_minutes_used ?? 0) + $duration);
                        $record->lunch_start = null;
                        $record->lunch_end = null;
                    }

                    $record->clock_out = $autoOutAt;
                    $record->minutes_worked = $record->computeMinutes();
                    $record->save();
                }
            });

        return Command::SUCCESS;
    }
}
