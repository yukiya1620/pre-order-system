@extends('layouts.app')

@section('title', 'お知らせ投稿 | 予約注文システム')

@section('content')
    <div class="page announcements-page" data-announcements-url="{{ url('/api/v1/farmer/announcements') }}">
        <header class="announcements-page__header">
            <a href="{{ route('farmer.home') }}" class="announcements-page__back-link">◀ もどる</a>
            <h1 class="announcements-page__title">お知らせ投稿</h1>
        </header>

        <p id="announcement-form-message" class="message message-error" hidden></p>

        <form id="announcement-form">
            <input type="hidden" id="announcement-id" value="">

            <div class="announcements-page__preset-buttons" id="announcement-preset-buttons" aria-label="定型文"></div>

            <div class="field">
                <label for="announcement-title">タイトル</label>
                <input type="text" id="announcement-title" name="title" required maxlength="100">
                <p id="title-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="announcement-body">本文(任意)</label>
                <textarea id="announcement-body" name="body"></textarea>
                <p id="body-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <label class="announcements-page__checkbox-label">
                    <input type="checkbox" id="announcement-is-published" checked>
                    このお知らせを公開する
                </label>
            </div>

            <button type="submit" id="announcement-submit-button">投稿する</button>
            <button type="button" id="announcement-cancel-edit-button" hidden>編集をやめる</button>
        </form>

        <h2 class="announcements-page__list-heading">過去のお知らせ</h2>

        <p id="announcements-loading">読み込み中です…</p>

        <p id="announcements-message" class="message message-error" hidden></p>

        <p id="announcements-empty" class="announcements-empty" hidden>お知らせはまだありません。</p>

        <div id="announcements-list" class="announcements-list"></div>

        <nav class="announcements-pager" id="announcements-pager" aria-label="ページ送り" hidden>
            <button type="button" id="announcements-prev-button">◀ 前へ</button>
            <span id="announcements-page-indicator" class="announcements-pager__indicator"></span>
            <button type="button" id="announcements-next-button">次へ ▶</button>
        </nav>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/farmer-announcements.js') }}" defer></script>
@endpush
