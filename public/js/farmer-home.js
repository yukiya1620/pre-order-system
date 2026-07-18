(function () {
    var container = document.querySelector('.farmer-home');
    if (!container) {
        return;
    }

    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var pendingCountEl = document.getElementById('farmer-home-pending-count');
    var todaySalesEl = document.getElementById('farmer-home-today-sales');
    var changeRequestCountEl = document.getElementById('farmer-home-change-request-count');
    var messageEl = document.getElementById('farmer-home-message');
    var logoutButton = document.getElementById('farmer-home-logout-button');

    function showMessage(text) {
        messageEl.textContent = text;
        messageEl.hidden = false;
    }

    /**
     * 要対応件数。既にdelivery_confirmations一覧は「未回答のものだけ」に絞られた
     * 小さなデータなので、件数を数えるためだけに全件取得しても負担にならない。
     */
    function loadPendingCount() {
        fetch('/api/v1/farmer/delivery-confirmations', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            var count = (data.delivery_confirmations || []).length;
            pendingCountEl.textContent = count + '件';
        }).catch(function () {
            // 取得できていないのに0件と誤解されないよう、失敗だと分かる文言にする
            pendingCountEl.textContent = '取得できませんでした';
        });
    }

    /**
     * 本日の確定売上(status=配達完了、delivery_date=今日の合計)。
     * sales-summaryのconfirmed.todayを使う。sales-summaryのpendingは日付で絞られていない
     * 全期間の予定売上のため、「本日の予定売上」に相当する値はAPIに存在しない。
     * ここで新たに集計処理を行う必要はなく、すでにサーバー側で集計済みの値をそのまま使う。
     */
    function loadTodaySales() {
        fetch('/api/v1/farmer/sales-summary', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            if (!data.confirmed || !data.confirmed.today) {
                throw new Error('unexpected response shape');
            }
            todaySalesEl.textContent = '¥' + Number(data.confirmed.today.total_amount).toLocaleString();
        }).catch(function () {
            // 取得できていないのに0円と誤解されないよう、失敗だと分かる文言にする
            todaySalesEl.textContent = '取得できませんでした';
        });
    }

    /**
     * 要対応(変更相談)件数。配達確認の要対応件数とは別枠で表示する(合算しない)。
     * 一覧を取得して数えるのではなく、専用のcount APIが返す件数をそのまま使う。
     */
    function loadChangeRequestCount() {
        fetch('/api/v1/farmer/order-change-requests/count', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            // 0件も正常な値なので、falsyチェックではなく型で判定する
            if (typeof data.count !== 'number') {
                throw new Error('unexpected response shape');
            }
            changeRequestCountEl.textContent = data.count + '件';
        }).catch(function () {
            // 取得できていないのに0件と誤解されないよう、失敗だと分かる文言にする
            changeRequestCountEl.textContent = '取得できませんでした';
        });
    }

    logoutButton.addEventListener('click', function () {
        if (logoutButton.disabled) {
            return;
        }
        logoutButton.disabled = true;
        logoutButton.textContent = 'ログアウトしています…';

        fetch('/api/v1/auth/logout', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        }).then(function (response) {
            // 401はすでにログアウト済みとして扱い、どちらの場合も安全な既存画面(トップ)へ戻す
            if (response.ok || response.status === 401) {
                window.location.href = '/';
                return;
            }
            throw new Error('unexpected status ' + response.status);
        }).catch(function () {
            showMessage('ログアウトに失敗しました。時間をおいてもう一度お試しください。');
            logoutButton.disabled = false;
            logoutButton.textContent = 'ログアウト';
        });
    });

    loadPendingCount();
    loadTodaySales();
    loadChangeRequestCount();
})();
