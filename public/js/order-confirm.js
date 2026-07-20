(function () {
    var container = document.querySelector('.order-confirm-page');
    if (!container) {
        return;
    }

    var previewUrl = container.dataset.previewUrl;
    var ordersUrl = container.dataset.ordersUrl;
    var orderCompleteBaseUrl = container.dataset.orderCompleteBaseUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    var loadingEl = document.getElementById('order-confirm-loading');
    var messageEl = document.getElementById('order-confirm-message');
    var contentEl = document.getElementById('order-confirm-content');
    var submitButton = document.getElementById('order-confirm-submit-button');

    function showMessage(text) {
        messageEl.textContent = text;
        messageEl.hidden = false;
    }

    function formatDate(dateString) {
        var parts = dateString.split('-');
        return Number(parts[1]) + '月' + Number(parts[2]) + '日';
    }

    /**
     * B4からのクエリ文字列は初期値の受け渡しにのみ使う。価格・在庫・正式な配達予定日は
     * このあとPOST /orders/previewで取得する値を正とする(URLの値をそのまま信用しない)。
     */
    function parseQuery() {
        var params = new URLSearchParams(window.location.search);
        var productSaleId = params.get('product_sale_id');
        var quantity = params.get('quantity');

        if (!productSaleId || !quantity || Number(quantity) < 1) {
            return null;
        }

        return {
            product_sale_id: Number(productSaleId),
            quantity: Number(quantity),
            delivery_time_slot: params.get('delivery_time_slot') || null,
            delivery_date: params.get('delivery_date') || null
        };
    }

    var orderRequest = parseQuery();

    if (!orderRequest) {
        loadingEl.hidden = true;
        showMessage('商品情報が正しく指定されていません。商品ページからやり直してください。');
        return;
    }

    function renderPreview(preview) {
        document.getElementById('confirm-product-name').textContent = preview.product_name;
        document.getElementById('confirm-quantity').textContent = preview.quantity + '点';
        document.getElementById('confirm-amount').textContent =
            '¥' + Number(preview.total_amount).toLocaleString()
            + '(¥' + Number(preview.unit_price).toLocaleString() + ' × ' + preview.quantity + ')';

        document.getElementById('confirm-address').textContent = preview.delivery_address;
        document.getElementById('confirm-time-slot').textContent = preview.delivery_time_slot || '指定なし';
        document.getElementById('confirm-delivery-date').textContent = formatDate(preview.delivery_date);

        var noteEl = document.getElementById('confirm-delivery-note');
        if (preview.delivery_note) {
            noteEl.textContent = '※ ' + preview.delivery_note;
            noteEl.hidden = false;
        } else {
            noteEl.hidden = true;
        }
    }

    function loadPreview() {
        fetch(previewUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(orderRequest)
        }).then(function (response) {
            if (response.status === 401) {
                loadingEl.hidden = true;
                showMessage('ログインの有効期限が切れています。もう一度ログインしてください。');
                return null;
            }
            if (response.status === 422) {
                return response.json().then(function (data) {
                    loadingEl.hidden = true;
                    var text = (data.error && data.error.message)
                        || 'この商品は現在ご注文いただけません。';
                    showMessage(text);
                    return null;
                });
            }
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            if (!data) {
                return;
            }
            loadingEl.hidden = true;
            renderPreview(data.order_preview);
            contentEl.hidden = false;
        }).catch(function () {
            loadingEl.hidden = true;
            showMessage('注文内容の確認に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    submitButton.addEventListener('click', function () {
        if (submitButton.disabled) {
            return;
        }
        submitButton.disabled = true;
        submitButton.textContent = '送信しています…';
        messageEl.hidden = true;

        fetch(ordersUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(orderRequest)
        }).then(function (response) {
            if (response.status === 401) {
                showMessage('ログインの有効期限が切れています。もう一度ログインしてください。');
                return null;
            }
            if (response.status === 422) {
                return response.json().then(function (data) {
                    var text = (data.error && data.error.message)
                        || '注文を確定できませんでした。内容をご確認のうえ、商品ページからやり直してください。';
                    showMessage(text);
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
                submitButton.textContent = '✔ この内容で注文する';
                return;
            }
            // replace()で遷移することで、注文完了後に「戻る」を押してもこの確認画面に
            // 戻らないようにする(二重送信防止)。
            window.location.replace(orderCompleteBaseUrl + '/' + data.order.id + '/complete');
        }).catch(function () {
            showMessage('送信に失敗しました。時間をおいてもう一度お試しください。');
            submitButton.disabled = false;
            submitButton.textContent = '✔ この内容で注文する';
        });
    });

    loadPreview();
})();
