(function () {
    var container = document.querySelector('.order-detail-page');
    if (!container) {
        return;
    }

    var orderUrl = container.dataset.orderUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var loadingEl = document.getElementById('order-detail-loading');
    var generalMessageEl = document.getElementById('order-detail-message');
    var contentEl = document.getElementById('order-detail-content');

    var actionsSectionEl = document.getElementById('detail-actions-section');
    var actionMessageEl = document.getElementById('detail-action-message');
    var reduceQuantityFieldEl = document.getElementById('detail-reduce-quantity-field');
    var newQuantityInput = document.getElementById('detail-new-quantity');
    var adjustmentNoteInput = document.getElementById('detail-adjustment-note');
    var confirmedWithBuyerCheckbox = document.getElementById('detail-confirmed-with-buyer');
    var reduceQuantityButton = document.getElementById('detail-reduce-quantity-button');
    var cancelOrderButton = document.getElementById('detail-cancel-order-button');

    var changeRequestSectionEl = document.getElementById('detail-change-request-section');
    var changeRequestMessageEl = document.getElementById('detail-change-request-message');
    var changeRequestSuccessEl = document.getElementById('detail-change-request-success');
    var changeRequestTypeEl = document.getElementById('detail-change-request-type');
    var changeRequestSummaryEl = document.getElementById('detail-change-request-summary');
    var changeRequestCreatedAtEl = document.getElementById('detail-change-request-created-at');
    var changeRequestNoteInput = document.getElementById('detail-change-request-note');
    var resolveWithoutChangeButton = document.getElementById('detail-resolve-without-change-button');

    var NOT_ADJUSTABLE_STATUSES = ['配達完了', 'キャンセル'];

    // resolve-without-change APIのエラーコード → 画面に出す文言
    var CHANGE_REQUEST_ERROR_MESSAGES = {
        ALREADY_RESOLVED: 'この相談はすでに対応済みです。最新の状態を再読み込みします。',
        ORDER_MISMATCH: '相談内容と注文情報を確認できませんでした。画面を再読み込みしてください。'
    };

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
            quantityCell.textContent = item.quantity + unitLabelFor(item);
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

    var currentOrder = null;

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

    /**
     * 商品の単位(袋・本・パックなど)は明細のproduct_sale.product.unit_labelを使う。
     * 取得できない場合だけ、この画面で従来使っていた「袋」にそろえる。
     * 商品明細テーブル・購入者からのご相談セクション、どちらもこの関数で統一する。
     */
    function unitLabelFor(item) {
        var unit = item && item.product_sale && item.product_sale.product && item.product_sale.product.unit_label;
        return unit || '袋';
    }

    /**
     * 未処理の相談(pending_change_request)があるときだけセクションを表示する。
     * 数量減少・キャンセルが確定されて相談が自動解消された場合や、「変更せず終了」した場合は
     * 再取得のたびにここでhiddenへ戻る。
     */
    function renderPendingChangeRequest(order) {
        changeRequestMessageEl.hidden = true;
        changeRequestSuccessEl.hidden = true;

        var pending = order.pending_change_request;

        if (!pending) {
            changeRequestSectionEl.hidden = true;
            return;
        }

        changeRequestSectionEl.hidden = false;
        changeRequestNoteInput.value = '';

        var unit = unitLabelFor((order.order_items || [])[0]);

        if (pending.request_type === 'quantity_reduction') {
            changeRequestTypeEl.textContent = '数量変更';
            changeRequestSummaryEl.textContent =
                pending.quantity_at_request + unit + 'から' + pending.requested_quantity + unit + 'への変更希望';
        } else {
            changeRequestTypeEl.textContent = 'キャンセル';
            changeRequestSummaryEl.textContent =
                '注文キャンセルの相談です(相談時点の数量: ' + pending.quantity_at_request + unit + ')';
        }

        changeRequestCreatedAtEl.textContent = formatDateTime(pending.created_at);
    }

    resolveWithoutChangeButton.addEventListener('click', function () {
        var pending = currentOrder && currentOrder.pending_change_request;
        if (!pending) {
            return;
        }

        if (!window.confirm('注文内容を変更せず、この相談を終了します。購入者へ通知されます。よろしいですか?')) {
            return;
        }

        resolveWithoutChangeButton.disabled = true;
        changeRequestMessageEl.hidden = true;
        changeRequestSuccessEl.hidden = true;

        fetch('/api/v1/farmer/order-change-requests/' + pending.id + '/resolve-without-change', {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ note: changeRequestNoteInput.value || null })
        }).then(function (response) {
            if (response.status === 401) {
                resolveWithoutChangeButton.disabled = false;
                showChangeRequestError('ログインの有効期限が切れています。もう一度ログインしてください。');
                return null;
            }
            if (response.status === 404) {
                resolveWithoutChangeButton.disabled = false;
                showChangeRequestError('相談内容と注文情報を確認できませんでした。画面を再読み込みしてください。');
                return null;
            }
            if (response.status === 422) {
                return response.json().then(function (data) {
                    var code = data.error && data.error.code;
                    showChangeRequestError(
                        CHANGE_REQUEST_ERROR_MESSAGES[code]
                        || (data.error && data.error.message)
                        || 'この操作は行えませんでした。画面を確認してください。'
                    );

                    if (code === 'ALREADY_RESOLVED') {
                        // 古い相談表示を残さないよう、最新の状態を取り直す
                        // (ボタンの有効化は再取得後のrenderPendingChangeRequestに任せる)
                        loadOrder();
                    } else {
                        resolveWithoutChangeButton.disabled = false;
                    }
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
            showChangeRequestSuccess('相談を終了しました。');
            loadOrder();
        }).catch(function () {
            resolveWithoutChangeButton.disabled = false;
            showChangeRequestError('相談を終了できませんでした。時間をおいてもう一度お試しください。');
        });
    });

    /**
     * 数量が2以上なら「数量を減らす」「注文をキャンセルする」の両方、
     * 1なら全キャンセルのみを選べる。配達完了・キャンセル済みは操作欄自体を隠す。
     */
    function renderActions(order) {
        var item = (order.order_items || [])[0];

        if (!item || NOT_ADJUSTABLE_STATUSES.indexOf(order.status) !== -1) {
            actionsSectionEl.hidden = true;
            return;
        }

        actionsSectionEl.hidden = false;
        actionMessageEl.hidden = true;
        confirmedWithBuyerCheckbox.checked = false;
        adjustmentNoteInput.value = '';

        if (item.quantity >= 2) {
            reduceQuantityFieldEl.hidden = false;
            reduceQuantityButton.hidden = false;
            newQuantityInput.min = 1;
            newQuantityInput.max = item.quantity - 1;
            newQuantityInput.value = item.quantity - 1;
        } else {
            reduceQuantityFieldEl.hidden = true;
            reduceQuantityButton.hidden = true;
        }
    }

    function showActionError(text) {
        actionMessageEl.textContent = text;
        actionMessageEl.hidden = false;
    }

    function setActionsBusy(busy) {
        reduceQuantityButton.disabled = busy;
        cancelOrderButton.disabled = busy;
    }

    /**
     * 数量減少・キャンセル共通の送信処理。二重クリック自体はボタンのdisabledで防ぐが、
     * サーバー側もロック後の再検証で二重実行を防いでいるため、通信の再送があっても安全。
     */
    function submitAdjustment(path, payload) {
        if (!confirmedWithBuyerCheckbox.checked) {
            showActionError('購入者へ電話等で確認済みであることをチェックしてください。');
            return;
        }

        setActionsBusy(true);
        actionMessageEl.hidden = true;

        fetch(orderUrl + path, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        }).then(function (response) {
            if (response.status === 401) {
                setActionsBusy(false);
                showActionError('ログインの有効期限が切れています。もう一度ログインしてください。');
                return null;
            }
            if (response.status === 422) {
                return response.json().then(function (data) {
                    setActionsBusy(false);
                    showActionError((data.error && data.error.message) || 'この操作は行えませんでした。画面を確認してください。');
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
            setActionsBusy(false);
            renderOrder(data.order);
        }).catch(function () {
            setActionsBusy(false);
            showActionError('通信に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    reduceQuantityButton.addEventListener('click', function () {
        var item = (currentOrder.order_items || [])[0];
        var newQuantity = parseInt(newQuantityInput.value, 10);

        if (!newQuantity || newQuantity < 1 || newQuantity >= item.quantity) {
            showActionError('新しい数量は、現在の数量(' + item.quantity + ')より少ない1以上の値にしてください。');
            return;
        }

        if (!window.confirm('数量を' + item.quantity + 'から' + newQuantity + 'に変更します。よろしいですか?')) {
            return;
        }

        submitAdjustment('/reduce-quantity', {
            quantity: newQuantity,
            confirmed_with_buyer_at: true,
            note: adjustmentNoteInput.value || null
        });
    });

    cancelOrderButton.addEventListener('click', function () {
        if (!window.confirm('注文番号 ' + currentOrder.order_number + ' をキャンセルします。よろしいですか?')) {
            return;
        }

        submitAdjustment('/cancel', {
            confirmed_with_buyer_at: true,
            note: adjustmentNoteInput.value || null
        });
    });

    function renderOrder(order) {
        currentOrder = order;
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
        renderPendingChangeRequest(order);
        renderActions(order);
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
