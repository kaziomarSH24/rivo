<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        $this->authorizeResource(DatabaseNotification::class, 'notification');
    }

    public function index()
    {
        $notifications = $this->notificationService->getAllForUser(Auth::user(), request('per_page', 15));
        return NotificationResource::collection($notifications);
    }

    public function stats()
    {
        return response()->json([
            'ok' => true,
            'unread_count' => $this->notificationService->getUnreadCountForUser(Auth::user()),
        ]);
    }

    public function markAsRead(DatabaseNotification $notification)
    {
        $this->notificationService->markAsRead($notification);
        return response_success('Notification marked as read.');
    }

    public function markAllAsRead()
    {
        $this->notificationService->markAllAsRead(Auth::user());
        return response_success('All unread notifications marked as read.');
    }

    public function destroy(DatabaseNotification $notification)
    {
        $this->notificationService->delete($notification);
        return response_success('Notification deleted successfully.');
    }
}
