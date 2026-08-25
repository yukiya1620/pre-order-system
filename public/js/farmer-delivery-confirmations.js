(function () {
    var container = document.querySelector('.confirmation-page');
    if (!container) {
        return;
    }

    var listUrl = container.dataset.deliveryConfirmationsUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var loadingEl = document.getElementById('confirmation-loading');
    var generalMessageEl = document.getElementById('confirmation-message');
    var emptyEl = document.getElementById('confirmation-empty');
    var listEl = document.getElementById('confirmation-list');

    var responseLabels = {
        '配達可能': '予定どおり配達できる',
        '数量変更': '数量を変更する',
        'キャンセル相談': 'キャンセルの相談をする'
    };

    function showGeneralMessage(text) {
        generalMessageEl.textContent = text;
        generalMessageEl.hidden = false;
    }

    function formatDeliveryDate(dateString) {
        var date = new Date(dateString);
        return (date.getMonth() + 1) + '月' + date.getDate() + '日';
    }

    function showEmptyIfNoCards() {
        if (listEl.children.length === 0) {
            emptyEl.hidden = false;
        }
    }

    function setCardBusy(card, busy) {
        var buttons = card.querySelectorAll('button');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].disabled = busy;
        }
    }

    function showCardMessage(card, text) {
        var messageEl = card.querySelector('.confirmation-card__message');
        messageEl.textContent = text;
        messageEl.hidden = false;
    }

    /**
     * 回答を送信する。成功したらカードごと画面から取り除く
     * (回答済みの配達確認は再回答できないため、一覧に残す意味が無い)。
     */
    function submitResponse(card, id, payload) {
        setCardBusy(card, true);

        fetch(listUrl + '/' + id + '/respond', {
            method: 'POST',
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
                        || 'この回答は送信できませんでした。画面を確認してください。';
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
            card.remove();
            showEmptyIfNoCards();
        }).catch(function () {
            showCardMessage(card, '回答の送信に失敗しました。時間をおいてもう一度お試しください。');
            setCardBusy(card, false);
        });
    }

    function buildActionButton(label, onClick) {
        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.addEventListener('click', onClick);
        return button;
    }

    function buildCard(confirmation) {
        var order = confirmation.order;
        var items = (order.order_items || []).map(function (item) {
            return item.product_name + ' ' + item.quantity + '袋';
        }).join(' / ');

        var card = document.createElement('div');
        card.className = 'confirmation-card';

        // 一般公開デモの操作チュートリアル向けに、注文内容(日付・購入者・
        // 商品・お届け先)だけをまとめたラッパー。回答ボタン群を含まない
        // コンパクトな領域をスポットライト対象にできるようにするためだけの
        // 変更で、見た目(app.cssのクラス・スタイル)や回答処理には影響しない。
        var summaryEl = document.createElement('div');
        summaryEl.className = 'confirmation-card__summary';
        card.appendChild(summaryEl);

        var dateEl = document.createElement('p');
        dateEl.className = 'confirmation-card__date';
        dateEl.textContent = formatDeliveryDate(order.delivery_date) + 'に配達予定の注文';
        summaryEl.appendChild(dateEl);

        var buyerEl = document.createElement('p');
        buyerEl.className = 'confirmation-card__buyer';
        buyerEl.textContent = order.user.name + ' 様';
        summaryEl.appendChild(buyerEl);

        var itemsEl = document.createElement('p');
        itemsEl.className = 'confirmation-card__items';
        itemsEl.textContent = items;
        summaryEl.appendChild(itemsEl);

        var addressEl = document.createElement('p');
        addressEl.className = 'confirmation-card__address';
        addressEl.textContent = 'お届け先: ' + order.delivery_address;
        summaryEl.appendChild(addressEl);

        var questionEl = document.createElement('p');
        questionEl.className = 'confirmation-card__question';
        questionEl.textContent = 'この注文はどうしますか?';
        card.appendChild(questionEl);

        var actionsEl = document.createElement('div');
        actionsEl.className = 'confirmation-card__actions';

        var deliverableButton = buildActionButton('✔ 予定どおり配達できる', function () {
            if (!window.confirm('この注文を「' + responseLabels['配達可能'] + '」で回答します。よろしいですか?')) {
                return;
            }
            submitResponse(card, confirmation.id, { response: '配達可能' });
        });
        deliverableButton.dataset.response = '配達可能';
        actionsEl.appendChild(deliverableButton);

        var changeDateButton = buildActionButton('📅 配達日を変更する', function () {
            datePicker.hidden = !datePicker.hidden;
        });
        actionsEl.appendChild(changeDateButton);

        var datePicker = document.createElement('div');
        datePicker.className = 'confirmation-card__date-picker';
        datePicker.hidden = true;

        var dateInput = document.createElement('input');
        dateInput.type = 'date';
        datePicker.appendChild(dateInput);

        var confirmDateButton = buildActionButton('決定', function () {
            if (!dateInput.value) {
                showCardMessage(card, '新しい配達予定日を選んでください。');
                return;
            }
            if (!window.confirm('配達日を' + dateInput.value + 'に変更して回答します。よろしいですか?')) {
                return;
            }
            submitResponse(card, confirmation.id, {
                response: '配達日変更',
                new_delivery_date: dateInput.value
            });
        });
        datePicker.appendChild(confirmDateButton);
        actionsEl.appendChild(datePicker);

        actionsEl.appendChild(buildActionButton('🔢 数量を変更する', function () {
            if (!window.confirm('この注文を「' + responseLabels['数量変更'] + '」で回答します。よろしいですか?')) {
                return;
            }
            submitResponse(card, confirmation.id, { response: '数量変更' });
        }));

        actionsEl.appendChild(buildActionButton('☎ キャンセルの相談をする', function () {
            if (!window.confirm('この注文を「' + responseLabels['キャンセル相談'] + '」で回答します。よろしいですか?')) {
                return;
            }
            submitResponse(card, confirmation.id, { response: 'キャンセル相談' });
        }));

        card.appendChild(actionsEl);

        var noteEl = document.createElement('p');
        noteEl.className = 'confirmation-card__note';
        noteEl.textContent = '※選ぶと、お客様へ自動でお知らせが送られます';
        card.appendChild(noteEl);

        var cardMessageEl = document.createElement('p');
        cardMessageEl.className = 'confirmation-card__message message message-error';
        cardMessageEl.hidden = true;
        card.appendChild(cardMessageEl);

        return card;
    }

    function loadConfirmations() {
        fetch(listUrl, {
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

            var confirmations = data.delivery_confirmations || [];
            if (confirmations.length === 0) {
                emptyEl.hidden = false;
                return;
            }

            confirmations.forEach(function (confirmation, index) {
                var card = buildCard(confirmation);
                // 一般公開デモの操作チュートリアル向けに、最初のカードだけ
                // 対象の目印を付ける(buyer-home.jsの商品カードと同じ考え方)。
                // 390px幅でtooltipが画面外にはみ出さないよう、カード全体
                // (回答ボタン群を含む縦長の領域)ではなく、注文内容の
                // まとめ(confirmation-card__summary)だけを対象にする。
                // 回答ボタン等の既存動作には一切影響しない。
                if (index === 0) {
                    var summaryEl = card.querySelector('.confirmation-card__summary');
                    summaryEl.dataset.demoTutorial = 'farmer-delivery-confirmation-card';
                }
                listEl.appendChild(card);
            });

            // 一般公開デモの操作チュートリアル向けに、カードの描画が完了した
            // ことを知らせる。チュートリアル(DEMO_MODE=trueのときだけ読み込まれる)は
            // このイベントを待ってから対象要素を探すことで、setTimeoutの秒数
            // 決め打ちに頼らずに済む。チュートリアルを読み込んでいない画面でも、
            // 発火自体は無害(リスナーが無いだけ)。
            document.dispatchEvent(new CustomEvent('demo-tutorial:farmer-delivery-confirmations-rendered'));
        }).catch(function () {
            loadingEl.hidden = true;
            showGeneralMessage('注文の取得に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    loadConfirmations();
})();
