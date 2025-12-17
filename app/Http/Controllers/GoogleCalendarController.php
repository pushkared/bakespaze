<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\User;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleCalendarController extends Controller
{
    protected function currentWorkspace(Request $request): ?Workspace
    {
        $user = $request->user();
        $workspaceId = session('current_workspace_id');

        return Workspace::whereHas('memberships', fn($q) => $q->where('user_id', $user->id))
            ->when($workspaceId, fn($q) => $q->where('id', $workspaceId))
            ->orderBy('name')
            ->first();
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $workspace = $this->currentWorkspace($request);
        $events = collect();
        $users = User::orderBy('name')->get(['id','name','email']);

        if ($user->google_access_token) {
            $events = $this->fetchEvents($user)->take(25);
        }

        $eventData = $events->map(function ($event) {
            $startRaw = optional($event->getStart())->getDateTime() ?? optional($event->getStart())->getDate();
            $endRaw = optional($event->getEnd())->getDateTime() ?? optional($event->getEnd())->getDate();
            $start = $startRaw ? Carbon::parse($startRaw)->setTimezone('Asia/Kolkata') : null;
            $end = $endRaw ? Carbon::parse($endRaw)->setTimezone('Asia/Kolkata') : null;
            $attendees = collect($event->getAttendees() ?? [])->map(fn($a) => $a->getEmail())->filter()->values()->all();
            return [
                'id' => $event->getId(),
                'title' => $event->getSummary() ?: 'Untitled event',
                'start' => $start?->format('Y-m-d\TH:i:sP') ?? $startRaw,
                'end' => $end?->format('Y-m-d\TH:i:sP') ?? $endRaw,
                'hangoutLink' => $event->getHangoutLink(),
                'description' => $event->getDescription(),
                'location' => $event->getLocation(),
                'attendees' => $attendees,
            ];
        });

        return view('calendar.index', [
            'workspace' => $workspace,
            'events' => $eventData,
            'users' => $users,
        ]);
    }

    public function redirect()
    {
        Log::info('Google Calendar redirect start', ['user_id' => auth()->id()]);
        session(['google_oauth_user_id' => auth()->id()]);
        return Socialite::driver('google')
            ->scopes(['https://www.googleapis.com/auth/calendar.events'])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirectUrl(config('services.google.calendar_redirect') ?? config('services.google.redirect'))
            ->redirect();
    }

    public function callback(Request $request)
    {
        try {
            // Stateless to avoid state mismatch in dev tunnels
            $googleUser = Socialite::driver('google')
                ->redirectUrl(config('services.google.calendar_redirect') ?? config('services.google.redirect'))
                ->stateless()
                ->user();
        } catch (\Throwable $e) {
            Log::error('Google Calendar callback error', ['message' => $e->getMessage()]);
            return redirect()->route('calendar.index')->with('status', 'Google sign-in failed: '.$e->getMessage());
        }

        $user = auth()->user() ?: (\App\Models\User::find(session('google_oauth_user_id')));
        if (!$user) {
            Log::warning('Google Calendar callback without user');
            return redirect()->route('login')->with('status', 'Please sign in and reconnect Google Calendar.');
        }

        $tokenArray = $googleUser->accessTokenResponseBody ?? [];
        $accessToken = $tokenArray['access_token'] ?? $googleUser->token;
        $refreshToken = $tokenArray['refresh_token'] ?? $googleUser->refreshToken ?? $user->google_refresh_token;
        $expiresIn = $tokenArray['expires_in'] ?? $googleUser->expiresIn ?? 3500;

        if (!$accessToken) {
            Log::error('Google Calendar missing access token', ['tokenArray' => $tokenArray]);
            return redirect()->route('calendar.index')->with('status', 'Could not retrieve Google token.');
        }

        $user->update([
            'google_access_token' => json_encode([
                'access_token' => $accessToken,
                'expires_in' => $expiresIn,
                'created' => time(),
            ]),
            'google_refresh_token' => $refreshToken,
            'google_token_expires_at' => Carbon::now()->addSeconds($expiresIn),
        ]);

        Log::info('Google Calendar tokens saved', [
            'user_id' => $user->id,
            'has_refresh' => !empty($refreshToken),
            'expires_in' => $expiresIn,
        ]);

        session()->forget('google_oauth_user_id');

        return redirect()->route('calendar.index')->with('status', 'Google Calendar connected.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'start' => ['required','date'],
            'end' => ['required','date','after_or_equal:start'],
            'attendees' => ['nullable','string'], // comma separated emails
            'attendee_ids' => ['array'],
            'attendee_ids.*' => ['exists:users,id'],
            'create_meet' => ['nullable','boolean'],
        ]);

        $user = $request->user();
        $client = $this->googleClient($user);
        $service = new Calendar($client);

        // build attendees list from IDs + comma separated emails
        $attendeeEmails = collect();
        if (!empty($data['attendee_ids'])) {
            $attendeeEmails = $attendeeEmails->merge(
                User::whereIn('id', $data['attendee_ids'])->pluck('email')
            );
        }
        if (!empty($data['attendees'])) {
            $attendeeEmails = $attendeeEmails->merge(
                collect(explode(',', $data['attendees']))->map(fn($e) => trim($e))
            );
        }
        $attendees = $attendeeEmails->filter()->unique()->values()->map(fn($email) => ['email' => $email]);

        $event = new Event([
            'summary' => $data['title'],
            'description' => $data['description'] ?? '',
            'start' => ['dateTime' => Carbon::parse($data['start'])->toIso8601String()],
            'end' => ['dateTime' => Carbon::parse($data['end'])->toIso8601String()],
        ]);

        if ($attendees->count()) {
            $event->setAttendees($attendees->all());
        }

        if (!empty($data['create_meet'])) {
            $event->setConferenceData([
                'createRequest' => [
                    'requestId' => Str::uuid()->toString(),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ]);
        }

        $service->events->insert('primary', $event, ['conferenceDataVersion' => !empty($data['create_meet']) ? 1 : 0]);

        return redirect()->route('calendar.index')->with('status', 'Event created and synced to Google Calendar.');
    }

    protected function googleClient($user): GoogleClient
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

        // Refresh if expired
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

    protected function fetchEvents($user)
    {
        try {
            $client = $this->googleClient($user);
            $service = new Calendar($client);
            $now = Carbon::now()->toRfc3339String();
            $events = $service->events->listEvents('primary', [
                'timeMin' => $now,
                'maxResults' => 50,
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ]);
            return collect($events->getItems());
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
