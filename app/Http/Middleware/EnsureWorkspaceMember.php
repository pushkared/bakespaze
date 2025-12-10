<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceMember
{
    /**
     * Ensure the authenticated user belongs to the requested workspace.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $workspaceParam = $request->route('workspace');
        $workspaceId = null;

        if ($workspaceParam instanceof Workspace) {
            $workspaceId = $workspaceParam->id;
        } elseif (is_numeric($workspaceParam)) {
            $workspaceId = (int) $workspaceParam;
        }

        if (!$workspaceId) {
            return response()->json(['message' => 'Workspace not specified'], 400);
        }

        $isMember = Membership::where('workspace_id', $workspaceId)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
