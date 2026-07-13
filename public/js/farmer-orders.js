(function () {
    var container = document.querySelector('.orders-page');
    if (!container) {
        return;
    }

    var ordersUrl = container.dataset.ordersUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var loadingEl = document.getElementById('orders-loading');
    var generalMessageEl = document.getElementById('orders-message');
    var emptyEl = document.getElementById('orders-empty');
    var listEl = document.getElementById('orders-list');
    var filterSelect = document.getElementById('orders-status-filter');
    var pagerEl = document.getElementById('orders-pager');
    var prevButton = document.getElementById('orders-prev-button');
    var nextButton = document.getElementById('orders-next-button');
    var pageIndicatorEl = document.getElementById('orders-page-indicator');

    var statusBadgeClasses = {
        '受付済': 'order-status-badge--received',
        '配達確認済': 'order-status-badge--confirmed',
        '配達日変更': 'order-status-badge--changed',
        '配達完了': 'order-status-badge--delivered',
        'キャンセル': 'order-status-badge--cancelled'
    };

    var paymentStatusLabels = {
        paid: '支払い済み',
        unpaid: '未払い'
    };

    var paymentMethodLabels = {
        cash: '現金',
        card: 'カード',
        paypay: 'PayPay'
    };

    var currentPage = 1;

    function showGeneralMessage(text) {
        generalMessageEl.textContent = text;
        generalMessageEl.hidden = false;
    }

    function formatDeliveryDate(dateString) {
        var date = new Date(dateString);
        return (date.getMonth() + 1) + '月' + date.getDate() + '日';
    }

    /**
     * 現在選択中の絞り込みに、その注文のステータスが合致するか。
     * 配達完了操作の後、一覧に残すか取り除くかの判定にも使う。
     */
    function orderMatchesCurrentFilter(status) {
        var filter = filterSelect.value;
        if (filter === 'active') {
            return status !== '配達完了' && status !== 'キャンセル';
        }
        if (filter === 'all') {
            return true;
        }
        return status === filter;
    }

    function buildQueryString(page) {
        var params = new URLSearchParams();
        var filter = filterSelect.value;

        if (filter === 'active') {
            params.set('active_only', '1');
        } else if (filter !== 'all') {
            params.set('status', filter);
        }
        params.set('page', String(page));

        return params.toString();
    }

    function setCardBusy(card, busy) {
        var controls = card.querySelectorAll('button, select');
        for (var i = 0; i < controls.length; i++) {
            controls[i].disabled = busy;
        }
    }

    function showCardMessage(card, text) {
        var messageEl = card.querySelector('.order-card__message');
        messageEl.textContent = text;
        messageEl.hidden = false;
    }

    function updateStatusBadge(card, status) {
        var badge = card.querySelector('.order-card__status-badge');
        Object.keys(statusBadgeClasses).forEach(function (key) {
            badge.classList.remove(statusBadgeClasses[key]);
        });
        badge.classList.add(statusBadgeClasses[status] || '');
        badge.textContent = status;
    }

    /**
     * 配達完了APIを呼ぶ。成功後、今の絞り込みに合わなくなった注文はカードごと取り除き、
     * 合致する場合(例: 「すべて」「配達完了」で見ている場合)はバッジと操作欄を更新するだけにする。
     */
    function submitComplete(card, orderId, payload) {
        setCardBusy(card, true);

        fetch(ordersUrl + '/' + orderId + '/complete', {
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
                showGeneralMessage('ログインの有効期限が切れています。もう一度ログインしてください。');
                setCardBusy(card, false);
                return null;
            }
            if (response.status === 422) {
                return response.json().then(function (data) {
                    var message = (data.error && data.error.message)
                        || 'この操作は行えませんでした。画面を確認してください。';
                    showCardMessage(card, message);
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
            var newStatus = data.order.status;
            if (orderMatchesCurrentFilter(newStatus)) {
                updateStatusBadge(card, newStatus);
                var form = card.querySelector('.order-card__complete-form');
                form.hidden = true;
                setCardBusy(card, false);
            } else {
                card.remove();
                if (listEl.children.length === 0) {
                    emptyEl.hidden = false;
                }
            }
        }).catch(function () {
            showCardMessage(card, '配達完了の送信に失敗しました。時間をおいてもう一度お試しください。');
            setCardBusy(card, false);
        });
    }

    function buildCompleteForm(card, order) {
        var wrapper = document.createElement('div');
        wrapper.className = 'order-card__complete-form';
        wrapper.hidden = true;

        var statusLabel = document.createElement('label');
        statusLabel.textContent = '支払い状況';
        wrapper.appendChild(statusLabel);

        var statusSelect = document.createElement('select');
        [['', '選択してください'], ['paid', '支払い済み'], ['unpaid', '未払い']].forEach(function (pair) {
            var option = document.createElement('option');
            option.value = pair[0];
            option.textContent = pair[1];
            statusSelect.appendChild(option);
        });
        wrapper.appendChild(statusSelect);

        var methodLabel = document.createElement('label');
        methodLabel.textContent = '支払い方法(任意)';
        wrapper.appendChild(methodLabel);

        var methodSelect = document.createElement('select');
        [['', '未選択'], ['cash', '現金'], ['card', 'カード'], ['paypay', 'PayPay']].forEach(function (pair) {
            var option = document.createElement('option');
            option.value = pair[0];
            option.textContent = pair[1];
            methodSelect.appendChild(option);
        });
        wrapper.appendChild(methodSelect);

        var submitButton = document.createElement('button');
        submitButton.type = 'button';
        submitButton.textContent = 'この内容で配達完了にする';
        submitButton.addEventListener('click', function () {
            if (!statusSelect.value) {
                showCardMessage(card, '支払い状況を選択してください。');
                return;
            }

            var statusLabelText = paymentStatusLabels[statusSelect.value];
            var methodLabelText = methodSelect.value ? paymentMethodLabels[methodSelect.value] : '未選択';
            var confirmText = 'この注文を配達完了にします。支払い状況: '
                + statusLabelText + ' / 支払い方法: ' + methodLabelText + ' でよろしいですか?';

            if (!window.confirm(confirmText)) {
                return;
            }

            var payload = { payment_status: statusSelect.value };
            if (methodSelect.value) {
                payload.payment_method = methodSelect.value;
            }

            submitComplete(card, order.id, payload);
        });
        wrapper.appendChild(submitButton);

        return wrapper;
    }

    function buildCard(order) {
        var items = (order.order_items || []).map(function (item) {
            return item.product_name + ' ' + item.quantity + '袋';
        }).join(' / ');

        var card = document.createElement('div');
        card.className = 'order-card';

        var dateEl = document.createElement('p');
        dateEl.className = 'order-card__date';
        dateEl.textContent = formatDeliveryDate(order.delivery_date) + 'に配達予定';
        card.appendChild(dateEl);

        var buyerEl = document.createElement('p');
        buyerEl.className = 'order-card__buyer';
        buyerEl.textContent = order.user.name + ' 様';
        card.appendChild(buyerEl);

        var itemsEl = document.createElement('p');
        itemsEl.className = 'order-card__items';
        itemsEl.textContent = items;
        card.appendChild(itemsEl);

        var statusBadge = document.createElement('span');
        statusBadge.className = 'order-card__status-badge';
        card.appendChild(statusBadge);
        updateStatusBadge(card, order.status);

        if (order.status !== 'キャンセル') {
            var completeButton = document.createElement('button');
            completeButton.type = 'button';
            completeButton.className = 'order-card__complete-button';
            completeButton.textContent = '配達完了にする';

            var completeForm = buildCompleteForm(card, order);

            completeButton.addEventListener('click', function () {
                completeForm.hidden = !completeForm.hidden;
            });

            card.appendChild(completeButton);
            card.appendChild(completeForm);
        }

        var detailEl = document.createElement('div');
        detailEl.className = 'order-card__unimplemented';
        detailEl.setAttribute('aria-disabled', 'true');
        detailEl.textContent = '注文詳細 (準備中)';
        card.appendChild(detailEl);

        var cardMessageEl = document.createElement('p');
        cardMessageEl.className = 'order-card__message message message-error';
        cardMessageEl.hidden = true;
        card.appendChild(cardMessageEl);

        return card;
    }

    function updatePager(pagination) {
        pagerEl.hidden = false;
        pageIndicatorEl.textContent = pagination.current_page + ' / ' + pagination.last_page;
        prevButton.disabled = !pagination.prev_page_url;
        nextButton.disabled = !pagination.next_page_url;
    }

    function loadOrders() {
        loadingEl.hidden = false;
        generalMessageEl.hidden = true;
        emptyEl.hidden = true;
        pagerEl.hidden = true;
        listEl.innerHTML = '';

        fetch(ordersUrl + '?' + buildQueryString(currentPage), {
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

            var pagination = data.orders;
            var orders = pagination.data || [];

            if (orders.length === 0) {
                emptyEl.hidden = false;
                return;
            }

            orders.forEach(function (order) {
                listEl.appendChild(buildCard(order));
            });

            updatePager(pagination);
        }).catch(function () {
            loadingEl.hidden = true;
            showGeneralMessage('予約一覧の取得に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    filterSelect.addEventListener('change', function () {
        currentPage = 1;
        loadOrders();
    });

    prevButton.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage -= 1;
            loadOrders();
        }
    });

    nextButton.addEventListener('click', function () {
        currentPage += 1;
        loadOrders();
    });

    loadOrders();
})();
