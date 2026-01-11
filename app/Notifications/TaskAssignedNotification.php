<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(private Task $task, private ?string $assignerName = null)
    {
    }

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage())
            ->title('New task assigned to you')
            ->icon('/images/icon-192.png')
            ->body(trim($this->task->title.' - from '.($this->assignerName ?: 'a teammate')))
            ->data([
                'url' => route('tasks.index'),
            ]);
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'task_assigned',
            'title' => 'New task assigned to you',
            'body' => trim($this->task->title.' - from '.($this->assignerName ?: 'a teammate')),
            'action_url' => route('tasks.index', ['status' => 'open', 'workspace_id' => $this->task->workspace_id]),
            'task_id' => $this->task->id,
        ];
    }
}
