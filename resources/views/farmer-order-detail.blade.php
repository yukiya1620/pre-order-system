@extends('layouts.app')

@section('title', '注文詳細 | 予約注文システム')

@section('content')
    <div class="page order-detail-page" data-order-url="{{ url('/api/v1/farmer/orders/'.$orderId) }}">
        <header class="order-detail-page__header">
            <a href="{{ route('farmer.orders') }}" class="order-detail-page__back-link">◀ 予約一覧へ戻る</a>
            <h1 class="order-detail-page__title">注文詳細</h1>
        </header>

        <p id="order-detail-loading">読み込み中です…</p>

        <p id="order-detail-message" class="message message-error" hidden></p>

        <div id="order-detail-content" class="order-detail" hidden>
            <p class="order-detail__heading-line">
                <span id="detail-order-number" class="order-detail__order-number"></span>
                <span id="detail-status-badge" class="order-card__status-badge"></span>
                <span id="detail-proxy-badge" class="order-detail__proxy-badge" hidden>☎ 電話注文</span>
            </p>

            <section class="order-detail__section">
                <h2>配達情報</h2>
                <p>配達予定日: <span id="detail-delivery-date"></span></p>
                <p>配達時間帯: <span id="detail-delivery-time-slot"></span></p>
                <p>配達先: <span id="detail-delivery-address"></span></p>
            </section>

            <section class="order-detail__section">
                <h2>購入者情報</h2>
                <p>お名前: <span id="detail-buyer-name"></span> 様</p>
                <p>電話番号: <span id="detail-buyer-phone"></span></p>
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

            <section class="order-detail__section">
                <h2>支払い情報</h2>
                <p>支払い方法: <span id="detail-payment-method"></span></p>
                <p>支払い状況: <span id="detail-payment-status"></span></p>
            </section>

            <section id="detail-proxy-section" class="order-detail__section" hidden>
                <h2>代理注文メモ</h2>
                <p id="detail-proxy-note"></p>
            </section>

            <section class="order-detail__section">
                <h2>配達確認</h2>
                <p id="detail-confirmation-status"></p>
                <div id="detail-confirmation-details" hidden>
                    <p>回答内容: <span id="detail-confirmation-response"></span></p>
                    <p id="detail-confirmation-new-date-row">新しい配達予定日: <span id="detail-confirmation-new-date"></span></p>
                    <p id="detail-confirmation-note-row">回答メモ: <span id="detail-confirmation-note"></span></p>
                    <p>回答日時: <span id="detail-confirmation-responded-at"></span></p>
                </div>
            </section>

            <section id="detail-change-request-section" class="order-detail__section" hidden>
                <h2>購入者からのご相談</h2>

                <p id="detail-change-request-message" class="message message-error" role="alert" aria-live="assertive" hidden></p>
                <p id="detail-change-request-success" class="message message-success" role="status" aria-live="polite" hidden></p>

                <p>相談種別: <span id="detail-change-request-type"></span></p>
                <p id="detail-change-request-summary"></p>
                <p>相談日時: <span id="detail-change-request-created-at"></span></p>

                <div class="field">
                    <label for="detail-change-request-note">メモ(任意)</label>
                    <textarea id="detail-change-request-note" maxlength="255"></textarea>
                </div>

                <button type="button" id="detail-resolve-without-change-button">変更せず相談を終了する</button>
            </section>

            <section id="detail-actions-section" class="order-detail__section" hidden>
                <h2>注文の変更</h2>
                <p class="field-hint">購入者へ電話等で確認したうえで、変更を確定してください。</p>

                <p id="detail-action-message" class="message message-error" hidden></p>

                <div class="field" id="detail-reduce-quantity-field" hidden>
                    <label for="detail-new-quantity">新しい数量</label>
                    <input type="number" id="detail-new-quantity" min="1">
                </div>

                <div class="field">
                    <label for="detail-adjustment-note">メモ(任意)</label>
                    <textarea id="detail-adjustment-note" maxlength="255"></textarea>
                </div>

                <div class="field">
                    <label class="order-detail__checkbox-label">
                        <input type="checkbox" id="detail-confirmed-with-buyer">
                        購入者へ電話等で確認済みです
                    </label>
                </div>

                <button type="button" id="detail-reduce-quantity-button" hidden>この数量に変更する</button>
                <button type="button" id="detail-cancel-order-button" class="order-detail__cancel-button">注文をキャンセルする</button>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/farmer-order-detail.js') }}" defer></script>
@endpush
