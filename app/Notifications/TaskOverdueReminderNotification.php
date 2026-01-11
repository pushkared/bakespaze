<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TaskOverdueReminderNotification extends Notification
{
    use Queueable;

    public function __construct(private Task $task, private int $daysOverdue)
    {
    }

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $days = $this->daysOverdue === 1 ? '1 day' : $this->daysOverdue.' days';

        return (new WebPushMessage())
            ->title('Task overdue')
            ->icon('/images/icon-192.png')
            ->body($this->task->title.' • '.$days.' overdue')
            ->data([
                'url' => route('tasks.index'),
            ]);
    }

    public function toDatabase($notifiable): array
    {
        $days = $this->daysOverdue === 1 ? '1 day' : $this->daysOverdue.' days';

        return [
            'type' => 'task_overdue',
            'title' => 'Task overdue',
            'body' => $this->task->title.' • '.$days.' overdue',
            'action_url' => route('tasks.index', [
                'status' => 'overdue',
                'workspace_id' => $this->task->workspace_id,
            ]),
            'task_id' => $this->task->id,
        ];
    }
}
