<?php

namespace App\Http\Controllers\Api\V1\Farmer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farmer\StoreAnnouncementRequest;
use App\Http\Requests\Farmer\UpdateAnnouncementRequest;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends Controller
{
    /**
     * お知らせ管理用一覧。編集・削除の対象を選ぶためのものなので、
     * 購入者向け一覧と違い公開・非公開の両方を表示する。
     */
    public function index(): JsonResponse
    {
        $announcements = Announcement::orderByDesc('published_at')->paginate(20);

        return response()->json(['announcements' => $announcements]);
    }

    /**
     * お知らせ投稿。published_atを省略した場合は投稿した瞬間を公開日時にする。
     */
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['published_at'] = $data['published_at'] ?? now();

        $announcement = Announcement::create($data);

        return response()->json(['announcement' => $announcement], 201);
    }

    /**
     * お知らせ編集。非公開→公開に切り替える際、published_atが指定されていなければ
     * 「今、公開した」ことにする(一覧の新しい順表示に反映されるように)。
     */
    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $data = $request->validated();

        if (($data['is_published'] ?? null) === true
            && ! $announcement->is_published
            && ! array_key_exists('published_at', $data)) {
            $data['published_at'] = now();
        }

        $announcement->update($data);

        return response()->json(['announcement' => $announcement->fresh()]);
    }

    /**
     * お知らせ削除
     */
    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json(['message' => 'お知らせを削除しました。']);
    }
}
