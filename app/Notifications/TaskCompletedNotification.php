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
        return ['database', WebPushChannel::class];
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

    public function toDatabase($notifiable): array
    {
        $by = $this->completedBy ? ' by '.$this->completedBy : '';

        return [
            'type' => 'task_completed',
            'title' => 'Task completed',
            'body' => trim($this->task->title.$by),
            'action_url' => route('tasks.index', ['status' => 'completed', 'workspace_id' => $this->task->workspace_id]),
            'task_id' => $this->task->id,
        ];
    }
}
