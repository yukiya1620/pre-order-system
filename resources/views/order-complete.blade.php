@extends('layouts.app')

@section('title', '注文完了 | 予約注文システム')

@section('content')
    <div class="page order-complete-page" data-order-url="{{ url('/api/v1/orders/'.$orderId) }}">
        <p id="order-complete-loading">読み込み中です…</p>

        <p id="order-complete-message" class="message message-error" hidden></p>

        <div id="order-complete-content" hidden>
            <p class="order-complete__icon" aria-hidden="true">✔</p>
            <h1 class="order-complete__heading">ご注文ありがとうございました</h1>

            <dl class="order-complete__summary">
                <dt>注文番号</dt>
                <dd id="complete-order-number"></dd>

                <dt>配達予定</dt>
                <dd id="complete-delivery-date"></dd>
            </dl>

            <a href="{{ route('buyer.home') }}" class="order-complete__back-link">商品一覧へ戻る ▶</a>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/order-complete.js') }}" defer></script>
@endpush
