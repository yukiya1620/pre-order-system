@extends('layouts.app')

@section('title', '配達のかくにん | 予約注文システム')

@section('content')
    <div class="page confirmation-page" data-delivery-confirmations-url="{{ url('/api/v1/farmer/delivery-confirmations') }}">
        <header class="confirmation-page__header">
            <a href="{{ route('farmer.home') }}" class="confirmation-page__back-link">◀ もどる</a>
            <h1 class="confirmation-page__title">配達のかくにん</h1>
        </header>

        <p id="confirmation-loading">読み込み中です…</p>

        <p id="confirmation-message" class="message message-error" hidden></p>

        <p id="confirmation-empty" class="confirmation-empty" hidden>現在、確認が必要な注文はありません。</p>

        <div id="confirmation-list" class="confirmation-list"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/farmer-delivery-confirmations.js') }}" defer></script>
@endpush
