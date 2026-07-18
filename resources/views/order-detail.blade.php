@extends('layouts.app')

@section('title', '注文詳細 | 予約注文システム')

@section('content')
    <div class="page order-detail-page"
         data-order-url="{{ url('/api/v1/orders/'.$orderId) }}"
         data-reorder-preview-url="{{ url('/api/v1/orders/'.$orderId.'/reorder-preview') }}"
         data-order-confirm-base-url="{{ url('/orders/confirm') }}">
        <header class="order-detail-page__header">
            <a href="{{ route('orders.index') }}" class="order-detail-page__back-link">◀ 注文履歴へ戻る</a>
            <h1 class="order-detail-page__title">注文詳細</h1>
        </header>

        <p id="order-detail-loading">読み込み中です…</p>

        <p id="order-detail-message" class="message message-error" hidden></p>

        <div id="order-detail-content" hidden>
            <p class="order-detail__heading-line">
                <span id="detail-order-number" class="order-detail__order-number"></span>
                <span id="detail-status-badge" class="order-card__status-badge"></span>
            </p>

            <section class="order-detail__section">
                <h2>配達情報</h2>
                <p>配達予定日: <span id="detail-delivery-date"></span></p>
                <p>配達時間帯: <span id="detail-delivery-time-slot"></span></p>
                <p>配達先: <span id="detail-delivery-address"></span></p>
            </section>

            <section class="order-detail__section">
                <h2>商品明細</h2>
                <table class="order-detail__items-table">
                    <thead>
                        <tr>
                            <th scope="col">商品名</th>
                            <th scope="col">数量</th>
                            <th scope="col">単価</th>
                            <th scope="col">小計</th>
                        </tr>
                    </thead>
                    <tbody id="detail-items-body"></tbody>
                </table>
                <p class="order-detail__total">合計金額: <span id="detail-total-amount"></span></p>
            </section>

            <section id="detail-change-request-section" class="order-detail__section" hidden>
                <h2>ご相談</h2>

                <p id="detail-change-request-message" class="message message-error" role="alert" aria-live="assertive" hidden></p>
                <p id="detail-change-request-success" class="message message-success" role="status" aria-live="polite" hidden></p>

                <div id="detail-pending-request-info" hidden>
                    <p id="detail-pending-request-heading"></p>
                    <p id="detail-pending-request-quantity-detail" hidden></p>
                    <p id="detail-pending-request-created-at"></p>
                    <p>農家からの連絡をお待ちください。</p>
                </div>

                <div id="detail-change-request-buttons">
                    <button type="button" id="detail-request-quantity-change-button" class="order-detail__reorder-button" hidden>数量変更を相談する</button>
                    <button type="button" id="detail-request-cancellation-button" class="order-detail__cancel-button">キャンセルを相談する</button>
                </div>

                <div id="detail-quantity-change-form" hidden>
                    <div class="field">
                        <label for="detail-requested-quantity">希望する数量</label>
                        <input type="number" id="detail-requested-quantity" min="1">
                    </div>
                    <button type="button" id="detail-submit-quantity-change-button" class="order-detail__reorder-button">相談を送信する</button>
                    <button type="button" id="detail-cancel-quantity-change-form-button">戻る</button>
                </div>
            </section>

            <p id="order-detail-reorder-message" class="message message-error" hidden></p>
            <button type="button" id="order-detail-reorder-button" class="order-detail__reorder-button">前回と同じ内容で注文</button>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/order-detail.js') }}" defer></script>
@endpush
