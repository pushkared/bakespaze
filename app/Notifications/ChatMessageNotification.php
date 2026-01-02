<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ChatMessageNotification extends Notification
{
    use Queueable;

    public function __construct(private Message $message)
    {
    }

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $title = $this->message->sender?->name.' sent a message';
        $body = $this->message->body ?: 'Attachment';

        return (new WebPushMessage())
            ->title($title)
            ->icon('/images/icon-192.png')
            ->body($body)
            ->data([
                'url' => route('chat.index'),
            ]);
    }
}
