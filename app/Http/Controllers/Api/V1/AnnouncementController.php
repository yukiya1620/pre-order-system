<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends Controller
{
    /**
     * 公開中のお知らせ一覧(購入者トップページ表示用、公開日時の新しい順)
     */
    public function index(): JsonResponse
    {
        $announcements = Announcement::where('is_published', true)
            ->orderByDesc('published_at')
            ->get();

        return response()->json(['announcements' => $announcements]);
    }
}
