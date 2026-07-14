@extends('layouts.app')

@section('title', '商品詳細 | 予約注文システム')

@section('content')
    <div class="page product-detail-page" data-product-url="{{ url('/api/v1/products/'.$productSaleId) }}">
        <header class="product-detail-page__header">
            <a href="{{ route('buyer.home') }}" class="product-detail-page__back-link">◀ 商品一覧へ戻る</a>
        </header>

        <p id="product-detail-loading">読み込み中です…</p>

        <p id="product-detail-message" class="message message-error" hidden></p>

        <div id="product-detail-content" hidden>
            <div id="product-detail-image-wrap" class="product-detail__image-wrap"></div>

            <p id="product-detail-category" class="product-detail__category"></p>
            <h1 id="product-detail-name" class="product-detail__name"></h1>
            <p id="product-detail-price" class="product-detail__price"></p>
            <span id="product-detail-status-badge" class="product-status-badge"></span>
            <p id="product-detail-stock" class="product-detail__stock"></p>
            <p class="product-detail__delivery">🚚 配達予定 <span id="product-detail-delivery-date"></span></p>
            <p id="product-detail-delivery-note" class="product-detail__delivery-note"></p>
            <p id="product-detail-description" class="product-detail__description"></p>

            <div class="field">
                <label for="product-detail-quantity">数量</label>
                <input type="number" id="product-detail-quantity" min="1" value="1">
            </div>

            <div class="field">
                <label for="product-detail-time-slot">配達時間帯(任意)</label>
                <select id="product-detail-time-slot">
                    <option value="">指定なし</option>
                    <option value="午前">午前</option>
                    <option value="午後">午後</option>
                </select>
            </div>

            @guest
                <p class="product-detail__login-prompt">
                    ご注文にはログインが必要です。<a href="{{ route('login') }}">ログインする ▶</a>
                </p>
            @else
                <button type="button" id="product-detail-order-button" class="product-detail__order-button" disabled>この内容で注文する</button>
                <p class="product-detail__preparing-note">※ 注文確認画面は準備中です。もうしばらくお待ちください。</p>
            @endguest
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/product-detail.js') }}" defer></script>
@endpush
