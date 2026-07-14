@extends('layouts.app')

@section('title', '商品管理 | 予約注文システム')

@section('content')
    <div class="page products-page" data-products-url="{{ url('/api/v1/farmer/products') }}">
        <header class="products-page__header">
            <a href="{{ route('farmer.home') }}" class="products-page__back-link">◀ もどる</a>
            <h1 class="products-page__title">商品管理</h1>
        </header>

        <div class="products-page__unimplemented">
            <div class="orders-page__unimplemented-item" aria-disabled="true">
                ＋ 新しい商品を登録 <span class="farmer-home__menu-item-badge">準備中</span>
            </div>
        </div>

        <div class="products-page__filter">
            <label for="products-status-filter">絞り込み</label>
            <select id="products-status-filter">
                <option value="all" selected>すべて</option>
                <option value="visible">表示中</option>
                <option value="archived">非表示</option>
            </select>
        </div>

        <p id="products-loading">読み込み中です…</p>

        <p id="products-message" class="message message-error" hidden></p>

        <p id="products-empty" class="products-empty" hidden>該当する商品はありません。</p>

        <div id="products-list" class="products-list"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/farmer-products.js') }}" defer></script>
@endpush
