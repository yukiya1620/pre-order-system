<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class FarmerAnnouncementsController extends Controller
{
    /**
     * お知らせ投稿(F9)。表示専用画面。投稿・一覧・編集・削除は画面側のJavaScriptが
     * 既存の GET/POST/PUT/DELETE /api/v1/farmer/announcements を呼んで行う。
     */
    public function index(): View
    {
        return view('farmer-announcements');
    }
}
