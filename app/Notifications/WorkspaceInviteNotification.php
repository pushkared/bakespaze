<?php

namespace App\Notifications;

use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class WorkspaceInviteNotification extends Notification
{
    use Queueable;

    public function __construct(private Workspace $workspace)
    {
    }

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage())
            ->title('Workspace invitation')
            ->icon('/images/icon-192.png')
            ->body('Tap to accept your invitation to '.$this->workspace->name)
            ->data([
                'url' => route('workspaces.index'),
            ]);
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'workspace_invite',
            'title' => 'Workspace invitation',
            'body' => 'Tap to accept your invitation to '.$this->workspace->name,
            'action_url' => route('workspaces.index'),
            'workspace_id' => $this->workspace->id,
        ];
    }
}
