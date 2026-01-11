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
        return ['database', WebPushChannel::class];
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

    public function toDatabase($notifiable): array
    {
        $due = $this->task->due_date ? $this->task->due_date->format('d M') : 'No due date';

        return [
            'type' => 'task_accepted',
            'title' => 'Task accepted',
            'body' => $this->task->title.' - '.$due,
            'action_url' => route('tasks.index', ['status' => 'ongoing', 'workspace_id' => $this->task->workspace_id]),
            'task_id' => $this->task->id,
        ];
    }
}
