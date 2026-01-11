<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\Task;
use App\Notifications\TaskOverdueReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendTaskOverdueReminders extends Command
{
    protected $signature = 'tasks:remind-overdue';
    protected $description = 'Send web push reminders for overdue tasks (1 day and 3 days).';

    public function handle(): int
    {
        $timezone = AppSetting::current()->timezone ?? 'Asia/Kolkata';
        $today = Carbon::now($timezone)->startOfDay();
        $todayKey = $today->toDateString();

        Task::with(['assignees', 'creator'])
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $todayKey)
            ->chunk(200, function ($tasks) use ($today, $todayKey) {
                foreach ($tasks as $task) {
                    $due = Carbon::parse($task->due_date)->startOfDay();
                    $daysOverdue = (int) $due->diffInDays($today);
                    if (!in_array($daysOverdue, [1, 3], true)) {
                        continue;
                    }

                    $cacheKey = 'task_overdue_'.$task->id.'_'.$daysOverdue.'_'.$todayKey;
                    if (Cache::get($cacheKey)) {
                        continue;
                    }

                    $recipients = collect()
                        ->merge($task->assignees ?? [])
                        ->when($task->creator, fn($c) => $c->push($task->creator))
                        ->unique('id')
                        ->values();

                    foreach ($recipients as $user) {
                        try {
                            if ($user->notifications_enabled) {
                                $user->notify(new TaskOverdueReminderNotification($task, $daysOverdue));
                            }
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }

                    Cache::put($cacheKey, true, now()->addHours(20));
                }
            });

        return Command::SUCCESS;
    }
}
