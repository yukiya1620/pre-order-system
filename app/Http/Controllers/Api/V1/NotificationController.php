<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * 自分宛ての通知一覧(新しい順)
     */
    public function index(): JsonResponse
    {
        $notifications = Auth::user()->notifications()->latest()->get();

        return response()->json(['notifications' => $notifications]);
    }

    /**
     * 1件だけ既読にする
     */
    public function markAsRead(Notification $notification): JsonResponse
    {
        if ($notification->user_id !== Auth::id()) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'この通知は操作できません。',
                ],
            ], 403);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['notification' => $notification]);
    }

    /**
     * 自分宛ての未読通知をすべて既読にする
     */
    public function markAllAsRead(): JsonResponse
    {
        Auth::user()->notifications()->where('is_read', false)->update(['is_read' => true]);

        return response()->json(['message' => 'すべての通知を既読にしました。']);
    }
}
