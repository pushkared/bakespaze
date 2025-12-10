<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use App\Models\Workspace;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS for URL generation when app URL is HTTPS (helps behind proxies/tunnels)
        $appUrl = config('app.url');
        if (is_string($appUrl) && str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }

        // Share workspace/user data for admin/manager dropdown in layout
        View::composer('layouts.app', function ($view) {
            if (!auth()->check()) {
                return;
            }
            $user = auth()->user();
            $isManager = in_array($user->role ?? '', ['admin', 'manager']);
            $workspaces = collect();
            $workspaceUsers = collect();
            if ($isManager) {
                $workspaces = Workspace::orderBy('name')->get(['id','name','slug']);
                $workspaceUsers = User::orderBy('name')->get(['id','name','email']);
            }
            $view->with(compact('workspaces', 'workspaceUsers', 'isManager'));
        });
    }
}
