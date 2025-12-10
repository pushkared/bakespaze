<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLogout
{
    /**
     * Log out inactive users after a configured timeout (minutes).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timeoutMinutes = (int) config('session.auto_logout_minutes', 30);

        if ($timeoutMinutes > 0 && Auth::check()) {
            $lastActive = $request->session()->get('last_active_at');
            $now = now();

            if ($lastActive && $now->diffInMinutes($lastActive) >= $timeoutMinutes) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('status', 'You were logged out due to inactivity.');
            }

            $request->session()->put('last_active_at', $now);
        }

        return $next($request);
    }
}
