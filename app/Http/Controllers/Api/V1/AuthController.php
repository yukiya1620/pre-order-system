<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * メールアドレス+パスワードでログインする(主に農家アカウント向け)
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => 'メールアドレスまたはパスワードが正しくありません。',
                ],
            ], 422);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => Auth::user(),
        ]);
    }

    /**
     * ログアウト。ブラウザのセッション認証とBearerトークン認証の両方に対応する。
     *
     * auth:sanctumミドルウェアが解決する「sanctum」ガードはRequestGuardという薄いラッパーで
     * logout()を持たないため、そのまま Auth::logout() は呼べない。
     * セッション認証時は currentAccessToken() が実体を持たない TransientToken を返し、
     * Bearerトークン認証時は実際にDBへ保存された PersonalAccessToken を返す。
     * この違いで、どちらの方式で認証されたかを安全に判定する。
     */
    public function logout(Request $request): JsonResponse
    {
        $token = Auth::user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            // Bearerトークン認証: 今回使われたトークンだけを失効させる(他のトークンやセッションには触れない)
            $token->delete();
        } else {
            // セッション認証: 実体を持つwebガードでログアウトし、セッション自体を無効化する
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'ログアウトしました。',
        ]);
    }
}
