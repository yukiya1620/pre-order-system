(function () {
    var form = document.getElementById('settings-form');
    if (!form) {
        return;
    }

    var usersMeUrl = form.dataset.usersMeUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var loadingIndicator = document.getElementById('loading-indicator');
    var generalMessage = document.getElementById('general-message');
    var saveButton = document.getElementById('save-button');

    var roleLabels = { buyer: '購入者', farmer: '農家' };

    function showGeneralMessage(text, type) {
        generalMessage.textContent = text;
        generalMessage.className = 'message ' + (type === 'success' ? 'message-success' : 'message-error');
        generalMessage.hidden = false;
    }

    function clearFieldErrors() {
        ['name', 'email', 'address'].forEach(function (field) {
            var el = document.getElementById(field + '-error');
            el.textContent = '';
            el.hidden = true;
        });
    }

    function showFieldErrors(errors) {
        clearFieldErrors();
        Object.keys(errors).forEach(function (field) {
            var el = document.getElementById(field + '-error');
            if (el) {
                el.textContent = errors[field][0];
                el.hidden = false;
            }
        });
    }

    function fillForm(user) {
        document.getElementById('name').value = user.name || '';
        document.getElementById('email').value = user.email || '';
        document.getElementById('address').value = user.address || '';
        document.getElementById('phone_number').textContent = user.phone_number || '';
        document.getElementById('role').textContent = roleLabels[user.role] || user.role || '';
    }

    function loadProfile() {
        fetch(usersMeUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (response.status === 401) {
                loadingIndicator.hidden = true;
                showGeneralMessage('ログインの有効期限が切れています。もう一度ログインしてください。', 'error');
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
            fillForm(data.user);
            loadingIndicator.hidden = true;
            form.hidden = false;
        }).catch(function () {
            loadingIndicator.hidden = true;
            showGeneralMessage('登録情報の取得に失敗しました。時間をおいてもう一度お試しください。', 'error');
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (saveButton.disabled) {
            return;
        }
        saveButton.disabled = true;
        saveButton.textContent = '保存しています…';
        generalMessage.hidden = true;
        clearFieldErrors();

        var payload = {
            name: document.getElementById('name').value,
            email: document.getElementById('email').value,
            address: document.getElementById('address').value
        };

        fetch(usersMeUrl, {
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
                showGeneralMessage('ログインの有効期限が切れています。もう一度ログインしてください。', 'error');
                return null;
            }
            if (response.status === 422) {
                return response.json().then(function (data) {
                    showFieldErrors(data.errors || {});
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
            fillForm(data.user);
            showGeneralMessage('保存しました。', 'success');
        }).catch(function () {
            showGeneralMessage('保存に失敗しました。時間をおいてもう一度お試しください。', 'error');
        }).finally(function () {
            saveButton.disabled = false;
            saveButton.textContent = '保存する';
        });
    });

    loadProfile();
})();
