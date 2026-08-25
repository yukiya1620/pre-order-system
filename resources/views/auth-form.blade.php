@extends('layouts.app')

@section('title', ($mode === 'register' ? '会員登録' : 'ログイン').' | 予約注文システム')

@section('content')
    <div class="page auth-form-page"
         data-mode="{{ $mode }}"
         data-sms-send-url="{{ url('/api/v1/auth/sms/send') }}"
         data-sms-verify-url="{{ url('/api/v1/auth/sms/verify') }}"
         data-email-login-url="{{ url('/api/v1/auth/login') }}"
         data-buyer-home-url="{{ route('buyer.home') }}"
         data-farmer-home-url="{{ route('farmer.home') }}"
         {{--
             一般公開デモ専用: DEMO_MODE=true かつ config('demo.tutorial_buyer_phone')が
             設定されている場合だけ、案内用の電話番号をdata属性経由でJSへ渡す。
             値そのものをBlade・JSにハードコードせず、config経由の値をそのまま
             出力するだけに留める。
         --}}
         @if (config('demo.enabled') && config('demo.tutorial_buyer_phone'))
             data-demo-tutorial-buyer-phone="{{ config('demo.tutorial_buyer_phone') }}"
         @endif
    >
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
                <div class="field" data-demo-tutorial="buyer-login-phone">
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

            {{--
                data-demo-tutorial="buyer-login-code-actions" は、チュートリアルの
                「認証コード」ステップ専用の目印。認証コード入力欄だけでなく
                「認証する」ボタンまで含めてスポットライトすることで、
                このステップ中でも実際に認証コードを入力して認証を完了できる
                状態のまま案内する(第3-C: 非最終ページのローカル最終ステップでは
                次へボタンを表示せず、実操作で次へ進む設計としたための対応)。
            --}}
            <div class="auth-step" data-step-name="code" hidden data-demo-tutorial="buyer-login-code-actions">
                <p id="auth-code-sent-note" class="auth-form__note"></p>
                <div class="field" data-demo-tutorial="buyer-login-code">
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

        {{--
            一般公開デモ専用: DEMO_MODE=true かつ販売者デモ用のメール・パスワードが
            両方設定されている場合だけ、「販売者デモを試す方」向けの案内を表示する。
            この農家アカウントは一般公開デモ専用であり、DEMO_MODE=trueの画面で
            利用者へ案内すること自体が想定された用途(本番アカウントの資格情報とは無関係)。
            認証処理そのもの(メール+パスワードログインAPI)は変更せず、
            「メール+パスワード方式への切り替え」と「入力欄への値のセット」だけを
            補助する。実際にログインボタンを押すのは利用者本人(ワンクリック認証
            バイパスは行わない)。mode==='login'のときだけ表示する
            (会員登録画面には電話番号→メール切り替え自体が存在しないため)。
            値そのものをBlade・JSにハードコードせず、config経由の値をそのまま
            data属性として出力するだけに留める。
        --}}
        @if ($mode === 'login' && config('demo.enabled') && config('demo.tutorial_farmer_email') && config('demo.tutorial_farmer_password'))
            <div class="auth-form__farmer-demo-hint"
                 data-demo-tutorial-farmer-email="{{ config('demo.tutorial_farmer_email') }}"
                 data-demo-tutorial-farmer-password="{{ config('demo.tutorial_farmer_password') }}">
                <p class="auth-form__farmer-demo-hint-text">販売者(農家)デモを試す方は、こちらでログイン情報を入力できます。</p>
                <button type="button" id="farmer-demo-fill-button">📋 販売者デモのログイン情報を入力</button>
            </div>
        @endif

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
    {{--
        ログイン画面のチュートリアル接続コード(電話番号・認証コード入力欄の
        案内、購入者チュートリアルの引き継ぎ)。共通基盤(demo-tutorial.js)や
        認証処理(auth-form.js)には手を入れず、この画面専用のファイルとして分離する。
    --}}
    @if (config('demo.enabled'))
        <script src="{{ asset('js/auth-form-tutorial.js') }}" defer></script>
    @endif
@endpush
