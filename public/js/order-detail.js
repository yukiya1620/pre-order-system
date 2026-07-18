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

    var changeRequestSectionEl = document.getElementById('detail-change-request-section');
    var changeRequestMessageEl = document.getElementById('detail-change-request-message');
    var changeRequestSuccessEl = document.getElementById('detail-change-request-success');
    var pendingRequestInfoEl = document.getElementById('detail-pending-request-info');
    var pendingRequestHeadingEl = document.getElementById('detail-pending-request-heading');
    var pendingRequestQuantityDetailEl = document.getElementById('detail-pending-request-quantity-detail');
    var pendingRequestCreatedAtEl = document.getElementById('detail-pending-request-created-at');
    var changeRequestButtonsEl = document.getElementById('detail-change-request-buttons');
    var requestQuantityChangeButton = document.getElementById('detail-request-quantity-change-button');
    var requestCancellationButton = document.getElementById('detail-request-cancellation-button');
    var quantityChangeFormEl = document.getElementById('detail-quantity-change-form');
    var requestedQuantityInput = document.getElementById('detail-requested-quantity');
    var submitQuantityChangeButton = document.getElementById('detail-submit-quantity-change-button');
    var cancelQuantityChangeFormButton = document.getElementById('detail-cancel-quantity-change-form-button');

    // 配達完了・キャンセル済みは相談セクション自体を表示しない(F4の操作欄非表示と同じ考え方)
    var NOT_ADJUSTABLE_STATUSES = ['配達完了', 'キャンセル'];

    // サーバーのエラーコード → 画面に出す文言。未知のコードはサーバーのmessageをそのまま使う。
    var CHANGE_REQUEST_ERROR_MESSAGES = {
        REQUEST_ALREADY_PENDING: 'すでにこの注文について相談中です。',
        ORDER_NOT_ADJUSTABLE: 'この注文は現在、変更やキャンセルの相談を受け付けられません。',
        QUANTITY_ALREADY_MINIMUM: '数量が1点のため、数量変更はできません。キャンセル相談をご利用ください。',
        INVALID_QUANTITY: '現在の数量より少ない、1点以上の数量を入力してください。',
        MULTIPLE_ITEMS_NOT_SUPPORTED: 'この注文は画面から変更相談できません。農家へ直接ご連絡ください。'
    };

    var currentOrder = null;

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

    function showChangeRequestError(text) {
        changeRequestSuccessEl.hidden = true;
        changeRequestMessageEl.textContent = text;
        changeRequestMessageEl.hidden = false;
    }

    function showChangeRequestSuccess(text) {
        changeRequestMessageEl.hidden = true;
        changeRequestSuccessEl.textContent = text;
        changeRequestSuccessEl.hidden = false;
    }

    function setChangeRequestButtonsBusy(busy) {
        requestQuantityChangeButton.disabled = busy;
        requestCancellationButton.disabled = busy;
        submitQuantityChangeButton.disabled = busy;
        cancelQuantityChangeFormButton.disabled = busy;
    }

    /**
     * 配達完了・キャンセル済みはセクション自体を隠す。未処理相談があればその内容を表示して
     * ボタンは両方隠す(二重送信防止)。無ければ数量に応じてボタンを出し分ける
     * (現在数量が1なら数量変更ボタンは出さない)。
     */
    function renderChangeRequestSection(order) {
        if (NOT_ADJUSTABLE_STATUSES.indexOf(order.status) !== -1) {
            changeRequestSectionEl.hidden = true;
            return;
        }

        changeRequestSectionEl.hidden = false;
        quantityChangeFormEl.hidden = true;

        var item = (order.order_items || [])[0];
        var pending = order.pending_change_request;

        if (pending) {
            changeRequestButtonsEl.hidden = true;
            pendingRequestInfoEl.hidden = false;

            if (pending.request_type === 'quantity_reduction') {
                pendingRequestHeadingEl.textContent = '数量変更のご相談を受け付けました';
                pendingRequestQuantityDetailEl.textContent =
                    pending.quantity_at_request + '点から' + pending.requested_quantity + '点への変更希望';
                pendingRequestQuantityDetailEl.hidden = false;
            } else {
                pendingRequestHeadingEl.textContent = 'キャンセルのご相談を受け付けました';
                pendingRequestQuantityDetailEl.hidden = true;
            }

            pendingRequestCreatedAtEl.textContent = '送信日時: ' + formatDateTime(pending.created_at);
            return;
        }

        pendingRequestInfoEl.hidden = true;
        changeRequestButtonsEl.hidden = false;
        requestQuantityChangeButton.hidden = !item || item.quantity < 2;
    }

    /**
     * 相談送信後、サーバーの状態を正として取り直して再描画する。
     * フロント側で注文数量・在庫・ステータスを直接書き換えることはしない。
     */
    function reloadOrder() {
        fetch(orderUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            renderOrder(data.order);
        }).catch(function () {
            showChangeRequestError('最新の状態の取得に失敗しました。画面を再読み込みしてください。');
        });
    }

    function submitChangeRequest(url, payload, successMessage) {
        setChangeRequestButtonsBusy(true);
        changeRequestMessageEl.hidden = true;
        changeRequestSuccessEl.hidden = true;

        var options = {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        };

        if (payload) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(payload);
        }

        fetch(url, options).then(function (response) {
            if (response.status === 401) {
                setChangeRequestButtonsBusy(false);
                showChangeRequestError('ログインの有効期限が切れています。もう一度ログインしてください。');
                return null;
            }
            if (response.status === 403) {
                setChangeRequestButtonsBusy(false);
                showChangeRequestError('この注文は操作できません。');
                return null;
            }
            if (response.status === 422) {
                return response.json().then(function (data) {
                    setChangeRequestButtonsBusy(false);
                    var code = data.error && data.error.code;
                    showChangeRequestError(
                        CHANGE_REQUEST_ERROR_MESSAGES[code]
                        || (data.error && data.error.message)
                        || 'この操作は行えませんでした。画面を確認してください。'
                    );
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
            setChangeRequestButtonsBusy(false);
            showChangeRequestSuccess(successMessage);
            reloadOrder();
        }).catch(function () {
            setChangeRequestButtonsBusy(false);
            showChangeRequestError('相談の送信に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    requestQuantityChangeButton.addEventListener('click', function () {
        var item = (currentOrder.order_items || [])[0];

        requestedQuantityInput.min = 1;
        requestedQuantityInput.max = item.quantity - 1;
        requestedQuantityInput.value = item.quantity - 1;

        changeRequestMessageEl.hidden = true;
        changeRequestButtonsEl.hidden = true;
        quantityChangeFormEl.hidden = false;
    });

    cancelQuantityChangeFormButton.addEventListener('click', function () {
        if (cancelQuantityChangeFormButton.disabled) {
            return;
        }
        quantityChangeFormEl.hidden = true;
        changeRequestButtonsEl.hidden = false;
        changeRequestMessageEl.hidden = true;
    });

    submitQuantityChangeButton.addEventListener('click', function () {
        var item = (currentOrder.order_items || [])[0];
        var requestedQuantity = parseInt(requestedQuantityInput.value, 10);

        if (!requestedQuantity || requestedQuantity < 1 || requestedQuantity >= item.quantity) {
            showChangeRequestError('現在の数量より少ない、1点以上の数量を入力してください。');
            return;
        }

        if (!window.confirm('数量を' + item.quantity + '点から' + requestedQuantity + '点へ変更する相談を農家へ送ります。よろしいですか?')) {
            return;
        }

        submitChangeRequest(
            orderUrl + '/quantity-change-requests',
            { requested_quantity: requestedQuantity },
            '数量変更のご相談を送信しました。'
        );
    });

    requestCancellationButton.addEventListener('click', function () {
        if (!window.confirm('注文はこの時点ではキャンセルされません。農家へキャンセルの相談を送信します。よろしいですか?')) {
            return;
        }

        submitChangeRequest(orderUrl + '/cancellation-requests', null, 'キャンセルのご相談を送信しました。');
    });

    function renderOrder(order) {
        currentOrder = order;
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

        renderChangeRequestSection(order);
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
