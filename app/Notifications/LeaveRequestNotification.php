<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class LeaveRequestNotification extends Notification
{
    use Queueable;

    public function __construct(private LeaveRequest $request)
    {
    }

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $title = $this->request->user?->name.' requested leave';
        $range = $this->request->start_date->format('d M').' - '.$this->request->end_date->format('d M');

        return (new WebPushMessage())
            ->title('Leave request')
            ->icon('/images/icon-192.png')
            ->body($title.' ('.$range.')')
            ->data([
                'url' => route('leaves.index'),
            ]);
    }
}
