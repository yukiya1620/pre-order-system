(function () {
    var container = document.querySelector('.sales-page');
    if (!container) {
        return;
    }

    var summaryUrl = container.dataset.salesSummaryUrl;
    var byProductBaseUrl = container.dataset.salesByProductUrl;

    var summaryLoadingEl = document.getElementById('sales-summary-loading');
    var summaryMessageEl = document.getElementById('sales-summary-message');
    var summaryContentEl = document.getElementById('sales-summary-content');

    var byProductLoadingEl = document.getElementById('sales-by-product-loading');
    var byProductMessageEl = document.getElementById('sales-by-product-message');
    var byProductEmptyEl = document.getElementById('sales-by-product-empty');
    var byProductTableEl = document.getElementById('sales-by-product-table');
    var byProductBodyEl = document.getElementById('sales-by-product-body');
    var periodButtons = document.querySelectorAll('.sales-period-button');

    function formatYen(amount) {
        return '¥' + Number(amount).toLocaleString();
    }

    function showSummaryError(text) {
        summaryMessageEl.textContent = text;
        summaryMessageEl.hidden = false;
    }

    function showByProductError(text) {
        byProductMessageEl.textContent = text;
        byProductMessageEl.hidden = false;
    }

    /**
     * 確定売上・予定売上・内訳をまとめて取得する。商品別売上(sales-by-product)とは
     * 別のAPI呼び出しなので、こちらが失敗しても商品別売上の表示には影響しない。
     */
    function loadSummary() {
        fetch(summaryUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (response.status === 401) {
                summaryLoadingEl.hidden = true;
                showSummaryError('ログインの有効期限が切れています。もう一度ログインしてください。');
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
            summaryLoadingEl.hidden = true;

            document.getElementById('confirmed-today-amount').textContent = formatYen(data.confirmed.today.total_amount);
            document.getElementById('confirmed-today-count').textContent = data.confirmed.today.order_count + '件';
            document.getElementById('confirmed-month-amount').textContent = formatYen(data.confirmed.this_month.total_amount);
            document.getElementById('confirmed-month-count').textContent = data.confirmed.this_month.order_count + '件';
            document.getElementById('confirmed-year-amount').textContent = formatYen(data.confirmed.this_year.total_amount);
            document.getElementById('confirmed-year-count').textContent = data.confirmed.this_year.order_count + '件';

            document.getElementById('pending-amount').textContent = formatYen(data.pending.total_amount);
            document.getElementById('pending-count').textContent = data.pending.order_count + '件';

            ['paid', 'unpaid', 'refunded'].forEach(function (key) {
                document.getElementById('payment-status-' + key + '-amount').textContent = formatYen(data.payment_status_breakdown[key].total_amount);
                document.getElementById('payment-status-' + key + '-count').textContent = data.payment_status_breakdown[key].order_count + '件';
            });
            ['cash', 'card', 'paypay'].forEach(function (key) {
                document.getElementById('payment-method-' + key + '-amount').textContent = formatYen(data.payment_method_breakdown[key].total_amount);
                document.getElementById('payment-method-' + key + '-count').textContent = data.payment_method_breakdown[key].order_count + '件';
            });

            summaryContentEl.hidden = false;
        }).catch(function () {
            summaryLoadingEl.hidden = true;
            showSummaryError('売上集計の取得に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    function loadByProduct(period) {
        byProductLoadingEl.hidden = false;
        byProductMessageEl.hidden = true;
        byProductEmptyEl.hidden = true;
        byProductTableEl.hidden = true;
        byProductBodyEl.innerHTML = '';

        fetch(byProductBaseUrl + '?period=' + period, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (response.status === 401) {
                byProductLoadingEl.hidden = true;
                showByProductError('ログインの有効期限が切れています。もう一度ログインしてください。');
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
            byProductLoadingEl.hidden = true;

            var products = data.products || [];
            if (products.length === 0) {
                byProductEmptyEl.hidden = false;
                return;
            }

            products.forEach(function (product) {
                var row = document.createElement('tr');

                var nameCell = document.createElement('td');
                nameCell.textContent = product.product_name;
                row.appendChild(nameCell);

                var quantityCell = document.createElement('td');
                quantityCell.textContent = product.total_quantity;
                row.appendChild(quantityCell);

                var amountCell = document.createElement('td');
                amountCell.textContent = formatYen(product.total_amount);
                row.appendChild(amountCell);

                byProductBodyEl.appendChild(row);
            });

            byProductTableEl.hidden = false;
        }).catch(function () {
            byProductLoadingEl.hidden = true;
            showByProductError('商品別売上の取得に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    periodButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            periodButtons.forEach(function (b) {
                b.classList.remove('sales-period-button--active');
            });
            button.classList.add('sales-period-button--active');
            loadByProduct(button.dataset.period);
        });
    });

    loadSummary();
    loadByProduct('month');
})();
