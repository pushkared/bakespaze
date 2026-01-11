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
        return ['database', WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $title = ($this->message->sender?->name ?: 'Someone').' sent a message';
        $body = $this->message->body ?: 'Attachment';

        return (new WebPushMessage())
            ->title($title)
            ->icon('/images/icon-192.png')
            ->body($body)
            ->data([
                'url' => route('chat.index', ['conversation_id' => $this->message->conversation_id]),
            ]);
    }

    public function toDatabase($notifiable): array
    {
        $sender = $this->message->sender?->name ?: 'Someone';
        $body = $this->message->body ?: 'Attachment';

        return [
            'type' => 'chat_message',
            'title' => $sender.' sent a message',
            'body' => $body,
            'action_url' => route('chat.index', ['conversation_id' => $this->message->conversation_id]),
            'conversation_id' => $this->message->conversation_id,
        ];
    }
}
