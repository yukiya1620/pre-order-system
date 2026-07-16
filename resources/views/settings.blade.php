@extends('layouts.app')

@section('title', '設定 | 予約注文システム')

@section('content')
    <div class="page">
        <header class="settings-page__header">
            <a href="{{ Auth::user()->role === 'farmer' ? route('farmer.home') : route('buyer.home') }}" class="settings-page__back-link">◀ もどる</a>
            <h1>設定</h1>
        </header>

        <p id="loading-indicator">読み込み中です…</p>

        <p id="general-message" class="message message-error" hidden></p>

        <form id="settings-form" data-users-me-url="{{ url('/api/v1/users/me') }}" hidden>
            <div class="field">
                <label for="name">お名前</label>
                <input type="text" id="name" name="name" required maxlength="100">
                <p id="name-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="email">メールアドレス</label>
                <input type="email" id="email" name="email" maxlength="254">
                <p class="field-hint">任意です。空のままでもかまいません。</p>
                <p id="email-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="address">ご住所</label>
                <input type="text" id="address" name="address" required maxlength="255">
                <p id="address-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <p class="field-label">電話番号</p>
                <span id="phone_number" class="readonly-value"></span>
                <p class="field-hint">電話番号はここでは変更できません。</p>
            </div>

            <div class="field">
                <p class="field-label">ご利用区分</p>
                <span id="role" class="readonly-value"></span>
            </div>

            <div class="field">
                <label class="settings-page__checkbox-label">
                    <input type="checkbox" id="notify_by_email" name="notify_by_email">
                    メールで通知を受け取る
                </label>
                <p class="field-hint">利用するにはメールアドレスの登録が必要です。</p>
                <p id="notify_by_email-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <label class="settings-page__checkbox-label">
                    <input type="checkbox" id="notify_by_sms" name="notify_by_sms">
                    SMSで通知を受け取る
                </label>
                <p id="notify_by_sms-error" class="field-error" hidden></p>
            </div>

            <button type="submit" id="save-button">保存する</button>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/settings.js') }}" defer></script>
@endpush
