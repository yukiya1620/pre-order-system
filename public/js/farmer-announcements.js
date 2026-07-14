(function () {
    var container = document.querySelector('.announcements-page');
    if (!container) {
        return;
    }

    // 定型文ボタンの候補。増減したい場合はこの配列を編集するだけでよい。
    var PRESET_PHRASES = [
        '本日収穫しました',
        '本日の販売分は売り切れました',
        '臨時休業のお知らせ'
    ];

    var announcementsUrl = container.dataset.announcementsUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    var presetButtonsEl = document.getElementById('announcement-preset-buttons');
    var form = document.getElementById('announcement-form');
    var formMessageEl = document.getElementById('announcement-form-message');
    var idInput = document.getElementById('announcement-id');
    var titleInput = document.getElementById('announcement-title');
    var bodyInput = document.getElementById('announcement-body');
    var isPublishedCheckbox = document.getElementById('announcement-is-published');
    var submitButton = document.getElementById('announcement-submit-button');
    var cancelEditButton = document.getElementById('announcement-cancel-edit-button');

    var loadingEl = document.getElementById('announcements-loading');
    var listMessageEl = document.getElementById('announcements-message');
    var emptyEl = document.getElementById('announcements-empty');
    var listEl = document.getElementById('announcements-list');
    var pagerEl = document.getElementById('announcements-pager');
    var prevButton = document.getElementById('announcements-prev-button');
    var nextButton = document.getElementById('announcements-next-button');
    var pageIndicatorEl = document.getElementById('announcements-page-indicator');

    var currentPage = 1;

    function showFormMessage(text) {
        formMessageEl.textContent = text;
        formMessageEl.hidden = false;
    }

    function showListMessage(text) {
        listMessageEl.textContent = text;
        listMessageEl.hidden = false;
    }

    function clearFieldErrors() {
        ['title', 'body'].forEach(function (field) {
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

    function formatPublishedAt(dateString) {
        var date = new Date(dateString);
        var hours = ('0' + date.getHours()).slice(-2);
        var minutes = ('0' + date.getMinutes()).slice(-2);
        return (date.getMonth() + 1) + '月' + date.getDate() + '日 ' + hours + ':' + minutes;
    }

    function renderPresetButtons() {
        PRESET_PHRASES.forEach(function (phrase) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'announcements-page__preset-button';
            button.textContent = phrase;
            // タイトル欄に文言を入れるだけで、本文は変更せず、投稿もしない。
            button.addEventListener('click', function () {
                titleInput.value = phrase;
            });
            presetButtonsEl.appendChild(button);
        });
    }

    function resetForm() {
        form.reset();
        idInput.value = '';
        isPublishedCheckbox.checked = true;
        submitButton.textContent = '投稿する';
        cancelEditButton.hidden = true;
        clearFieldErrors();
    }

    function enterEditMode(announcement) {
        idInput.value = announcement.id;
        titleInput.value = announcement.title;
        bodyInput.value = announcement.body || '';
        isPublishedCheckbox.checked = announcement.is_published;
        submitButton.textContent = '更新する';
        cancelEditButton.hidden = false;
        clearFieldErrors();
        formMessageEl.hidden = true;
        form.scrollIntoView({ behavior: 'smooth' });
    }

    cancelEditButton.addEventListener('click', function () {
        resetForm();
    });

    function updatePager(pagination) {
        pagerEl.hidden = false;
        pageIndicatorEl.textContent = pagination.current_page + ' / ' + pagination.last_page;
        prevButton.disabled = !pagination.prev_page_url;
        nextButton.disabled = !pagination.next_page_url;
    }

    function setCardBusy(card, busy) {
        var controls = card.querySelectorAll('button');
        for (var i = 0; i < controls.length; i++) {
            controls[i].disabled = busy;
        }
    }

    function showCardMessage(card, text) {
        var messageEl = card.querySelector('.announcement-card__message');
        messageEl.textContent = text;
        messageEl.hidden = false;
    }

    /**
     * 削除後、今のページに表示していたお知らせが無くなった場合は前のページへ戻る。
     * それ以外は今のページを再読み込みするだけでよい。
     */
    function deleteAnnouncement(card, announcementId) {
        setCardBusy(card, true);

        fetch(announcementsUrl + '/' + announcementId, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        }).then(function (response) {
            if (response.status === 401) {
                showListMessage('ログインの有効期限が切れています。もう一度ログインしてください。');
                setCardBusy(card, false);
                return null;
            }
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return true;
        }).then(function (ok) {
            if (!ok) {
                return;
            }
            if (listEl.children.length === 1 && currentPage > 1) {
                currentPage -= 1;
            }
            loadAnnouncements();
        }).catch(function () {
            showCardMessage(card, '削除に失敗しました。時間をおいてもう一度お試しください。');
            setCardBusy(card, false);
        });
    }

    function togglePublish(card, announcement) {
        setCardBusy(card, true);

        fetch(announcementsUrl + '/' + announcement.id, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ is_published: !announcement.is_published })
        }).then(function (response) {
            if (response.status === 401) {
                showListMessage('ログインの有効期限が切れています。もう一度ログインしてください。');
                setCardBusy(card, false);
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
            loadAnnouncements();
        }).catch(function () {
            showCardMessage(card, '公開状態の切り替えに失敗しました。時間をおいてもう一度お試しください。');
            setCardBusy(card, false);
        });
    }

    function buildCard(announcement) {
        var card = document.createElement('div');
        card.className = 'announcement-card';

        var titleLine = document.createElement('p');
        titleLine.className = 'announcement-card__title-line';

        var titleEl = document.createElement('span');
        titleEl.className = 'announcement-card__title';
        titleEl.textContent = announcement.title;
        titleLine.appendChild(titleEl);

        var badge = document.createElement('span');
        badge.className = 'announcement-card__badge '
            + (announcement.is_published ? 'announcement-card__badge--published' : 'announcement-card__badge--unpublished');
        badge.textContent = announcement.is_published ? '公開中' : '非公開';
        titleLine.appendChild(badge);

        card.appendChild(titleLine);

        var dateEl = document.createElement('p');
        dateEl.className = 'announcement-card__date';
        dateEl.textContent = formatPublishedAt(announcement.published_at);
        card.appendChild(dateEl);

        if (announcement.body) {
            var bodyEl = document.createElement('p');
            bodyEl.className = 'announcement-card__body';
            bodyEl.textContent = announcement.body;
            card.appendChild(bodyEl);
        }

        var actionsEl = document.createElement('div');
        actionsEl.className = 'announcement-card__actions';

        var editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.textContent = '編集';
        editButton.addEventListener('click', function () {
            enterEditMode(announcement);
        });
        actionsEl.appendChild(editButton);

        var toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.textContent = announcement.is_published ? '非公開にする' : '公開する';
        toggleButton.addEventListener('click', function () {
            togglePublish(card, announcement);
        });
        actionsEl.appendChild(toggleButton);

        var deleteButton = document.createElement('button');
        deleteButton.type = 'button';
        deleteButton.className = 'announcement-card__delete-button';
        deleteButton.textContent = '削除';
        deleteButton.addEventListener('click', function () {
            if (!window.confirm('「' + announcement.title + '」を削除します。よろしいですか?')) {
                return;
            }
            deleteAnnouncement(card, announcement.id);
        });
        actionsEl.appendChild(deleteButton);

        card.appendChild(actionsEl);

        var cardMessageEl = document.createElement('p');
        cardMessageEl.className = 'announcement-card__message message message-error';
        cardMessageEl.hidden = true;
        card.appendChild(cardMessageEl);

        return card;
    }

    function loadAnnouncements() {
        loadingEl.hidden = false;
        listMessageEl.hidden = true;
        emptyEl.hidden = true;
        pagerEl.hidden = true;
        listEl.innerHTML = '';

        fetch(announcementsUrl + '?page=' + currentPage, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (response.status === 401) {
                loadingEl.hidden = true;
                showListMessage('ログインの有効期限が切れています。もう一度ログインしてください。');
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

            var pagination = data.announcements;
            var announcements = pagination.data || [];

            if (announcements.length === 0) {
                emptyEl.hidden = false;
                return;
            }

            announcements.forEach(function (announcement) {
                listEl.appendChild(buildCard(announcement));
            });

            updatePager(pagination);
        }).catch(function () {
            loadingEl.hidden = true;
            showListMessage('お知らせ一覧の取得に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (submitButton.disabled) {
            return;
        }
        submitButton.disabled = true;
        formMessageEl.hidden = true;
        clearFieldErrors();

        var isEditing = !!idInput.value;
        var payload = {
            title: titleInput.value,
            body: bodyInput.value || null,
            is_published: isPublishedCheckbox.checked
        };

        var url = isEditing ? announcementsUrl + '/' + idInput.value : announcementsUrl;
        var method = isEditing ? 'PUT' : 'POST';

        fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        }).then(function (response) {
            if (response.status === 401) {
                showFormMessage('ログインの有効期限が切れています。もう一度ログインしてください。');
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
            resetForm();
            if (isEditing) {
                loadAnnouncements();
            } else {
                currentPage = 1;
                loadAnnouncements();
            }
        }).catch(function () {
            showFormMessage('送信に失敗しました。時間をおいてもう一度お試しください。');
        }).finally(function () {
            submitButton.disabled = false;
        });
    });

    prevButton.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage -= 1;
            loadAnnouncements();
        }
    });

    nextButton.addEventListener('click', function () {
        currentPage += 1;
        loadAnnouncements();
    });

    renderPresetButtons();
    loadAnnouncements();
})();
