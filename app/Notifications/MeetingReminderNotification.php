<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class MeetingReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $title,
        private ?Carbon $startTime,
        private ?string $meetLink
    ) {
    }

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $time = $this->startTime ? $this->startTime->format('h:i A') : '';
        $body = trim($this->title.' '.$time);
        $url = $this->meetLink ?: route('calendar.index');

        return (new WebPushMessage())
            ->title('Meeting in 10 minutes')
            ->icon('/images/icon-192.png')
            ->body($body)
            ->data([
                'url' => $url,
            ]);
    }
}
