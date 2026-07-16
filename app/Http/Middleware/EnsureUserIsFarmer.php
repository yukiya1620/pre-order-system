<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 農家(role=farmer)としてログインしている利用者だけを通す関所。
 * EnsureUserIsBuyerと同じく、画面(Web)からの利用かAPIからの利用かで
 * レスポンス形式を分ける($request->expectsJson()で判定)。
 */
class EnsureUserIsFarmer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => 'ログインが必要です。',
                    ],
                ], 401);
            }

            return redirect()->route('login');
        }

        if ($user->role !== User::ROLE_FARMER) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => '農家アカウントのみ利用できます。',
                    ],
                ], 403);
            }

            return redirect()->route('buyer.home');
        }

        return $next($request);
    }
}
