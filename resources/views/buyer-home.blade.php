@extends('layouts.app')

@section('title', '購入者トップ | 予約注文システム')

@section('content')
    <div class="page buyer-home-page">
        <header class="buyer-home-page__header">
            <h1 class="buyer-home-page__title">ようこそ</h1>
        </header>

        <p class="buyer-home-page__message">購入者トップ(商品一覧)は準備中です。もうしばらくお待ちください。</p>

        <button type="button" id="buyer-home-logout-button" class="buyer-home-page__logout-button">ログアウト</button>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/buyer-home.js') }}" defer></script>
@endpush
