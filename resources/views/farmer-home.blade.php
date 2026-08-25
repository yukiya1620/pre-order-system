@extends('layouts.app')

@section('title', '農家ホーム | 予約注文システム')

@section('content')
    <div class="farmer-home">
        <header class="farmer-home__header">
            <h1 class="farmer-home__title">農家ホーム</h1>
            <p class="farmer-home__farmer-name">{{ $farmer->name }} さん</p>

            <nav class="farmer-home__header-nav" aria-label="設定・ログアウト">
                <a href="{{ route('settings') }}" class="farmer-home__settings-link">⚙ 設定</a>
                <button type="button" id="farmer-home-logout-button" class="farmer-home__logout-button">ログアウト</button>
            </nav>
        </header>

        {{--
            一般公開デモ専用の「使い方を見る」ボタン。DEMO_MODE=trueのときだけ表示する。
            購入者トップ(buyer-home.blade.php)と同じ考え方で、header直下に
            独立した導線として置く。
        --}}
        @if (config('demo.enabled'))
            <p class="demo-tutorial-start-button-wrap">
                <button type="button" id="farmer-home-tutorial-start-button" class="demo-tutorial-start-button">💡 使い方を見る</button>
            </p>
        @endif

        <p id="farmer-home-message" class="message message-error" hidden></p>

        <section class="farmer-home__overview" aria-label="今日の概要" data-demo-tutorial="farmer-overview">
            <div class="farmer-home__overview-item">
                <span class="farmer-home__overview-label">⚠ 要対応(配達確認)</span>
                <span class="farmer-home__overview-value" id="farmer-home-pending-count">読み込み中…</span>
            </div>
            <div class="farmer-home__overview-item">
                <span class="farmer-home__overview-label">💰 本日の確定売上</span>
                <span class="farmer-home__overview-value" id="farmer-home-today-sales">読み込み中…</span>
            </div>
            <div class="farmer-home__overview-item">
                <a href="{{ route('farmer.orders', ['filter' => 'pending_change_request']) }}" id="farmer-home-change-request-link" class="farmer-home__overview-link">
                    <span class="farmer-home__overview-label">💬 要対応(変更相談)</span>
                    <span class="farmer-home__overview-value" id="farmer-home-change-request-count">読み込み中…</span>
                </a>
            </div>
        </section>

        <nav class="farmer-home__menu" aria-label="業務メニュー">
            <a href="{{ route('farmer.delivery-confirmations') }}" class="farmer-home__menu-item" data-demo-tutorial="farmer-menu-delivery-confirmations">
                <span class="farmer-home__menu-item-icon" aria-hidden="true">📋</span>
                <span>注文確認</span>
            </a>

            <a href="{{ route('farmer.products') }}" class="farmer-home__menu-item" data-demo-tutorial="farmer-menu-products">
                <span class="farmer-home__menu-item-icon" aria-hidden="true">🥬</span>
                <span>商品管理</span>
            </a>

            <a href="{{ route('farmer.orders') }}" class="farmer-home__menu-item">
                <span class="farmer-home__menu-item-icon" aria-hidden="true">📅</span>
                <span>予約一覧</span>
            </a>

            <a href="{{ route('farmer.sales') }}" class="farmer-home__menu-item" data-demo-tutorial="farmer-menu-sales">
                <span class="farmer-home__menu-item-icon" aria-hidden="true">💰</span>
                <span>売上確認</span>
            </a>

            <a href="{{ route('farmer.announcements') }}" class="farmer-home__menu-item">
                <span class="farmer-home__menu-item-icon" aria-hidden="true">📢</span>
                <span>お知らせ</span>
            </a>

            <a href="{{ route('settings') }}" class="farmer-home__menu-item">
                <span class="farmer-home__menu-item-icon" aria-hidden="true">⚙</span>
                <span>設定</span>
            </a>
        </nav>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/farmer-home.js') }}" defer></script>
    {{--
        農家ホームのチュートリアル接続コード(ステップ定義・開始ボタンの制御・
        初回自動開始)。共通基盤(demo-tutorial.js)や概要取得処理(farmer-home.js)
        には手を入れず、この画面専用のファイルとして分離する。
    --}}
    @if (config('demo.enabled'))
        <script src="{{ asset('js/farmer-home-tutorial.js') }}" defer></script>
    @endif
@endpush
