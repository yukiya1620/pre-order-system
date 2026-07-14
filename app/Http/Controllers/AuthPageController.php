<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AuthPageController extends Controller
{
    /**
     * 会員登録(B1)。名前→電話番号→住所→メール(任意)→SMSコード確認の
     * ステップ形式。ログイン(B2)と同じBlade/JSを共有し、data-mode="register"で切り替える。
     */
    public function register(): View|RedirectResponse
    {
        return $this->redirectIfAuthenticated() ?? view('auth-form', ['mode' => 'register']);
    }

    /**
     * ログイン(B2)。電話番号+SMS認証を基本とし、画面内のリンクで
     * メール+パスワードログインに切り替えられる。
     */
    public function login(): View|RedirectResponse
    {
        return $this->redirectIfAuthenticated() ?? view('auth-form', ['mode' => 'login']);
    }

    /**
     * すでにログイン中の利用者が/register・/loginへ来た場合、
     * ロールに応じた本来の行き先へ振り分ける。
     */
    private function redirectIfAuthenticated(): ?RedirectResponse
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        return redirect($user->role === User::ROLE_FARMER ? route('farmer.home') : route('buyer.home'));
    }
}
