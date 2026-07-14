@extends('layouts.app')

@section('title', '予約一覧 | 予約注文システム')

@section('content')
    <div class="page orders-page" data-orders-url="{{ url('/api/v1/farmer/orders') }}" data-order-detail-base-url="{{ url('/farmer/orders') }}">
        <header class="orders-page__header">
            <a href="{{ route('farmer.home') }}" class="orders-page__back-link">◀ もどる</a>
            <h1 class="orders-page__title">予約一覧</h1>
        </header>

        <div class="orders-page__unimplemented">
            <a href="{{ route('farmer.orders.create') }}" class="orders-page__unimplemented-item">
                ☎ 電話注文
            </a>
        </div>

        <div class="orders-page__filter">
            <label for="orders-status-filter">絞り込み</label>
            <select id="orders-status-filter">
                <option value="active" selected>未完了</option>
                <option value="all">すべて</option>
                <option value="受付済">受付済</option>
                <option value="配達確認済">配達確認済</option>
                <option value="配達日変更">配達日変更</option>
                <option value="配達完了">配達完了</option>
                <option value="キャンセル">キャンセル</option>
            </select>
        </div>

        <p id="orders-loading">読み込み中です…</p>

        <p id="orders-message" class="message message-error" hidden></p>

        <p id="orders-empty" class="orders-empty" hidden>該当する注文はありません。</p>

        <div id="orders-list" class="orders-list"></div>

        <nav class="orders-pager" id="orders-pager" aria-label="ページ送り" hidden>
            <button type="button" id="orders-prev-button">◀ 前へ</button>
            <span id="orders-page-indicator" class="orders-pager__indicator"></span>
            <button type="button" id="orders-next-button">次へ ▶</button>
        </nav>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/farmer-orders.js') }}" defer></script>
@endpush
