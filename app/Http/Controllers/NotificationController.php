<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function unread(Request $request)
    {
        $user = $request->user();

        $unreadCount = $user->unreadNotifications()->count();
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
                    'conversation_id' => $notification->data['conversation_id'] ?? null,
                    'leave_id' => $notification->data['leave_id'] ?? null,
                    'employee_id' => $notification->data['employee_id'] ?? null,
                    'created_at' => $notification->created_at?->diffForHumans(),
                ];
            });

        return response()->json([
            'count' => $notifications->count(),
            'unread_count' => $unreadCount,
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

    public function clearAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['status' => 'ok']);
    }
}
