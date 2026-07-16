(function () {
    var container = document.querySelector('.order-detail-page');
    if (!container) {
        return;
    }

    var orderUrl = container.dataset.orderUrl;
    var reorderPreviewUrl = container.dataset.reorderPreviewUrl;
    var orderConfirmBaseUrl = container.dataset.orderConfirmBaseUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    var loadingEl = document.getElementById('order-detail-loading');
    var generalMessageEl = document.getElementById('order-detail-message');
    var contentEl = document.getElementById('order-detail-content');
    var reorderButton = document.getElementById('order-detail-reorder-button');
    var reorderMessageEl = document.getElementById('order-detail-reorder-message');

    var statusBadgeClasses = {
        '受付済': 'order-status-badge--received',
        '配達確認済': 'order-status-badge--confirmed',
        '配達日変更': 'order-status-badge--changed',
        '配達完了': 'order-status-badge--delivered',
        'キャンセル': 'order-status-badge--cancelled'
    };

    // 設計書5.1の「色+アイコン+文字」の3点セットに合わせ、状態バッジに絵文字を添える
    var statusIcons = {
        '受付済': '📝',
        '配達確認済': '✅',
        '配達日変更': '📅',
        '配達完了': '📦',
        'キャンセル': '✕'
    };

    function showGeneralMessage(text) {
        generalMessageEl.textContent = text;
        generalMessageEl.hidden = false;
    }

    function formatDate(dateString) {
        var parts = dateString.split('-');
        return Number(parts[1]) + '月' + Number(parts[2]) + '日';
    }

    function renderStatusBadge(status) {
        var badge = document.getElementById('detail-status-badge');
        Object.keys(statusBadgeClasses).forEach(function (key) {
            badge.classList.remove(statusBadgeClasses[key]);
        });
        badge.classList.add(statusBadgeClasses[status] || '');
        badge.textContent = '';
        if (statusIcons[status]) {
            var iconEl = document.createElement('span');
            iconEl.setAttribute('aria-hidden', 'true');
            iconEl.textContent = statusIcons[status];
            badge.appendChild(iconEl);
            badge.appendChild(document.createTextNode(' '));
        }
        badge.appendChild(document.createTextNode(status));
    }

    function renderItems(items) {
        var body = document.getElementById('detail-items-body');
        body.innerHTML = '';

        (items || []).forEach(function (item) {
            var row = document.createElement('tr');

            var nameCell = document.createElement('td');
            nameCell.textContent = item.product_name;
            row.appendChild(nameCell);

            var quantityCell = document.createElement('td');
            quantityCell.textContent = item.quantity + '点';
            row.appendChild(quantityCell);

            var priceCell = document.createElement('td');
            priceCell.textContent = '¥' + Number(item.unit_price).toLocaleString();
            row.appendChild(priceCell);

            var subtotalCell = document.createElement('td');
            subtotalCell.textContent = '¥' + Number(item.subtotal).toLocaleString();
            row.appendChild(subtotalCell);

            body.appendChild(row);
        });
    }

    function renderOrder(order) {
        document.getElementById('detail-order-number').textContent = '注文番号: ' + order.order_number;
        renderStatusBadge(order.status);

        document.getElementById('detail-delivery-date').textContent = formatDate(order.delivery_date);
        document.getElementById('detail-delivery-time-slot').textContent = order.delivery_time_slot || '指定なし';
        document.getElementById('detail-delivery-address').textContent = order.delivery_address;

        renderItems(order.order_items);
        document.getElementById('detail-total-amount').textContent = '¥' + Number(order.total_amount).toLocaleString();

        // キャンセル済みの注文は再注文の対象外にする(F3の「配達完了ボタンを出さない」と同じ考え方)
        if (order.status === 'キャンセル') {
            reorderButton.hidden = true;
        }
    }

    reorderButton.addEventListener('click', function () {
        if (reorderButton.disabled) {
            return;
        }
        reorderButton.disabled = true;
        reorderMessageEl.hidden = true;

        fetch(reorderPreviewUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        }).then(function (response) {
            if (response.status === 401) {
                reorderMessageEl.textContent = 'ログインの有効期限が切れています。もう一度ログインしてください。';
                reorderMessageEl.hidden = false;
                reorderButton.disabled = false;
                return null;
            }
            if (response.status === 422) {
                return response.json().then(function (data) {
                    reorderMessageEl.textContent = (data.error && data.error.message) || 'この商品は現在ご注文いただけません。';
                    reorderMessageEl.hidden = false;
                    reorderButton.disabled = false;
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
            var params = new URLSearchParams();
            params.set('product_sale_id', data.reorder_params.product_sale_id);
            params.set('quantity', data.reorder_params.quantity);
            if (data.reorder_params.delivery_time_slot) {
                params.set('delivery_time_slot', data.reorder_params.delivery_time_slot);
            }
            window.location.href = orderConfirmBaseUrl + '?' + params.toString();
        }).catch(function () {
            reorderMessageEl.textContent = '確認に失敗しました。時間をおいてもう一度お試しください。';
            reorderMessageEl.hidden = false;
            reorderButton.disabled = false;
        });
    });

    function loadOrder() {
        fetch(orderUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (response.status === 401) {
                loadingEl.hidden = true;
                showGeneralMessage('ログインの有効期限が切れています。もう一度ログインしてください。');
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
            renderOrder(data.order);
            contentEl.hidden = false;
        }).catch(function () {
            loadingEl.hidden = true;
            showGeneralMessage('注文詳細の取得に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    loadOrder();
})();
