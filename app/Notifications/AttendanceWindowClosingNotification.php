<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AttendanceWindowClosingNotification extends Notification
{
    use Queueable;

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage())
            ->title('Punch-in window closing soon')
            ->icon('/images/icon-192.png')
            ->body('You have limited time left to punch in today.')
            ->data([
                'url' => route('attendance.index'),
            ]);
    }
}
