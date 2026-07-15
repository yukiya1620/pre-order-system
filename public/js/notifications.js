(function () {
    var container = document.querySelector('.notifications-page');
    if (!container) {
        return;
    }

    var notificationsUrl = container.dataset.notificationsUrl;
    var markAllReadUrl = container.dataset.markAllReadUrl;
    var orderDetailBaseUrl = container.dataset.orderDetailBaseUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    var loadingEl = document.getElementById('notifications-loading');
    var messageEl = document.getElementById('notifications-message');
    var emptyEl = document.getElementById('notifications-empty');
    var listEl = document.getElementById('notifications-list');
    var markAllReadButton = document.getElementById('notifications-mark-all-read-button');

    function showMessage(text) {
        messageEl.textContent = text;
        messageEl.hidden = false;
    }

    function formatDateTime(dateString) {
        var date = new Date(dateString);
        return (date.getMonth() + 1) + '月' + date.getDate() + '日 '
            + String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
    }

    function markAsRead(notificationId) {
        return fetch(notificationsUrl + '/' + notificationId + '/read', {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        }).then(function (response) {
            return response.ok;
        }).catch(function () {
            return false;
        });
    }

    function buildCard(notification) {
        var card = document.createElement('div');
        card.className = 'notification-card' + (notification.is_read ? '' : ' notification-card--unread');

        var titleLine = document.createElement('p');
        titleLine.className = 'notification-card__title-line';

        var titleEl = document.createElement('span');
        titleEl.className = 'notification-card__title';
        titleEl.textContent = notification.title;
        titleLine.appendChild(titleEl);

        if (!notification.is_read) {
            var unreadBadge = document.createElement('span');
            unreadBadge.className = 'notification-card__unread-badge';
            unreadBadge.textContent = '未読';
            titleLine.appendChild(unreadBadge);
        }
        card.appendChild(titleLine);

        var bodyEl = document.createElement('p');
        bodyEl.className = 'notification-card__body';
        bodyEl.textContent = notification.body;
        card.appendChild(bodyEl);

        var dateEl = document.createElement('p');
        dateEl.className = 'notification-card__date';
        dateEl.textContent = formatDateTime(notification.created_at);
        card.appendChild(dateEl);

        var actionsEl = document.createElement('div');
        actionsEl.className = 'notification-card__actions';

        if (notification.related_order_id) {
            var viewButton = document.createElement('button');
            viewButton.type = 'button';
            viewButton.className = 'notification-card__view-button';
            viewButton.textContent = '関連する注文を見る ▶';
            viewButton.addEventListener('click', function () {
                // 可能なら先に既読にしてから遷移する(失敗しても閲覧自体は妨げない)
                var goToOrder = function () {
                    window.location.href = orderDetailBaseUrl + '/' + notification.related_order_id;
                };
                if (notification.is_read) {
                    goToOrder();
                } else {
                    markAsRead(notification.id).then(goToOrder);
                }
            });
            actionsEl.appendChild(viewButton);
        }

        if (!notification.is_read) {
            var readButton = document.createElement('button');
            readButton.type = 'button';
            readButton.className = 'notification-card__read-button';
            readButton.textContent = '既読にする';
            readButton.addEventListener('click', function () {
                readButton.disabled = true;
                markAsRead(notification.id).then(function (ok) {
                    if (ok) {
                        loadNotifications();
                    } else {
                        readButton.disabled = false;
                        showMessage('既読にできませんでした。時間をおいてもう一度お試しください。');
                    }
                });
            });
            actionsEl.appendChild(readButton);
        }

        card.appendChild(actionsEl);

        return card;
    }

    function loadNotifications() {
        loadingEl.hidden = false;
        messageEl.hidden = true;
        emptyEl.hidden = true;
        listEl.innerHTML = '';

        fetch(notificationsUrl, {
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
            var notifications = data.notifications || [];

            if (notifications.length === 0) {
                emptyEl.hidden = false;
                return;
            }

            notifications.forEach(function (notification) {
                listEl.appendChild(buildCard(notification));
            });
        }).catch(function () {
            loadingEl.hidden = true;
            showMessage('通知の取得に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    markAllReadButton.addEventListener('click', function () {
        if (markAllReadButton.disabled) {
            return;
        }
        markAllReadButton.disabled = true;

        fetch(markAllReadUrl, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            loadNotifications();
        }).catch(function () {
            showMessage('既読処理に失敗しました。時間をおいてもう一度お試しください。');
        }).finally(function () {
            markAllReadButton.disabled = false;
        });
    });

    loadNotifications();
})();
