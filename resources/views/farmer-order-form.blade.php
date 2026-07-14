@extends('layouts.app')

@section('title', '電話注文の代理入力 | 予約注文システム')

@section('content')
    <div class="page order-form-page"
         data-products-url="{{ url('/api/v1/products') }}"
         data-orders-api-url="{{ url('/api/v1/farmer/orders') }}"
         data-order-detail-base-url="{{ url('/farmer/orders') }}"
         data-orders-page-url="{{ route('farmer.orders') }}">
        <header class="order-form-page__header">
            <a href="{{ route('farmer.orders') }}" class="order-form-page__back-link">◀ 予約一覧へ戻る</a>
            <h1 class="order-form-page__title">電話注文の代理入力</h1>
        </header>

        <p id="order-form-loading">商品情報を読み込んでいます…</p>

        <p id="order-form-message" class="message message-error" hidden></p>

        <form id="order-form" hidden>
            <div class="field">
                <label for="phone-number">購入者の電話番号</label>
                <input type="text" id="phone-number" name="phone_number" required maxlength="20">
                <p class="field-hint">既存の購入者の電話番号ならそのまま注文できます。初めての方は送信後にお名前・ご住所の入力をお願いします。</p>
                <p id="phone_number-error" class="field-error" hidden></p>
            </div>

            <div id="registration-fields" hidden>
                <p class="order-form__registration-note">初めての購入者です。お名前とご住所を入力してください。</p>

                <div class="field">
                    <label for="buyer-name">お名前</label>
                    <input type="text" id="buyer-name" name="name" maxlength="100">
                    <p id="name-error" class="field-error" hidden></p>
                </div>

                <div class="field">
                    <label for="buyer-address">ご住所</label>
                    <textarea id="buyer-address" name="address" maxlength="255"></textarea>
                    <p id="address-error" class="field-error" hidden></p>
                </div>
            </div>

            <div class="field">
                <label for="product-sale">商品</label>
                <select id="product-sale" name="product_sale_id" required></select>
                <p id="product_sale_id-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="quantity">数量</label>
                <input type="number" id="quantity" name="quantity" required min="1" value="1">
                <p id="quantity-error" class="field-error" hidden></p>
            </div>

            <p id="order-form-estimate" class="order-form__estimate" hidden></p>

            <div class="field">
                <label for="delivery-time-slot">配達時間帯(任意)</label>
                <select id="delivery-time-slot" name="delivery_time_slot">
                    <option value="">指定なし</option>
                    <option value="午前">午前</option>
                    <option value="午後">午後</option>
                </select>
            </div>

            <div class="field">
                <label for="payment-method">支払い方法(任意)</label>
                <select id="payment-method" name="payment_method">
                    <option value="">未選択</option>
                    <option value="cash">現金</option>
                    <option value="card">カード</option>
                    <option value="paypay">PayPay</option>
                </select>
            </div>

            <div class="field">
                <label for="payment-status">支払い状況(任意)</label>
                <select id="payment-status" name="payment_status">
                    <option value="unpaid" selected>未払い</option>
                    <option value="paid">支払い済み</option>
                </select>
            </div>

            <div class="field">
                <label for="proxy-note">代理入力メモ(任意)</label>
                <input type="text" id="proxy-note" name="proxy_note" maxlength="255" placeholder="例: 7/15 電話にて受付">
                <p id="proxy_note-error" class="field-error" hidden></p>
            </div>

            <button type="submit" id="order-form-submit-button">この内容で注文する</button>
        </form>

        <div id="order-form-success" class="order-form__success" hidden>
            <p class="message message-success">注文を受け付けました。</p>
            <p>注文番号: <span id="success-order-number"></span></p>
            <p>配達予定日: <span id="success-delivery-date">確認しています…</span></p>

            <div class="order-form__success-links">
                <a href="{{ route('farmer.orders') }}">予約一覧へ</a>
                <a id="success-order-detail-link" href="#">この注文の詳細を見る ▶</a>
            </div>

            <button type="button" id="order-form-continue-button">続けて別の電話注文を入力する</button>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/farmer-order-form.js') }}" defer></script>
@endpush
