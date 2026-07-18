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

        <p id="farmer-home-message" class="message message-error" hidden></p>

        <section class="farmer-home__overview" aria-label="今日の概要">
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
            <a href="{{ route('farmer.delivery-confirmations') }}" class="farmer-home__menu-item">
                <span class="farmer-home__menu-item-icon" aria-hidden="true">📋</span>
                <span>注文確認</span>
            </a>

            <a href="{{ route('farmer.products') }}" class="farmer-home__menu-item">
                <span class="farmer-home__menu-item-icon" aria-hidden="true">🥬</span>
                <span>商品管理</span>
            </a>

            <a href="{{ route('farmer.orders') }}" class="farmer-home__menu-item">
                <span class="farmer-home__menu-item-icon" aria-hidden="true">📅</span>
                <span>予約一覧</span>
            </a>

            <a href="{{ route('farmer.sales') }}" class="farmer-home__menu-item">
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
@endpush
