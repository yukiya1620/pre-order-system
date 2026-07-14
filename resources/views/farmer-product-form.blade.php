@extends('layouts.app')

@section('title', ($mode === 'edit' ? '商品編集' : '商品登録').' | 予約注文システム')

@section('content')
    <div class="page product-form-page"
         data-mode="{{ $mode }}"
         data-categories-url="{{ url('/api/v1/farmer/categories') }}"
         data-products-api-url="{{ url('/api/v1/farmer/products') }}"
         data-product-api-url="{{ $productId ? url('/api/v1/farmer/products/'.$productId) : '' }}"
         data-products-page-url="{{ route('farmer.products') }}">
        <header class="product-form-page__header">
            <a href="{{ route('farmer.products') }}" class="product-form-page__back-link">◀ 商品管理へ戻る</a>
            <h1 class="product-form-page__title">{{ $mode === 'edit' ? '商品編集' : '商品登録' }}</h1>
        </header>

        <p id="product-form-loading">読み込み中です…</p>

        <p id="product-form-message" class="message message-error" hidden></p>

        <form id="product-form" hidden>
            <div class="field">
                <label for="name">商品名</label>
                <input type="text" id="name" name="name" required maxlength="100">
                <p id="name-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="description">商品説明</label>
                <textarea id="description" name="description" required></textarea>
                <p id="description-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="category_id">カテゴリー</label>
                <select id="category_id" name="category_id" required></select>
                <p id="category_id-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="unit_label">単位</label>
                <input type="text" id="unit_label" name="unit_label" maxlength="20">
                <p class="field-hint">「袋」「箱」「kg」など。空欄の場合は「個」になります。</p>
                <p id="unit_label-error" class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="image">商品画像</label>
                <div class="product-form__image-preview-wrap">
                    <img id="image-preview" class="product-form__image-preview" alt="商品画像プレビュー" hidden>
                    <div id="image-preview-placeholder" class="product-form__image-preview-placeholder">画像なし</div>
                </div>
                <input type="file" id="image" name="image" accept="image/*" capture="environment">
                <p class="field-hint">未選択の場合、既存の画像はそのまま維持されます。</p>
                <p id="image-error" class="field-error" hidden></p>
            </div>

            <div class="field" id="archived-field" hidden>
                <label class="product-form__checkbox-label">
                    <input type="checkbox" id="is_archived" name="is_archived">
                    この商品を非表示にする
                </label>
            </div>

            <button type="submit" id="submit-button">{{ $mode === 'edit' ? '保存する' : '登録する' }}</button>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/farmer-product-form.js') }}" defer></script>
@endpush
