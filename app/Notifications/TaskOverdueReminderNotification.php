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
        return [WebPushChannel::class];
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
}
