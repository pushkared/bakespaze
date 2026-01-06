<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')
            ->scopes(['email', 'profile'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            return redirect('/login')->withErrors([
                'google' => 'Login failed, please try again.',
            ]);
        }

        $user = User::updateOrCreate(
            ['google_id' => $googleUser->getId()],
            [
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'User',
                'email' => $googleUser->getEmail(),
                'provider' => 'google',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        if (!$user->avatar_url && $googleUser->getAvatar()) {
            $user->avatar_url = $googleUser->getAvatar();
            $user->save();
        }

        // Set a default role if missing (first user becomes admin, others employee)
        if (!$user->role) {
            $user->role = User::count() === 1 ? 'admin' : 'employee';
            $user->save();
        }

        // Do not auto-assign new users to a workspace.

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
