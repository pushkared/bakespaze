<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\Membership;
use Illuminate\Http\Request;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Carbon\Carbon;
use App\Models\Task;
use App\Models\AttendanceRecord;
use App\Models\AppSetting;

class VirtualOfficeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $workspaceId = session('current_workspace_id');

        $workspace = Workspace::with(['memberships.user' => function ($q) {
            $q->select('id','name','role','email');
        }])
        ->whereHas('memberships', fn($q) => $q->where('user_id', $user->id))
        ->when($workspaceId, fn($q) => $q->where('id', $workspaceId))
        ->first();

        // Fallback to first assigned workspace
        if (!$workspace) {
            $workspace = Workspace::with(['memberships.user' => function ($q) {
                $q->select('id','name','role','email');
            }])->whereHas('memberships', fn($q) => $q->where('user_id', $user->id))
            ->orderBy('name')->first();
        }

        abort_unless($workspace, 403);

        $members = $workspace->memberships->take(10);
        $settings = AppSetting::current();
        $summaries = $this->buildMemberSummaries($workspace, $settings);
        $nextMeeting = $this->nextCalendarEvent($user, $settings);

        return view('virtual-office.index', [
            'workspace' => $workspace,
            'members' => $members,
            'nextMeeting' => $nextMeeting,
            'summaries' => $summaries,
        ]);
    }

    protected function nextCalendarEvent($user, AppSetting $settings): ?array
    {
        if (empty($user->google_access_token)) {
            return null;
        }
        try {
            $client = new GoogleClient();
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->setRedirectUri(config('services.google.calendar_redirect') ?? config('services.google.redirect'));
            $client->setScopes(['https://www.googleapis.com/auth/calendar.events.readonly']);
            $client->setAccessType('offline');
            if ($user->google_access_token) {
                $client->setAccessToken(json_decode($user->google_access_token, true));
            }
            if ($client->isAccessTokenExpired() && $user->google_refresh_token) {
                $client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
                $newToken = $client->getAccessToken();
                $client->setAccessToken($newToken);
            }

            $service = new Calendar($client);
            $timezone = $settings->timezone ?? 'Asia/Kolkata';
            $now = Carbon::now($timezone)->toRfc3339String();
            $events = $service->events->listEvents('primary', [
                'timeMin' => $now,
                'maxResults' => 1,
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ]);
            $item = $events->getItems()[0] ?? null;
            if (!$item) return null;
            $start = optional($item->getStart())->getDateTime() ?? optional($item->getStart())->getDate();
            $parsed = Carbon::parse($start)->setTimezone($timezone);
            return [
                'title' => $item->getSummary() ?: 'Upcoming meeting',
                'time' => $parsed->format('D d M, h:i A'),
                'raw' => $parsed,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function buildMemberSummaries(Workspace $workspace, AppSetting $settings): array
    {
        $timezone = $settings->timezone ?? 'Asia/Kolkata';
        $today = Carbon::now($timezone)->toDateString();
        $memberIds = $workspace->memberships->pluck('user_id');

        $tasksByUser = Task::select('tasks.*', 'task_user.user_id')
            ->join('task_user', 'tasks.id', '=', 'task_user.task_id')
            ->where('tasks.workspace_id', $workspace->id)
            ->orderBy('tasks.due_date')
            ->get()
            ->groupBy('user_id');

        $attendanceByUser = AttendanceRecord::whereIn('user_id', $memberIds)
            ->whereDate('work_date', $today)
            ->get()
            ->groupBy('user_id');

        $summaries = [];

        foreach ($memberIds as $userId) {
            $attendance = $attendanceByUser->get($userId, collect());
            $record = $attendance instanceof \Illuminate\Support\Collection
                ? $attendance->sortByDesc('clock_in')->first()
                : null;
            $loggedToday = $attendance instanceof \Illuminate\Support\Collection && $attendance->isNotEmpty();
            $currentlyIn = $record && !$record->clock_out;
            $minutes = $record?->minutes_worked ?? 0;

            if ($record && $record->clock_in && !$record->clock_out) {
                $clockIn = Carbon::parse($record->clock_in)->timezone($timezone);
                $now = Carbon::now($timezone);
                $liveMinutes = $clockIn->diffInMinutes($now);
                $lunchStart = $record->lunch_start ? Carbon::parse($record->lunch_start)->timezone($timezone) : null;
                $lunchEnd = $record->lunch_end ? Carbon::parse($record->lunch_end)->timezone($timezone) : null;
                if ($lunchStart && $lunchEnd) {
                    $liveMinutes -= $lunchStart->diffInMinutes($lunchEnd);
                } elseif ($lunchStart) {
                    $liveMinutes -= $lunchStart->diffInMinutes($now);
                }
                $liveMinutes -= (int)($record->break_minutes_used ?? 0);
                $liveMinutes = max(0, $liveMinutes);
                $minutes = max($minutes, $liveMinutes);
            }

            $breakLimit = (int)($settings->break_duration_minutes ?? 30);
            $breakMinutes = (int)($record?->break_minutes_used ?? 0);
            $breakActive = $record && $record->lunch_start && !$record->lunch_end;
            if ($breakActive) {
                $breakMinutes = min($breakLimit, $breakMinutes + Carbon::parse($record->lunch_start)->timezone($timezone)->diffInMinutes(Carbon::now($timezone)));
            }
            $breakMinutes = (int) round($breakMinutes);
            $breakLimit = (int) round($breakLimit);

            $summaries[$userId] = [
                'logged_today' => $loggedToday,
                'currently_in' => $currentlyIn,
                'status_label' => $breakActive ? 'On Break' : ($currentlyIn ? 'Active' : ($loggedToday ? 'Logged Today' : 'Not Logged In')),
                'punch_in_time' => $record && $record->clock_in ? Carbon::parse($record->clock_in)->timezone($timezone)->format('h:i A') : null,
                'punch_out_time' => $record && $record->clock_out ? Carbon::parse($record->clock_out)->timezone($timezone)->format('h:i A') : null,
                'break_active' => $breakActive,
                'break_exhausted' => $breakMinutes >= $breakLimit,
                'break_minutes' => $breakMinutes,
                'break_limit' => $breakLimit,
                'hours_today' => $this->formatMinutes($minutes),
                'tasks' => collect($tasksByUser->get($userId))->take(4)->map(function ($task) {
                    return [
                        'title' => $task->title,
                        'status' => $task->status ?? 'open',
                        'due' => $task->due_date ? Carbon::parse($task->due_date)->format('d M') : null,
                    ];
                })->values()->all(),
            ];
        }

        return $summaries;
    }

    protected function formatMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%dh %dm', $h, $m);
    }
}
