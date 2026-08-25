@extends('layouts.app')

@section('title', '注文内容のかくにん | 予約注文システム')

@php
    $backProductSaleId = request()->query('product_sale_id');
@endphp

@section('content')
    <div class="page order-confirm-page"
         data-preview-url="{{ url('/api/v1/orders/preview') }}"
         data-orders-url="{{ url('/api/v1/orders') }}"
         data-order-complete-base-url="{{ url('/orders') }}">
        <header class="order-confirm-page__header">
            <a href="{{ $backProductSaleId ? route('products.show', $backProductSaleId) : route('buyer.home') }}" class="order-confirm-page__back-link">◀ もどる</a>
            <h1 class="order-confirm-page__title">注文内容のかくにん</h1>
        </header>

        <p class="order-confirm-page__reassurance">まだ注文は確定していません</p>

        <p id="order-confirm-loading">読み込み中です…</p>

        <p id="order-confirm-message" class="message message-error" hidden></p>

        <div id="order-confirm-content" hidden data-demo-tutorial="buyer-order-summary">
            <dl class="order-confirm__summary">
                <dt>商品</dt>
                <dd id="confirm-product-name"></dd>

                <dt>数量</dt>
                <dd id="confirm-quantity"></dd>

                <dt>金額</dt>
                <dd id="confirm-amount" class="order-confirm__amount"></dd>
            </dl>

            <hr>

            <dl class="order-confirm__summary">
                <dt>お届け先</dt>
                <dd id="confirm-address"></dd>

                <dt>配達時間帯</dt>
                <dd id="confirm-time-slot"></dd>

                <dt>配達予定</dt>
                <dd id="confirm-delivery-date"></dd>
            </dl>
            <p id="confirm-delivery-note" class="order-confirm__delivery-note"></p>

            <hr>

            <button type="button" id="order-confirm-submit-button" class="order-confirm__submit-button" data-demo-tutorial="buyer-order-submit">✔ この内容で注文する</button>
            <a href="{{ $backProductSaleId ? route('products.show', $backProductSaleId) : route('buyer.home') }}" class="order-confirm__cancel-link">✕ やめる</a>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/order-confirm.js') }}" defer></script>
    {{--
        注文確認画面のチュートリアル接続コード。共通基盤(demo-tutorial.js)や
        注文確認・注文確定処理(order-confirm.js)には手を入れず、この画面
        専用のファイルとして分離する。
    --}}
    @if (config('demo.enabled'))
        <script src="{{ asset('js/order-confirm-tutorial.js') }}" defer></script>
    @endif
@endpush
