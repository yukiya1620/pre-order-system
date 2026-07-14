(function () {
    var container = document.querySelector('.buyer-home-page');
    if (!container) {
        return;
    }

    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var logoutButton = document.getElementById('buyer-home-logout-button');

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
            // 401はすでにログアウト済みとして扱う。/ はbuyerミドルウェアで守られているため、
            // 未認証で再アクセスすると自動的に/loginへ振り分けられる
            if (response.ok || response.status === 401) {
                window.location.href = '/';
                return;
            }
            throw new Error('unexpected status ' + response.status);
        }).catch(function () {
            logoutButton.disabled = false;
            logoutButton.textContent = 'ログアウト';
        });
    });
})();
