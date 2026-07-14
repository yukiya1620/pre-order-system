@extends('layouts.app')

@section('title', ($mode === 'register' ? '会員登録' : 'ログイン').' | 予約注文システム')

@section('content')
    <div class="page auth-form-page"
         data-mode="{{ $mode }}"
         data-sms-send-url="{{ url('/api/v1/auth/sms/send') }}"
         data-sms-verify-url="{{ url('/api/v1/auth/sms/verify') }}"
         data-email-login-url="{{ url('/api/v1/auth/login') }}"
         data-buyer-home-url="{{ route('buyer.home') }}"
         data-farmer-home-url="{{ route('farmer.home') }}">
        <header class="auth-form-page__header">
            <h1 class="auth-form-page__title">{{ $mode === 'register' ? '会員登録' : 'ログイン' }}</h1>
        </header>

        <p id="auth-form-message" class="message message-error" hidden></p>

        <div id="auth-step-wizard">
            <div class="auth-step" data-step-name="name" hidden>
                <div class="field">
                    <label for="auth-name">お名前</label>
                    <input type="text" id="auth-name" maxlength="100">
                    <p id="auth-name-error" class="field-error" hidden></p>
                </div>
                <div class="auth-step__actions">
                    <button type="button" class="auth-step__back" data-back hidden>◀ 戻る</button>
                    <button type="button" class="auth-step__next" data-next data-step="name">次へ</button>
                </div>
            </div>

            <div class="auth-step" data-step-name="phone" hidden>
                <div class="field">
                    <label for="auth-phone">電話番号</label>
                    <input type="text" id="auth-phone" inputmode="numeric" maxlength="11" placeholder="09012345678">
                    <p class="field-hint">「0」から始まる10〜11桁の数字を、ハイフンなしで入力してください。</p>
                    <p id="auth-phone-error" class="field-error" hidden></p>
                </div>
                <div class="auth-step__actions">
                    <button type="button" class="auth-step__back" data-back>◀ 戻る</button>
                    <button type="button" class="auth-step__next" data-next data-step="phone">次へ</button>
                </div>
            </div>

            <div class="auth-step" data-step-name="address" hidden>
                <div class="field">
                    <label for="auth-address">ご住所</label>
                    <textarea id="auth-address" maxlength="255"></textarea>
                    <p id="auth-address-error" class="field-error" hidden></p>
                </div>
                <div class="auth-step__actions">
                    <button type="button" class="auth-step__back" data-back>◀ 戻る</button>
                    <button type="button" class="auth-step__next" data-next data-step="address">次へ</button>
                </div>
            </div>

            <div class="auth-step" data-step-name="email" hidden>
                <div class="field">
                    <label for="auth-email">メールアドレス(任意)</label>
                    <input type="email" id="auth-email" maxlength="254">
                    <p id="auth-email-error" class="field-error" hidden></p>
                </div>
                <div class="auth-step__actions">
                    <button type="button" class="auth-step__back" data-back>◀ 戻る</button>
                    <button type="button" class="auth-step__next" data-next data-step="email">次へ</button>
                </div>
            </div>

            <div class="auth-step" data-step-name="code" hidden>
                <p id="auth-code-sent-note" class="auth-form__note"></p>
                <div class="field">
                    <label for="auth-code">認証コード(6桁)</label>
                    <input type="text" id="auth-code" inputmode="numeric" maxlength="6">
                    <p id="auth-code-error" class="field-error" hidden></p>
                </div>
                <button type="button" id="auth-resend-button">コードを再送信する</button>
                <p id="auth-resend-countdown" class="auth-form__countdown" hidden></p>
                <div class="auth-step__actions">
                    <button type="button" class="auth-step__back" data-back>◀ 戻る</button>
                    <button type="button" id="auth-verify-button">認証する</button>
                </div>
            </div>
        </div>

        <p id="auth-switch-to-email" class="auth-form__switch" hidden>
            <button type="button" id="auth-switch-to-email-button">メールアドレス+パスワードでログインする</button>
        </p>

        <form id="email-login-form" hidden>
            <div class="field">
                <label for="login-email">メールアドレス</label>
                <input type="email" id="login-email">
                <p id="email-error" class="field-error" hidden></p>
            </div>
            <div class="field">
                <label for="login-password">パスワード</label>
                <input type="password" id="login-password">
                <p id="password-error" class="field-error" hidden></p>
            </div>
            <button type="submit" id="email-login-submit-button">ログインする</button>
        </form>

        <p id="auth-switch-to-phone" class="auth-form__switch" hidden>
            <button type="button" id="auth-switch-to-phone-button">◀ 電話番号でログインする</button>
        </p>

        @if ($mode === 'register')
            <p class="auth-form__footer-link">すでに登録済みの方は <a href="{{ route('login') }}">こちら(ログイン)</a></p>
        @else
            <p class="auth-form__footer-link">はじめての方は <a href="{{ route('register') }}">こちら(会員登録)</a></p>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/auth-form.js') }}" defer></script>
@endpush
