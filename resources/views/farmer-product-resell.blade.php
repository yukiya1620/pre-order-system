@extends('layouts.app')

@section('title', '再販売設定 | 予約注文システム')

@section('content')
    <div class="page product-resell-page"
         data-product-api-url="{{ url('/api/v1/farmer/products/'.$productId) }}"
         data-sales-api-url="{{ url('/api/v1/farmer/products/'.$productId.'/sales') }}"
         data-products-page-url="{{ route('farmer.products') }}">
        <header class="product-resell-page__header">
            <a href="{{ route('farmer.products') }}" class="product-resell-page__back-link">◀ 商品管理へ戻る</a>
            <h1 class="product-resell-page__title">再販売設定</h1>
        </header>

        <p id="product-resell-loading">読み込み中です…</p>

        <p id="product-resell-message" class="message message-error" hidden></p>

        <form id="product-resell-form" hidden>
            <div class="field">
                <label>商品</label>
                <p id="product-resell-name" class="product-resell__product-name"></p>
            </div>

            <p id="product-resell-active-warning" class="message message-error" hidden>
                現在の販売設定があります。新しい販売シーズンを追加します。
            </p>

            <div id="product-resell-previous" class="product-resell__previous" hidden>
                <p class="product-resell__previous-heading">前回の設定(参考)</p>
                <p>価格: <span id="previous-price"></span></p>
                <p>在庫: <span id="previous-stock"></span></p>
                <p>販売期間: <span id="previous-sale-period"></span></p>
                <p>配達予定: <span id="previous-delivery-period"></span></p>
            </div>

            <div class="field">
                <label for="price">価格(円)</label>
                <input type="number" id="price" name="price" required min="0">
                <p id="price-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="stock_quantity">在庫</label>
                <input type="number" id="stock_quantity" name="stock_quantity" required min="0">
                <p id="stock_quantity-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="sale_start_date">販売開始日</label>
                <input type="date" id="sale_start_date" name="sale_start_date" required>
                <p id="sale_start_date-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="sale_end_date">販売終了日</label>
                <input type="date" id="sale_end_date" name="sale_end_date" required>
                <p id="sale_end_date-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="delivery_date_from">配達開始日</label>
                <input type="date" id="delivery_date_from" name="delivery_date_from" required>
                <p id="delivery_date_from-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="delivery_date_to">配達終了日</label>
                <input type="date" id="delivery_date_to" name="delivery_date_to">
                <p class="field-hint">単日の場合は空欄のままにしてください。</p>
                <p id="delivery_date_to-error" class="field-error" hidden></p>
            </div>

            <button type="submit" id="submit-button">この内容で販売を開始</button>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/farmer-product-resell.js') }}" defer></script>
@endpush
