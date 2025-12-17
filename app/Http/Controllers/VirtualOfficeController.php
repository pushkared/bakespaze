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
        $summaries = $this->buildMemberSummaries($workspace);
        $nextMeeting = $this->nextCalendarEvent($user);

        return view('virtual-office.index', [
            'workspace' => $workspace,
            'members' => $members,
            'nextMeeting' => $nextMeeting,
            'summaries' => $summaries,
        ]);
    }

    protected function nextCalendarEvent($user): ?array
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
            $now = Carbon::now('Asia/Kolkata')->toRfc3339String();
            $events = $service->events->listEvents('primary', [
                'timeMin' => $now,
                'maxResults' => 1,
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ]);
            $item = $events->getItems()[0] ?? null;
            if (!$item) return null;
            $start = optional($item->getStart())->getDateTime() ?? optional($item->getStart())->getDate();
            $parsed = Carbon::parse($start)->setTimezone('Asia/Kolkata');
            return [
                'title' => $item->getSummary() ?: 'Upcoming meeting',
                'time' => $parsed->format('D d M, h:i A'),
                'raw' => $parsed,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function buildMemberSummaries(Workspace $workspace): array
    {
        $today = Carbon::now('Asia/Kolkata')->toDateString();
        $memberIds = $workspace->memberships->pluck('user_id');

        $tasksByUser = Task::select('tasks.*', 'task_user.user_id')
            ->join('task_user', 'tasks.id', '=', 'task_user.task_id')
            ->where('tasks.workspace_id', $workspace->id)
            ->whereDate('tasks.due_date', $today)
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
                $clockIn = Carbon::parse($record->clock_in)->timezone('Asia/Kolkata');
                $now = Carbon::now('Asia/Kolkata');
                $liveMinutes = $clockIn->diffInMinutes($now);
                $lunchStart = $record->lunch_start ? Carbon::parse($record->lunch_start)->timezone('Asia/Kolkata') : null;
                $lunchEnd = $record->lunch_end ? Carbon::parse($record->lunch_end)->timezone('Asia/Kolkata') : null;
                if ($lunchStart && $lunchEnd) {
                    $liveMinutes -= $lunchStart->diffInMinutes($lunchEnd);
                }
                $minutes = max($minutes, $liveMinutes);
            }

            $summaries[$userId] = [
                'logged_today' => $loggedToday,
                'currently_in' => $currentlyIn,
                'status_label' => $currentlyIn ? 'Active' : ($loggedToday ? 'Logged Today' : 'Not Logged In'),
                'punch_in_time' => $record && $record->clock_in ? Carbon::parse($record->clock_in)->timezone('Asia/Kolkata')->format('h:i A') : null,
                'punch_out_time' => $record && $record->clock_out ? Carbon::parse($record->clock_out)->timezone('Asia/Kolkata')->format('h:i A') : null,
                'hours_today' => $this->formatMinutes($minutes),
                'tasks' => collect($tasksByUser->get($userId))->take(3)->map(function ($task) {
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
