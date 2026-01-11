<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\AppSetting;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\BreakOverdueNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class SendBreakOverdueNotifications extends Command
{
    protected $signature = 'attendance:break-overdue';
    protected $description = 'Notify admins when a user exceeds their break duration.';

    public function handle(): int
    {
        $settings = AppSetting::current();
        $timezone = $settings->timezone ?? 'Asia/Kolkata';
        $now = Carbon::now($timezone);
        $breakLimit = (int)($settings->break_duration_minutes ?? 30);

        $records = AttendanceRecord::whereDate('work_date', $now->toDateString())
            ->whereNotNull('lunch_start')
            ->whereNull('lunch_end')
            ->get();

        foreach ($records as $record) {
            $lunchStart = Carbon::parse($record->lunch_start)->timezone($timezone);
            $liveMinutes = $lunchStart->diffInMinutes($now);
            $used = (int)($record->break_minutes_used ?? 0);
            $total = $used + $liveMinutes;
            if ($total < $breakLimit) {
                continue;
            }

            $employee = User::find($record->user_id);
            if (!$employee) {
                continue;
            }

            $cacheKey = 'break_overdue_'.$record->user_id.'_'.$now->toDateString();
            if (Cache::get($cacheKey)) {
                continue;
            }

            $workspaceIds = Membership::where('user_id', $employee->id)
                ->pluck('workspace_id');
            $adminIds = Membership::whereIn('workspace_id', $workspaceIds)
                ->where('role', 'admin')
                ->pluck('user_id')
                ->unique()
                ->all();
                $admins = User::whereIn('id', $adminIds)
                    ->where('notifications_enabled', true)
                    ->get();

            if ($admins->isEmpty()) {
                $admins = User::whereIn('role', ['admin', 'super_admin'])->get();
            }

            if ($admins->isNotEmpty()) {
                try {
                    Notification::send($admins, new BreakOverdueNotification($employee, $total - $breakLimit));
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            Cache::put($cacheKey, true, now()->addDay());
        }

        return Command::SUCCESS;
    }
}
