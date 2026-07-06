<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 農家(role=farmer)としてログインしている利用者だけを通す関所
 */
class EnsureUserIsFarmer
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== User::ROLE_FARMER) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => '農家アカウントのみ利用できます。',
                ],
            ], 403);
        }

        return $next($request);
    }
}
