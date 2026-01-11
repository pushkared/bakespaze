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
        return ['database', WebPushChannel::class];
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

    public function toDatabase($notifiable): array
    {
        $name = $this->employee->name ?? 'Employee';
        $over = max(1, $this->overMinutes);

        return [
            'type' => 'break_overdue',
            'title' => 'Break overdue',
            'body' => "{$name}'s break is overdue by {$over} min.",
            'action_url' => route('attendance.index', ['user_id' => $this->employee->id]),
            'employee_id' => $this->employee->id,
        ];
    }
}
