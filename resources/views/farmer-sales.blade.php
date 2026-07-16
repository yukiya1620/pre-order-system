@extends('layouts.app')

@section('title', '売上確認 | 予約注文システム')

@section('content')
    <div class="page sales-page"
         data-sales-summary-url="{{ url('/api/v1/farmer/sales-summary') }}"
         data-sales-by-product-url="{{ url('/api/v1/farmer/sales-by-product') }}">
        <header class="sales-page__header">
            <a href="{{ route('farmer.home') }}" class="sales-page__back-link">◀ ホームへ戻る</a>
            <h1 class="sales-page__title">売上確認</h1>
        </header>

        <section class="sales-page__section">
            <p id="sales-summary-loading">読み込み中です…</p>
            <p id="sales-summary-message" class="message message-error" hidden></p>

            <div id="sales-summary-content" hidden>
                <h2>確定売上</h2>
                <p class="sales-page__note">確定売上には、未払い・返金済みの注文も含まれます(支払い状況にかかわらず、配達完了した注文はすべて計上されます)。</p>

                <div class="sales-cards">
                    <div class="sales-card">
                        <p class="sales-card__label">本日</p>
                        <p class="sales-card__amount" id="confirmed-today-amount"></p>
                        <p class="sales-card__count" id="confirmed-today-count"></p>
                    </div>
                    <div class="sales-card">
                        <p class="sales-card__label">今月</p>
                        <p class="sales-card__amount" id="confirmed-month-amount"></p>
                        <p class="sales-card__count" id="confirmed-month-count"></p>
                    </div>
                    <div class="sales-card">
                        <p class="sales-card__label">今年</p>
                        <p class="sales-card__amount" id="confirmed-year-amount"></p>
                        <p class="sales-card__count" id="confirmed-year-count"></p>
                    </div>
                </div>

                <h2>予定売上(全期間)</h2>
                <p class="sales-page__note">未配達でキャンセルされていない注文の合計です。</p>
                <div class="sales-card sales-card--pending">
                    <p class="sales-card__amount" id="pending-amount"></p>
                    <p class="sales-card__count" id="pending-count"></p>
                </div>

                <h2>支払い状況の内訳(確定売上)</h2>
                <table class="sales-breakdown-table">
                    <thead>
                        <tr><th scope="col"></th><th scope="col">金額</th><th scope="col">件数</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th scope="row">支払い済み</th>
                            <td id="payment-status-paid-amount"></td>
                            <td id="payment-status-paid-count"></td>
                        </tr>
                        <tr>
                            <th scope="row">未払い</th>
                            <td id="payment-status-unpaid-amount"></td>
                            <td id="payment-status-unpaid-count"></td>
                        </tr>
                        <tr>
                            <th scope="row">返金済み</th>
                            <td id="payment-status-refunded-amount"></td>
                            <td id="payment-status-refunded-count"></td>
                        </tr>
                    </tbody>
                </table>

                <h2>支払い方法の内訳(確定売上)</h2>
                <table class="sales-breakdown-table">
                    <thead>
                        <tr><th scope="col"></th><th scope="col">金額</th><th scope="col">件数</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th scope="row">現金</th>
                            <td id="payment-method-cash-amount"></td>
                            <td id="payment-method-cash-count"></td>
                        </tr>
                        <tr>
                            <th scope="row">カード</th>
                            <td id="payment-method-card-amount"></td>
                            <td id="payment-method-card-count"></td>
                        </tr>
                        <tr>
                            <th scope="row">PayPay</th>
                            <td id="payment-method-paypay-amount"></td>
                            <td id="payment-method-paypay-count"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="sales-page__section">
            <h2>商品別売上</h2>

            <div class="sales-page__period-buttons">
                <button type="button" class="sales-period-button" data-period="today" aria-pressed="false">今日</button>
                <button type="button" class="sales-period-button sales-period-button--active" data-period="month" aria-pressed="true">今月</button>
                <button type="button" class="sales-period-button" data-period="year" aria-pressed="false">今年</button>
            </div>

            <p id="sales-by-product-loading">読み込み中です…</p>
            <p id="sales-by-product-message" class="message message-error" hidden></p>
            <p id="sales-by-product-empty" hidden>この期間の商品別売上はありません。</p>

            <table class="sales-by-product-table" id="sales-by-product-table" hidden>
                <thead>
                    <tr><th scope="col">商品名</th><th scope="col">販売数量</th><th scope="col">確定売上金額</th></tr>
                </thead>
                <tbody id="sales-by-product-body"></tbody>
            </table>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/farmer-sales.js') }}" defer></script>
@endpush
