<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TaskAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(private Task $task)
    {
    }

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $due = $this->task->due_date ? $this->task->due_date->format('d M') : 'No due date';

        return (new WebPushMessage())
            ->title('Task accepted')
            ->icon('/images/icon-192.png')
            ->body($this->task->title.' - '.$due)
            ->data([
                'url' => route('tasks.index'),
            ]);
    }
}
