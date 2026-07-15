@extends('layouts.app')

@section('title', '通知 | 予約注文システム')

@section('content')
    <div class="page notifications-page"
         data-notifications-url="{{ url('/api/v1/notifications') }}"
         data-mark-all-read-url="{{ url('/api/v1/notifications/read-all') }}"
         data-order-detail-base-url="{{ url('/orders') }}">
        <header class="notifications-page__header">
            <a href="{{ route('buyer.home') }}" class="notifications-page__back-link">◀ もどる</a>
            <h1 class="notifications-page__title">通知</h1>
        </header>

        <button type="button" id="notifications-mark-all-read-button" class="notifications-page__mark-all-button">すべて既読にする</button>

        <p id="notifications-loading">読み込み中です…</p>

        <p id="notifications-message" class="message message-error" hidden></p>

        <p id="notifications-empty" class="notifications-empty" hidden>通知はまだありません。</p>

        <div id="notifications-list" class="notifications-list"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/notifications.js') }}" defer></script>
@endpush
