<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 購入者(role=buyer)としてログインしている利用者だけを通す関所。
 * EnsureUserIsFarmerと違い、今回はWeb画面(/)からの利用を想定して
 * JSON 403ではなく適切なページへリダイレクトする。将来、購入者専用APIに
 * 適用する際は $request->expectsJson() 側の分岐がそのまま使える。
 */
class EnsureUserIsBuyer
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

        if ($user->role !== User::ROLE_BUYER) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => '購入者アカウントのみ利用できます。',
                    ],
                ], 403);
            }

            return redirect()->route('farmer.home');
        }

        return $next($request);
    }
}
