<?php

namespace App\Notifications;

use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class WorkspaceAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(private Workspace $workspace)
    {
    }

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage())
            ->title('Added to workspace')
            ->icon('/images/icon-192.png')
            ->body($this->workspace->name)
            ->data([
                'url' => route('workspaces.index'),
            ]);
    }
}
