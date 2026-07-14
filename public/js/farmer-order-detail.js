(function () {
    var container = document.querySelector('.order-detail-page');
    if (!container) {
        return;
    }

    var orderUrl = container.dataset.orderUrl;
    var loadingEl = document.getElementById('order-detail-loading');
    var generalMessageEl = document.getElementById('order-detail-message');
    var contentEl = document.getElementById('order-detail-content');

    var statusBadgeClasses = {
        '受付済': 'order-status-badge--received',
        '配達確認済': 'order-status-badge--confirmed',
        '配達日変更': 'order-status-badge--changed',
        '配達完了': 'order-status-badge--delivered',
        'キャンセル': 'order-status-badge--cancelled'
    };

    var paymentMethodLabels = {
        cash: '現金',
        card: 'カード',
        paypay: 'PayPay'
    };

    var paymentStatusLabels = {
        unpaid: '未払い',
        paid: '支払い済み',
        refunded: '返金済み'
    };

    // F2(注文確認画面)と同じ「回答内容→分かりやすい日本語」の変換
    var responseLabels = {
        '配達可能': '予定どおり配達できる',
        '配達日変更': '配達日を変更する',
        '数量変更': '数量を変更する',
        'キャンセル相談': 'キャンセルの相談をする'
    };

    function showGeneralMessage(text) {
        generalMessageEl.textContent = text;
        generalMessageEl.hidden = false;
    }

    function formatDate(dateString) {
        var date = new Date(dateString);
        return (date.getMonth() + 1) + '月' + date.getDate() + '日';
    }

    function formatDateTime(dateString) {
        var date = new Date(dateString);
        return (date.getMonth() + 1) + '月' + date.getDate() + '日 '
            + String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
    }

    function renderStatusBadge(status) {
        var badge = document.getElementById('detail-status-badge');
        Object.keys(statusBadgeClasses).forEach(function (key) {
            badge.classList.remove(statusBadgeClasses[key]);
        });
        badge.classList.add(statusBadgeClasses[status] || '');
        badge.textContent = status;
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
            quantityCell.textContent = item.quantity + '袋';
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

    /**
     * 配達確認は「未生成(対象外)」「確認中(農家からの回答待ち)」「回答済み」の3状態を区別する。
     */
    function renderDeliveryConfirmation(confirmation) {
        var statusEl = document.getElementById('detail-confirmation-status');
        var detailsEl = document.getElementById('detail-confirmation-details');

        if (!confirmation) {
            statusEl.textContent = '配達確認の対象外、または未生成です。';
            detailsEl.hidden = true;
            return;
        }

        if (!confirmation.responded_at) {
            statusEl.textContent = '確認中(農家からの回答待ちです。注文確認画面から回答してください)';
            detailsEl.hidden = true;
            return;
        }

        statusEl.textContent = '回答済みです。';
        detailsEl.hidden = false;

        document.getElementById('detail-confirmation-response').textContent =
            responseLabels[confirmation.response] || confirmation.response;

        var newDateRow = document.getElementById('detail-confirmation-new-date-row');
        if (confirmation.new_delivery_date) {
            document.getElementById('detail-confirmation-new-date').textContent = formatDate(confirmation.new_delivery_date);
            newDateRow.hidden = false;
        } else {
            newDateRow.hidden = true;
        }

        var noteRow = document.getElementById('detail-confirmation-note-row');
        if (confirmation.response_note) {
            document.getElementById('detail-confirmation-note').textContent = confirmation.response_note;
            noteRow.hidden = false;
        } else {
            noteRow.hidden = true;
        }

        document.getElementById('detail-confirmation-responded-at').textContent = formatDateTime(confirmation.responded_at);
    }

    function renderOrder(order) {
        document.getElementById('detail-order-number').textContent = '注文番号: ' + order.order_number;
        renderStatusBadge(order.status);
        document.getElementById('detail-proxy-badge').hidden = !order.is_proxy_order;

        document.getElementById('detail-delivery-date').textContent = formatDate(order.delivery_date);
        document.getElementById('detail-delivery-time-slot').textContent = order.delivery_time_slot || '指定なし';
        document.getElementById('detail-delivery-address').textContent = order.delivery_address;

        document.getElementById('detail-buyer-name').textContent = order.user.name;
        document.getElementById('detail-buyer-phone').textContent = order.user.phone_number;

        renderItems(order.order_items);
        document.getElementById('detail-total-amount').textContent = '¥' + Number(order.total_amount).toLocaleString();

        document.getElementById('detail-payment-method').textContent = paymentMethodLabels[order.payment_method] || order.payment_method;
        document.getElementById('detail-payment-status').textContent = paymentStatusLabels[order.payment_status] || order.payment_status;

        var proxySection = document.getElementById('detail-proxy-section');
        if (order.is_proxy_order && order.proxy_note) {
            document.getElementById('detail-proxy-note').textContent = order.proxy_note;
            proxySection.hidden = false;
        } else {
            proxySection.hidden = true;
        }

        renderDeliveryConfirmation(order.delivery_confirmation);
    }

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
