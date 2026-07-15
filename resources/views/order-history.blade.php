@extends('layouts.app')

@section('title', '注文履歴 | 予約注文システム')

@section('content')
    <div class="page order-history-page"
         data-orders-url="{{ url('/api/v1/orders') }}"
         data-order-detail-base-url="{{ url('/orders') }}"
         data-order-confirm-base-url="{{ url('/orders/confirm') }}">
        <header class="order-history-page__header">
            <a href="{{ route('buyer.home') }}" class="order-history-page__back-link">◀ もどる</a>
            <h1 class="order-history-page__title">注文履歴</h1>
        </header>

        <div class="order-history-page__filter">
            <label for="history-period-select">期間</label>
            <select id="history-period-select"></select>

            <label for="history-month-select" id="history-month-label" hidden>月</label>
            <select id="history-month-select" hidden></select>
        </div>

        <p id="order-history-loading">読み込み中です…</p>

        <p id="order-history-message" class="message message-error" hidden></p>

        <p id="order-history-empty" class="order-history-empty" hidden>該当する注文はありません。</p>

        <div id="order-history-list" class="order-history-list"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/order-history.js') }}" defer></script>
@endpush
