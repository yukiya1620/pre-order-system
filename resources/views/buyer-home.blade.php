@extends('layouts.app')

@section('title', '商品一覧 | 予約注文システム')

@section('content')
    <div class="page buyer-home-page"
         data-products-url="{{ url('/api/v1/products') }}"
         data-announcements-url="{{ url('/api/v1/announcements') }}"
         data-product-detail-base-url="{{ url('/products') }}">
        <header class="buyer-home-page__header">
            <h1 class="buyer-home-page__title">予約注文システム</h1>

            <nav class="buyer-home-page__header-nav" aria-label="アカウント">
                @auth
                    <span class="buyer-home-page__user-name">{{ Auth::user()->name }} 様</span>
                    <a href="{{ route('orders.index') }}" class="buyer-home-page__nav-link">📦 注文履歴</a>
                    <a href="{{ route('notifications.index') }}" class="buyer-home-page__nav-link">🔔 通知</a>
                    <a href="{{ route('settings') }}" class="buyer-home-page__settings-link">⚙ 設定</a>
                    <button type="button" id="buyer-home-logout-button" class="buyer-home-page__logout-button">ログアウト</button>
                @else
                    <a href="{{ route('login') }}" class="buyer-home-page__login-link">ログイン</a>
                    <a href="{{ route('register') }}" class="buyer-home-page__register-link">会員登録</a>
                @endauth
            </nav>
        </header>

        {{--
            一般公開デモ専用の「使い方を見る」ボタン。DEMO_MODE=trueのときだけ表示する。
            アカウント操作(ログイン・ログアウト等)のnavとは意味が異なるため、
            header内のnavには混ぜず、header直下に独立した導線として置く。
            商品一覧・お知らせより前にあるので、PC・スマホどちらでも
            ページを開いてすぐ目に入り、かつ商品閲覧そのものは邪魔しない位置。
        --}}
        @if (config('demo.enabled'))
            <p class="demo-tutorial-start-button-wrap">
                <button type="button" id="buyer-home-tutorial-start-button" class="demo-tutorial-start-button">💡 使い方を見る</button>
            </p>
        @endif

        <p id="buyer-home-message" class="message message-error" hidden></p>

        <section class="buyer-home-page__announcements" aria-label="お知らせ">
            <h2 class="buyer-home-page__section-heading">📢 お知らせ</h2>
            <p id="announcements-loading">読み込み中です…</p>
            <p id="announcements-empty" class="buyer-home-page__empty" hidden>お知らせはまだありません。</p>
            <ul id="announcements-list" class="announcements-summary-list"></ul>
        </section>

        <nav id="category-tabs" class="buyer-home-page__category-tabs" aria-label="カテゴリー絞り込み" hidden></nav>

        <p id="products-loading">商品情報を読み込んでいます…</p>
        <p id="products-empty" class="buyer-home-page__empty" hidden>現在お求めいただける商品はありません。</p>
        <div id="products-list" class="products-list"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/buyer-home.js') }}" defer></script>
    {{--
        商品一覧のチュートリアル接続コード(ステップ定義・開始ボタンの制御)。
        共通基盤(demo-tutorial.js)や商品取得処理(buyer-home.js)には手を
        入れず、この画面専用のファイルとして分離する。
    --}}
    @if (config('demo.enabled'))
        <script src="{{ asset('js/buyer-home-tutorial.js') }}" defer></script>
    @endif
@endpush
