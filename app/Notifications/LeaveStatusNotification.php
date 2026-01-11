<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class LeaveStatusNotification extends Notification
{
    use Queueable;

    public function __construct(private LeaveRequest $request)
    {
    }

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $range = $this->request->start_date->format('d M').' - '.$this->request->end_date->format('d M');
        $status = ucfirst($this->request->status);

        return (new WebPushMessage())
            ->title('Leave '.$status)
            ->icon('/images/icon-192.png')
            ->body($range)
            ->data([
                'url' => route('leaves.index'),
            ]);
    }

    public function toDatabase($notifiable): array
    {
        $range = $this->request->start_date->format('d M').' - '.$this->request->end_date->format('d M');
        $status = ucfirst($this->request->status);

        return [
            'type' => 'leave_status',
            'title' => 'Leave '.$status,
            'body' => $range,
            'action_url' => route('leaves.index'),
            'leave_id' => $this->request->id,
        ];
    }
}
