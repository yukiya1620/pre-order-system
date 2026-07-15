<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class NotificationsController extends Controller
{
    /**
     * 通知一覧(B8)。表示専用画面。buyerミドルウェアにより
     * 購入者としてログイン済みであることが保証されている。
     * 一覧・既読操作は画面側のJavaScriptが既存の
     * GET /api/v1/notifications・PUT /api/v1/notifications/{id}/read・
     * PUT /api/v1/notifications/read-all を呼んで行う。
     */
    public function index(): View
    {
        return view('notifications');
    }
}
