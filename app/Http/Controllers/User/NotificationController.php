<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Auth;

class NotificationController extends Controller
{
    /** Danh sách thông báo gần đây + số chưa đọc. */
    public function index()
    {
        $userId = Auth::id();

        $items = Notification::where('user_id', $userId)
            ->latest()
            ->limit(30)
            ->get(['id', 'message', 'url', 'is_read', 'created_at'])
            ->map(fn ($n) => [
                'id' => $n->id,
                'message' => $n->message,
                'url' => $n->url,
                'is_read' => (bool) $n->is_read,
                'time_ago' => $n->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'success' => true,
            'unread' => Notification::where('user_id', $userId)->where('is_read', false)->count(),
            'notifications' => $items,
        ]);
    }

    public function markRead(Notification $notification)
    {
        abort_if($notification->user_id !== Auth::id(), 403);
        $notification->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function markAllRead()
    {
        Notification::where('user_id', Auth::id())->where('is_read', false)->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }
}
