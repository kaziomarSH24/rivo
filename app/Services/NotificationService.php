<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationService
{
    /**
     * Get all notifications for a user.
     */
    public function getAllForUser(User $user, int $perPage = 15)
    {
        return $user->notifications()->paginate($perPage);
    }

    /**
     * Get unread notifications count for a user.
     */
    public function getUnreadCountForUser(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }

    /**
     * Mark all unread notifications as read for a user.
     */
    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * Delete a specific notification.
     */
    public function delete(DatabaseNotification $notification): void
    {
        $notification->delete();
    }
}
