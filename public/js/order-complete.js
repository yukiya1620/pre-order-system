(function () {
    var container = document.querySelector('.order-complete-page');
    if (!container) {
        return;
    }

    var orderUrl = container.dataset.orderUrl;
    var loadingEl = document.getElementById('order-complete-loading');
    var messageEl = document.getElementById('order-complete-message');
    var contentEl = document.getElementById('order-complete-content');

    function showMessage(text) {
        messageEl.textContent = text;
        messageEl.hidden = false;
    }

    function formatDate(dateString) {
        var parts = dateString.split('-');
        return Number(parts[1]) + '月' + Number(parts[2]) + '日';
    }

    fetch(orderUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    }).then(function (response) {
        if (response.status === 401) {
            loadingEl.hidden = true;
            showMessage('ログインの有効期限が切れています。もう一度ログインしてください。');
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
        document.getElementById('complete-order-number').textContent = data.order.order_number;
        document.getElementById('complete-delivery-date').textContent = formatDate(data.order.delivery_date);
        contentEl.hidden = false;
    }).catch(function () {
        loadingEl.hidden = true;
        showMessage('注文情報の取得に失敗しました。時間をおいてもう一度お試しください。');
    });
})();
