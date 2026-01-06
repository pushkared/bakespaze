<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\Membership;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $user = $request->user();
        $term = trim($request->get('q', ''));
        if (strlen($term) < 2) {
            return response()->json([
                'users' => [],
                'tasks' => [],
                'chats' => [],
                'meetings' => [],
            ]);
        }

        $workspaceIds = Membership::where('user_id', $user->id)->pluck('workspace_id');

        $users = User::select('id', 'name', 'email', 'role')
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role ?? '',
            ]);

        $tasks = Task::select('tasks.id', 'tasks.title', 'tasks.due_date', 'tasks.status', 'tasks.workspace_id')
            ->with(['workspace:id,name', 'assignees:id,name'])
            ->whereIn('tasks.workspace_id', $workspaceIds)
            ->where(function ($q) use ($term) {
                $q->where('tasks.title', 'like', "%{$term}%")
                  ->orWhere('tasks.description', 'like', "%{$term}%");
            })
            ->orderByRaw('ISNULL(tasks.due_date), tasks.due_date asc')
            ->limit(6)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'due_date' => optional($t->due_date)->format('d M'),
                'assigned_to' => $t->assignees->first()?->name,
                'status' => $t->status ?? 'open',
                'workspace' => $t->workspace?->name ?? 'Workspace',
            ]);

        $chats = $user->conversations()
            ->with(['participants:id,name', 'messages' => function ($q) {
                $q->latest()->limit(1);
            }])
            ->where(function ($q) use ($term) {
                $q->where('conversations.name', 'like', "%{$term}%")
                    ->orWhereHas('participants', fn($sub) => $sub->where('users.name', 'like', "%{$term}%"))
                    ->orWhereHas('messages', fn($sub) => $sub->where('body', 'like', "%{$term}%"));
            })
            ->limit(6)
            ->get()
            ->map(function ($conversation) use ($user) {
                $lastMessage = $conversation->messages->first();
                $title = $conversation->type === 'group'
                    ? ($conversation->name ?: 'Group Chat')
                    : $conversation->participants->firstWhere('id', '!=', $user->id)?->name;

                return [
                    'id' => $conversation->id,
                    'title' => $title ?: 'Direct Chat',
                    'participants' => $conversation->participants->pluck('name')->values(),
                    'last_message' => $lastMessage?->body,
                ];
            });

        $meetings = $this->searchMeetings($user, $term);

        return response()->json([
            'users' => $users,
            'tasks' => $tasks,
            'chats' => $chats,
            'meetings' => $meetings,
        ]);
    }

    protected function searchMeetings(User $user, string $term): array
    {
        if (!$user->google_access_token) {
            return [];
        }

        try {
            $client = $this->calendarClient($user);
            if (!$client) {
                return [];
            }
            $service = new Calendar($client);
            $now = Carbon::now()->toRfc3339String();
            $events = $service->events->listEvents('primary', [
                'timeMin' => $now,
                'maxResults' => 50,
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ]);
        } catch (\Throwable $e) {
            return [];
        }

        $needle = strtolower($term);

        return collect($events->getItems())
            ->filter(function ($event) use ($user, $needle) {
                $organizerEmail = optional($event->getOrganizer())->getEmail() ?? optional($event->getCreator())->getEmail();
                if (!$organizerEmail || !$user->email) {
                    return false;
                }
                if (strtolower($organizerEmail) !== strtolower($user->email)) {
                    return false;
                }
                $summary = $event->getSummary() ?? '';
                $description = $event->getDescription() ?? '';
                $location = $event->getLocation() ?? '';
                $haystack = strtolower($summary.' '.$description.' '.$location);
                return $needle === '' || strpos($haystack, $needle) !== false;
            })
            ->take(6)
            ->map(function ($event) {
                $startRaw = optional($event->getStart())->getDateTime() ?? optional($event->getStart())->getDate();
                $endRaw = optional($event->getEnd())->getDateTime() ?? optional($event->getEnd())->getDate();
                $start = $startRaw ? Carbon::parse($startRaw)->setTimezone('Asia/Kolkata') : null;
                $end = $endRaw ? Carbon::parse($endRaw)->setTimezone('Asia/Kolkata') : null;

                return [
                    'id' => $event->getId(),
                    'title' => $event->getSummary() ?: 'Untitled event',
                    'start' => $start?->format('d M, h:i A'),
                    'end' => $end?->format('d M, h:i A'),
                ];
            })
            ->values()
            ->all();
    }

    protected function calendarClient(User $user): ?GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.calendar_redirect') ?? config('services.google.redirect'));
        $client->setScopes(['https://www.googleapis.com/auth/calendar.events']);
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
    }
}
