<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TaskCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(private Task $task, private ?string $completedBy = null)
    {
    }

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $by = $this->completedBy ? ' by '.$this->completedBy : '';
        return (new WebPushMessage())
            ->title('Task completed')
            ->icon('/images/icon-192.png')
            ->body(trim($this->task->title.$by))
            ->data([
                'url' => route('tasks.index'),
            ]);
    }
}
