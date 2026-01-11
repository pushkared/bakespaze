<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function unread(Request $request)
    {
        $user = $request->user();

        $notifications = $user->unreadNotifications()
            ->latest()
            ->limit(30)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->data['type'] ?? '',
                    'title' => $notification->data['title'] ?? 'Notification',
                    'body' => $notification->data['body'] ?? '',
                    'action_url' => $notification->data['action_url'] ?? null,
                    'task_id' => $notification->data['task_id'] ?? null,
                    'workspace_id' => $notification->data['workspace_id'] ?? null,
                    'created_at' => $notification->created_at?->diffForHumans(),
                ];
            });

        return response()->json([
            'count' => $notifications->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->unreadNotifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['status' => 'ok']);
    }
}
