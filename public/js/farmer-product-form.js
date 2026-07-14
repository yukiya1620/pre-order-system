(function () {
    var container = document.querySelector('.product-form-page');
    if (!container) {
        return;
    }

    var mode = container.dataset.mode;
    var categoriesUrl = container.dataset.categoriesUrl;
    var productsApiUrl = container.dataset.productsApiUrl;
    var productApiUrl = container.dataset.productApiUrl;
    var productsPageUrl = container.dataset.productsPageUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    var loadingEl = document.getElementById('product-form-loading');
    var generalMessageEl = document.getElementById('product-form-message');
    var form = document.getElementById('product-form');
    var nameInput = document.getElementById('name');
    var descriptionInput = document.getElementById('description');
    var categorySelect = document.getElementById('category_id');
    var unitLabelInput = document.getElementById('unit_label');
    var imageInput = document.getElementById('image');
    var imagePreview = document.getElementById('image-preview');
    var imagePreviewPlaceholder = document.getElementById('image-preview-placeholder');
    var archivedField = document.getElementById('archived-field');
    var archivedCheckbox = document.getElementById('is_archived');
    var submitButton = document.getElementById('submit-button');

    var fieldNames = ['name', 'description', 'category_id', 'unit_label', 'image'];

    function showGeneralMessage(text) {
        generalMessageEl.textContent = text;
        generalMessageEl.hidden = false;
    }

    function clearFieldErrors() {
        fieldNames.forEach(function (field) {
            var el = document.getElementById(field + '-error');
            el.textContent = '';
            el.hidden = true;
        });
    }

    function showFieldErrors(errors) {
        clearFieldErrors();
        Object.keys(errors).forEach(function (field) {
            var el = document.getElementById(field + '-error');
            if (el) {
                el.textContent = errors[field][0];
                el.hidden = false;
            }
        });
    }

    function showImagePreview(src) {
        imagePreview.src = src;
        imagePreview.hidden = false;
        imagePreviewPlaceholder.hidden = true;
    }

    function showImagePlaceholder() {
        imagePreview.hidden = true;
        imagePreview.removeAttribute('src');
        imagePreviewPlaceholder.hidden = false;
    }

    imagePreview.addEventListener('error', showImagePlaceholder);

    imageInput.addEventListener('change', function () {
        var file = imageInput.files[0];
        if (!file) {
            return;
        }
        showImagePreview(URL.createObjectURL(file));
    });

    function loadCategories() {
        return fetch(categoriesUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            (data.categories || []).forEach(function (category) {
                var option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.name;
                categorySelect.appendChild(option);
            });
        });
    }

    function loadProductForEdit() {
        return fetch(productApiUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            var product = data.product;
            nameInput.value = product.name;
            descriptionInput.value = product.description;
            categorySelect.value = String(product.category_id);
            unitLabelInput.value = product.unit_label;
            archivedCheckbox.checked = !!product.is_archived;

            if (product.image) {
                showImagePreview('/storage/' + product.image);
            } else {
                showImagePlaceholder();
            }
        });
    }

    function init() {
        if (mode === 'edit') {
            archivedField.hidden = false;
            Promise.all([loadCategories(), loadProductForEdit()]).then(function () {
                loadingEl.hidden = true;
                form.hidden = false;
            }).catch(function () {
                loadingEl.hidden = true;
                showGeneralMessage('商品情報の取得に失敗しました。時間をおいてもう一度お試しください。');
            });
        } else {
            unitLabelInput.value = '個';
            showImagePlaceholder();
            loadCategories().then(function () {
                loadingEl.hidden = true;
                form.hidden = false;
            }).catch(function () {
                loadingEl.hidden = true;
                showGeneralMessage('カテゴリーの取得に失敗しました。時間をおいてもう一度お試しください。');
            });
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (submitButton.disabled) {
            return;
        }
        submitButton.disabled = true;
        submitButton.textContent = '送信しています…';
        generalMessageEl.hidden = true;
        clearFieldErrors();

        var formData = new FormData();
        formData.append('name', nameInput.value);
        formData.append('description', descriptionInput.value);
        formData.append('category_id', categorySelect.value);
        formData.append('unit_label', unitLabelInput.value);

        if (imageInput.files[0]) {
            formData.append('image', imageInput.files[0]);
        }

        var url = productsApiUrl;
        if (mode === 'edit') {
            formData.append('is_archived', archivedCheckbox.checked ? '1' : '0');
            formData.append('_method', 'PUT');
            url = productApiUrl;
        }

        // multipart/form-dataのContent-Type(境界文字列を含む)はブラウザに自動生成させる必要があるため、
        // ここで手動設定してはいけない。
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        }).then(function (response) {
            if (response.status === 401) {
                showGeneralMessage('ログインの有効期限が切れています。もう一度ログインしてください。');
                return null;
            }
            if (response.status === 422) {
                return response.json().then(function (data) {
                    showFieldErrors(data.errors || {});
                    return null;
                });
            }
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            if (!data) {
                submitButton.disabled = false;
                submitButton.textContent = mode === 'edit' ? '保存する' : '登録する';
                return;
            }
            window.location.href = productsPageUrl;
        }).catch(function () {
            showGeneralMessage('送信に失敗しました。時間をおいてもう一度お試しください。');
            submitButton.disabled = false;
            submitButton.textContent = mode === 'edit' ? '保存する' : '登録する';
        });
    });

    init();
})();
