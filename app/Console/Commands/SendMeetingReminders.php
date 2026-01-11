<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendMeetingReminders extends Command
{
    protected $signature = 'meeting:remind';
    protected $description = 'Send web push reminders 10 minutes before meetings.';

    public function handle(): int
    {
        User::whereNotNull('google_access_token')
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    $this->handleUser($user);
                }
            });

        return Command::SUCCESS;
    }

    protected function handleUser(User $user): void
    {
        $client = $this->googleClient($user);
        if (!$client) {
            return;
        }

        try {
            $service = new Calendar($client);
            $now = Carbon::now()->toRfc3339String();
            $timeMax = Carbon::now()->addMinutes(30)->toRfc3339String();
            $events = $service->events->listEvents('primary', [
                'timeMin' => $now,
                'timeMax' => $timeMax,
                'maxResults' => 20,
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ]);
        } catch (\Throwable $e) {
            report($e);
            return;
        }

        $now = Carbon::now();

        foreach ($events->getItems() as $event) {
            $startRaw = optional($event->getStart())->getDateTime();
            if (!$startRaw) {
                continue; // skip all-day events
            }
            $start = Carbon::parse($startRaw);
            $remindAt = $start->copy()->subMinutes(10);
            if (!$now->between($remindAt, $remindAt->copy()->addMinutes(2))) {
                continue;
            }

            $attendees = collect($event->getAttendees() ?? [])
                ->map(fn($a) => strtolower($a->getEmail() ?? ''))
                ->filter()
                ->values();

            $organizerEmail = optional($event->getOrganizer())->getEmail() ?? optional($event->getCreator())->getEmail();
            if ($organizerEmail) {
                $attendees->push(strtolower($organizerEmail));
            }

            $emails = $attendees->unique()->values()->all();
            if (empty($emails)) {
                continue;
            }

            $users = User::whereIn('email', $emails)->get();
            foreach ($users as $target) {
                $cacheKey = sprintf('meeting_reminder_%s_%s_%s', $event->getId(), $target->id, $start->timestamp);
                if (Cache::get($cacheKey)) {
                    continue;
                }
                try {
                    if ($target->notifications_enabled) {
                        $target->notify(new MeetingReminderNotification(
                            $event->getSummary() ?: 'Meeting',
                            $start,
                            $event->getHangoutLink()
                        ));
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
                Cache::put($cacheKey, true, now()->addHours(6));
            }
        }
    }

    protected function googleClient(User $user): ?GoogleClient
    {
        try {
            $client = new GoogleClient();
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->setRedirectUri(config('services.google.calendar_redirect') ?? config('services.google.redirect'));
            $client->setScopes(['https://www.googleapis.com/auth/calendar.events.readonly']);
            $client->setAccessType('offline');

            if ($user->google_access_token) {
                $token = json_decode($user->google_access_token, true);
                $client->setAccessToken($token);
            }

            if ($client->isAccessTokenExpired() && $user->google_refresh_token) {
                $client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
                $newToken = $client->getAccessToken();
                $client->setAccessToken($newToken);
                $user->update([
                    'google_access_token' => json_encode($newToken),
                    'google_token_expires_at' => Carbon::now()->addSeconds($newToken['expires_in'] ?? 3500),
                ]);
            }

            return $client;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }
}
