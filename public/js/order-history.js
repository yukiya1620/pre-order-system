(function () {
    var container = document.querySelector('.order-history-page');
    if (!container) {
        return;
    }

    var ordersUrl = container.dataset.ordersUrl;
    var orderDetailBaseUrl = container.dataset.orderDetailBaseUrl;
    var orderConfirmBaseUrl = container.dataset.orderConfirmBaseUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    var loadingEl = document.getElementById('order-history-loading');
    var messageEl = document.getElementById('order-history-message');
    var emptyEl = document.getElementById('order-history-empty');
    var listEl = document.getElementById('order-history-list');
    var periodSelect = document.getElementById('history-period-select');
    var monthLabel = document.getElementById('history-month-label');
    var monthSelect = document.getElementById('history-month-select');

    var currentYear = new Date().getFullYear();
    var YEARS_BACK = 4; // 今年を含め過去5年分を選択肢にする(それより前は必要になったら増やす)

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
        messageEl.textContent = text;
        messageEl.hidden = false;
    }

    function formatDate(dateString) {
        var parts = dateString.split('-');
        return Number(parts[1]) + '月' + Number(parts[2]) + '日';
    }

    /**
     * 設計書4.5の想定通り、今年は月まで選べるが、過去年は年単位でしか絞り込めない。
     */
    function buildPeriodOptions() {
        var recentOption = document.createElement('option');
        recentOption.value = '';
        recentOption.textContent = '直近6か月';
        periodSelect.appendChild(recentOption);

        for (var i = 0; i <= YEARS_BACK; i++) {
            var year = currentYear - i;
            var option = document.createElement('option');
            option.value = String(year);
            option.textContent = year + '年';
            periodSelect.appendChild(option);
        }

        for (var m = 1; m <= 12; m++) {
            var monthOption = document.createElement('option');
            monthOption.value = String(m);
            monthOption.textContent = m + '月';
            monthSelect.appendChild(monthOption);
        }

        var allMonthsOption = document.createElement('option');
        allMonthsOption.value = '';
        allMonthsOption.textContent = 'すべて';
        monthSelect.insertBefore(allMonthsOption, monthSelect.firstChild);
    }

    function updateMonthVisibility() {
        var selectedYear = periodSelect.value;
        var showMonth = selectedYear !== '' && Number(selectedYear) === currentYear;
        monthLabel.hidden = !showMonth;
        monthSelect.hidden = !showMonth;
        if (!showMonth) {
            monthSelect.value = '';
        }
    }

    function buildQueryString() {
        var params = new URLSearchParams();
        if (periodSelect.value) {
            params.set('year', periodSelect.value);
            if (!monthSelect.hidden && monthSelect.value) {
                params.set('month', monthSelect.value);
            }
        }
        return params.toString();
    }

    function setCardBusy(card, busy) {
        var controls = card.querySelectorAll('button');
        for (var i = 0; i < controls.length; i++) {
            controls[i].disabled = busy;
        }
    }

    function showCardMessage(card, text) {
        var el = card.querySelector('.order-history-card__message');
        el.textContent = text;
        el.hidden = false;
    }

    /**
     * 「前回と同じ内容で注文」。reorder-previewで在庫・販売状況を先に確認し、
     * 問題なければB5(注文内容の確認)へ遷移する。B5自体はここでは呼ばず、
     * 通常のB4→B5と同じクエリ文字列を組み立てるだけにとどめる。
     */
    function handleReorder(card, orderId) {
        setCardBusy(card, true);

        fetch(ordersUrl + '/' + orderId + '/reorder-preview', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        }).then(function (response) {
            if (response.status === 401) {
                showGeneralMessage('ログインの有効期限が切れています。もう一度ログインしてください。');
                setCardBusy(card, false);
                return null;
            }
            if (response.status === 422) {
                return response.json().then(function (data) {
                    var text = (data.error && data.error.message) || 'この商品は現在ご注文いただけません。';
                    showCardMessage(card, text);
                    setCardBusy(card, false);
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
            if (data.reorder_params.delivery_date) {
                params.set('delivery_date', data.reorder_params.delivery_date);
            }
            window.location.href = orderConfirmBaseUrl + '?' + params.toString();
        }).catch(function () {
            showCardMessage(card, '確認に失敗しました。時間をおいてもう一度お試しください。');
            setCardBusy(card, false);
        });
    }

    function buildCard(order) {
        var card = document.createElement('div');
        card.className = 'order-history-card';

        var dateEl = document.createElement('p');
        dateEl.className = 'order-history-card__date';
        dateEl.textContent = formatDate(order.delivery_date) + ' 配達予定';
        card.appendChild(dateEl);

        var numberEl = document.createElement('p');
        numberEl.className = 'order-history-card__number';
        numberEl.textContent = '注文番号: ' + order.order_number;
        card.appendChild(numberEl);

        var badge = document.createElement('span');
        badge.className = 'order-card__status-badge ' + (statusBadgeClasses[order.status] || '');
        if (statusIcons[order.status]) {
            var iconEl = document.createElement('span');
            iconEl.setAttribute('aria-hidden', 'true');
            iconEl.textContent = statusIcons[order.status];
            badge.appendChild(iconEl);
            badge.appendChild(document.createTextNode(' '));
        }
        badge.appendChild(document.createTextNode(order.status));
        card.appendChild(badge);

        var items = (order.order_items || []).map(function (item) {
            return item.product_name + ' ' + item.quantity + '点';
        }).join(' / ');
        var itemsEl = document.createElement('p');
        itemsEl.className = 'order-history-card__items';
        itemsEl.textContent = items;
        card.appendChild(itemsEl);

        var amountEl = document.createElement('p');
        amountEl.className = 'order-history-card__amount';
        amountEl.textContent = '¥' + Number(order.total_amount).toLocaleString();
        card.appendChild(amountEl);

        var actionsEl = document.createElement('div');
        actionsEl.className = 'order-history-card__actions';

        var detailLink = document.createElement('a');
        detailLink.className = 'order-history-card__detail-link';
        detailLink.href = orderDetailBaseUrl + '/' + order.id;
        detailLink.textContent = '詳細を見る ▶';
        actionsEl.appendChild(detailLink);

        var reorderButton = document.createElement('button');
        reorderButton.type = 'button';
        reorderButton.className = 'order-history-card__reorder-button';
        reorderButton.textContent = '前回と同じ内容で注文';
        reorderButton.addEventListener('click', function () {
            handleReorder(card, order.id);
        });
        actionsEl.appendChild(reorderButton);

        card.appendChild(actionsEl);

        var cardMessageEl = document.createElement('p');
        cardMessageEl.className = 'order-history-card__message message message-error';
        cardMessageEl.hidden = true;
        card.appendChild(cardMessageEl);

        return card;
    }

    function loadOrders() {
        loadingEl.hidden = false;
        messageEl.hidden = true;
        emptyEl.hidden = true;
        listEl.innerHTML = '';

        fetch(ordersUrl + '?' + buildQueryString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            loadingEl.hidden = true;
            var orders = data.orders || [];

            if (orders.length === 0) {
                emptyEl.hidden = false;
                return;
            }

            orders.forEach(function (order) {
                listEl.appendChild(buildCard(order));
            });
        }).catch(function () {
            loadingEl.hidden = true;
            showGeneralMessage('注文履歴の取得に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    periodSelect.addEventListener('change', function () {
        updateMonthVisibility();
        loadOrders();
    });
    monthSelect.addEventListener('change', loadOrders);

    buildPeriodOptions();
    updateMonthVisibility();
    loadOrders();
})();
