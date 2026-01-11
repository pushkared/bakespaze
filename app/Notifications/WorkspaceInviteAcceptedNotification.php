<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class WorkspaceInviteAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(private Workspace $workspace, private User $acceptedBy)
    {
    }

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage())
            ->title('Workspace invitation accepted')
            ->icon('/images/icon-192.png')
            ->body($this->acceptedBy->name.' joined '.$this->workspace->name)
            ->data([
                'url' => route('workspaces.index'),
                'type' => 'workspace_accepted',
                'workspace_id' => $this->workspace->id,
                'user_id' => $this->acceptedBy->id,
            ]);
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'workspace_accepted',
            'title' => 'Workspace invitation accepted',
            'body' => $this->acceptedBy->name.' joined '.$this->workspace->name,
            'action_url' => route('workspaces.index'),
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->acceptedBy->id,
        ];
    }
}
