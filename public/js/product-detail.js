(function () {
    var container = document.querySelector('.product-detail-page');
    if (!container) {
        return;
    }

    var productUrl = container.dataset.productUrl;
    var orderConfirmBaseUrl = container.dataset.orderConfirmBaseUrl;
    var loadingEl = document.getElementById('product-detail-loading');
    var messageEl = document.getElementById('product-detail-message');
    var contentEl = document.getElementById('product-detail-content');
    var quantityInput = document.getElementById('product-detail-quantity');
    var timeSlotSelect = document.getElementById('product-detail-time-slot');

    function showMessage(text) {
        messageEl.textContent = text;
        messageEl.hidden = false;
    }

    function formatDate(dateString) {
        var parts = dateString.split('-');
        return Number(parts[1]) + '月' + Number(parts[2]) + '日';
    }

    /**
     * 画像が無い、または読み込みに失敗した場合は必ずプレースホルダーを表示する(B3・F5と同じ考え方)。
     */
    function renderImage(product) {
        var wrap = document.getElementById('product-detail-image-wrap');
        wrap.innerHTML = '';

        function showPlaceholder() {
            wrap.innerHTML = '';
            var placeholder = document.createElement('div');
            placeholder.className = 'product-detail__image-placeholder';
            placeholder.textContent = '画像なし';
            wrap.appendChild(placeholder);
        }

        if (!product.image) {
            showPlaceholder();
            return;
        }

        var img = document.createElement('img');
        img.className = 'product-detail__image';
        img.src = '/storage/' + product.image;
        img.alt = product.name;
        img.addEventListener('error', showPlaceholder);
        wrap.appendChild(img);
    }

    /**
     * 売り切れ・予約受付停止・予約受付中の3状態(B3と同じ判定ロジック)。
     */
    function statusInfo(sale) {
        if (sale.status === '売り切れ') {
            return { label: '✕ 売り切れ', cls: 'product-status-badge--sold-out', orderable: false };
        }
        if (!sale.is_reservation_open) {
            return { label: '⏸ 予約受付停止', cls: 'product-status-badge--closed', orderable: false };
        }
        return { label: '🟢 予約受付中', cls: 'product-status-badge--open', orderable: true };
    }

    function renderProduct(sale) {
        renderImage(sale.product);

        document.getElementById('product-detail-category').textContent = sale.product.category ? sale.product.category.name : '';
        document.getElementById('product-detail-name').textContent = sale.product.name;
        document.getElementById('product-detail-price').textContent = '¥' + Number(sale.price).toLocaleString();

        var status = statusInfo(sale);
        var badge = document.getElementById('product-detail-status-badge');
        badge.textContent = status.label;
        badge.classList.add(status.cls);

        var stockEl = document.getElementById('product-detail-stock');
        if (status.orderable) {
            stockEl.textContent = '残り' + sale.stock_quantity + (sale.product.unit_label || '個');
            stockEl.hidden = false;
        } else {
            stockEl.hidden = true;
        }

        document.getElementById('product-detail-delivery-date').textContent = formatDate(sale.delivery_date);

        var noteEl = document.getElementById('product-detail-delivery-note');
        if (sale.delivery_note) {
            noteEl.textContent = '※ ' + sale.delivery_note;
            noteEl.hidden = false;
        } else {
            noteEl.hidden = true;
        }

        document.getElementById('product-detail-description').textContent = sale.product.description || '';

        // 在庫数を超える数量を選べないよう上限を設定する(0以下にはならない)
        quantityInput.max = String(Math.max(sale.stock_quantity, 1));

        var orderButton = document.getElementById('product-detail-order-button');
        if (orderButton) {
            if (!status.orderable) {
                orderButton.disabled = true;
            } else {
                orderButton.addEventListener('click', function () {
                    if (orderButton.disabled) {
                        return;
                    }

                    // 価格・合計金額・商品名・delivery_dateはURLに含めない。
                    // B5側でPOST /orders/previewを呼び、そのレスポンスを正として表示する。
                    var params = new URLSearchParams();
                    params.set('product_sale_id', sale.id);
                    params.set('quantity', quantityInput.value || '1');
                    if (timeSlotSelect.value) {
                        params.set('delivery_time_slot', timeSlotSelect.value);
                    }

                    window.location.href = orderConfirmBaseUrl + '?' + params.toString();
                });
            }
        }
    }

    function loadProduct() {
        fetch(productUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (response.status === 404) {
                loadingEl.hidden = true;
                showMessage('指定された商品が見つかりませんでした。');
                return null;
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
            renderProduct(data.product);
            contentEl.hidden = false;
        }).catch(function () {
            loadingEl.hidden = true;
            showMessage('商品情報の取得に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    loadProduct();
})();
