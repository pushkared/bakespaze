<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use App\Models\Workspace;
use App\Models\User;
use App\Models\Task;

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

        // Share workspace/user data for layout dropdowns
        View::composer('layouts.app', function ($view) {
            if (!auth()->check()) {
                return;
            }
            $user = auth()->user();
            $availableWorkspaces = Workspace::whereHas('memberships', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->orderBy('name')->get(['id','name','slug']);

            $currentWorkspace = $availableWorkspaces->firstWhere('id', session('current_workspace_id'))
                ?? $availableWorkspaces->first();

            $workspaceUsers = User::orderBy('name')->get(['id','name','email']);

            $panelTasks = Task::with('assignees')
                ->where(function ($q) use ($user) {
                    $q->where('creator_id', $user->id)
                      ->orWhereHas('assignees', fn($a) => $a->where('users.id', $user->id));
                })
                ->when($currentWorkspace, fn($q) => $q->where('workspace_id', $currentWorkspace->id))
                ->orderByRaw('ISNULL(due_date), due_date asc')
                ->limit(8)
                ->get();

            $view->with([
                'availableWorkspaces' => $availableWorkspaces,
                'currentWorkspace' => $currentWorkspace,
                'workspaceUsers' => $workspaceUsers,
                'panelTasks' => $panelTasks,
            ]);
        });
    }
}
