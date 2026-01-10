<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class BreakOverdueNotification extends Notification
{
    use Queueable;

    protected User $employee;
    protected int $overMinutes;

    public function __construct(User $employee, int $overMinutes)
    {
        $this->employee = $employee;
        $this->overMinutes = $overMinutes;
    }

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $name = $this->employee->name ?? 'Employee';
        $over = max(1, $this->overMinutes);

        return (new WebPushMessage())
            ->title('Break overdue')
            ->icon('/images/icon-192.png')
            ->body("{$name}'s break is overdue by {$over} min.")
            ->data([
                'url' => route('attendance.index', ['user_id' => $this->employee->id]),
            ]);
    }
}
