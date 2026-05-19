<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdminNotificationController extends Controller
{
    /**
     * Get all notifications for the logged-in admin.
     */
    public function index()
    {
        /** @var User|null $admin */
        $admin = Auth::user();

        if (!$admin) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'unread_count' => $admin->unreadNotifications()->count(), // Changed to builder count for extreme speed
            'notifications' => $admin->notifications()->paginate(10),
        ]);
    }

    /**
     * Mark a specific notification as read when clicked.
     */
    public function markAsRead($id)
    {
        /** @var User|null $admin */
        $admin = Auth::user();

        if (!$admin) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $notification = $admin->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read.']);
    }

    
}
