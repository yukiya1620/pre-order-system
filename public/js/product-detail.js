(function () {
    var container = document.querySelector('.product-detail-page');
    if (!container) {
        return;
    }

    var productUrl = container.dataset.productUrl;
    var orderConfirmBaseUrl = container.dataset.orderConfirmBaseUrl;
    var loadingEl = document.getElementById('product-detail-loading');
    var messageEl = document.getElementById('product-detail-message');
    var contentEl = document.getElementById('product-detail-content');
    var quantityInput = document.getElementById('product-detail-quantity');
    var timeSlotSelect = document.getElementById('product-detail-time-slot');
    var deliveryDateFieldEl = document.getElementById('product-detail-delivery-date-field');
    var deliveryDateSelect = document.getElementById('product-detail-delivery-date-select');

    function showMessage(text) {
        messageEl.textContent = text;
        messageEl.hidden = false;
    }

    function formatDate(dateString) {
        var parts = dateString.split('-');
        return Number(parts[1]) + '月' + Number(parts[2]) + '日';
    }

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    /**
     * "YYYY-MM-DD"を年月日に分解して、その年月日だけを見たローカルのDateを作る。
     * new Date("YYYY-MM-DD")は仕様上UTC 0時として解釈されるため、日本時間では
     * 前日にずれて見えることがある(既知の罠)。1日ずつ数え上げるためだけに使うので、
     * タイムゾーンに影響されない年月日ベースで組み立てる。
     */
    function dateFromParts(dateString) {
        var parts = dateString.split('-').map(Number);
        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    function toDateString(date) {
        return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
    }

    /**
     * 配達予定期間(from〜to、両端を含む)の全日付を<option>として並べる。
     * 最初にvalue=""の空の選択肢(「配達予定日を選択してください」)を置き、
     * 購入者が自分で選ぶまでは未選択の状態にする(期間の最初の日を勝手に選んでおくと、
     * 選ばずに進めてしまう・意図しない日で注文してしまう恐れがあるため)。
     */
    function renderDeliveryDateOptions(fromStr, toStr) {
        deliveryDateSelect.innerHTML = '';

        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '配達予定日を選択してください';
        deliveryDateSelect.appendChild(placeholder);

        var current = dateFromParts(fromStr);
        var end = dateFromParts(toStr);

        while (current <= end) {
            var value = toDateString(current);
            var option = document.createElement('option');
            option.value = value;
            option.textContent = formatDate(value);
            deliveryDateSelect.appendChild(option);
            current.setDate(current.getDate() + 1);
        }

        // 先頭(空の選択肢)を明示的に選択状態にする(ブラウザの既定動作に頼らない)
        deliveryDateSelect.value = '';
    }

    /**
     * 画像が無い、または読み込みに失敗した場合は必ずプレースホルダーを表示する(B3・F5と同じ考え方)。
     */
    function renderImage(product) {
        var wrap = document.getElementById('product-detail-image-wrap');
        wrap.innerHTML = '';

        function showPlaceholder() {
            wrap.innerHTML = '';
            var placeholder = document.createElement('div');
            placeholder.className = 'product-detail__image-placeholder';
            placeholder.textContent = '画像なし';
            wrap.appendChild(placeholder);
        }

        if (!product.image) {
            showPlaceholder();
            return;
        }

        var img = document.createElement('img');
        img.className = 'product-detail__image';
        img.src = '/storage/' + product.image;
        img.alt = product.name;
        img.addEventListener('error', showPlaceholder);
        wrap.appendChild(img);
    }

    /**
     * 商品の状態を「色+アイコン+文字」の3点セットで表す(設計書5.1、B3と同じ判定ロジック)。
     * 注文可能条件はサーバー側(OrderPlacementService::availabilityError)と同じく、
     * status === '販売中' かつ is_reservation_open === true の場合のみ orderable: true にする。
     */
    function statusInfo(sale) {
        if (sale.product && sale.product.is_archived) {
            return { icon: '', label: '現在お取り扱いしていません', cls: 'product-status-badge--ended', orderable: false };
        }
        if (sale.status === '準備中') {
            return { icon: '⏳', label: '準備中', cls: 'product-status-badge--preparing', orderable: false };
        }
        if (sale.status === '販売中') {
            if (sale.is_reservation_open) {
                return { icon: '🟢', label: '予約受付中', cls: 'product-status-badge--open', orderable: true };
            }
            return { icon: '', label: '受付停止', cls: 'product-status-badge--closed', orderable: false };
        }
        if (sale.status === '売り切れ') {
            return { icon: '✕', label: '売り切れ', cls: 'product-status-badge--sold-out', orderable: false };
        }
        if (sale.status === '販売終了') {
            return { icon: '⏹', label: '販売終了', cls: 'product-status-badge--ended', orderable: false };
        }
        return { icon: '', label: '現在注文できません', cls: 'product-status-badge--ended', orderable: false };
    }

    function renderProduct(sale) {
        renderImage(sale.product);

        document.getElementById('product-detail-category').textContent = sale.product.category ? sale.product.category.name : '';
        document.getElementById('product-detail-name').textContent = sale.product.name;
        document.getElementById('product-detail-price').textContent = '¥' + Number(sale.price).toLocaleString();

        var status = statusInfo(sale);
        var badge = document.getElementById('product-detail-status-badge');
        badge.className = 'product-status-badge ' + status.cls;
        badge.textContent = '';
        if (status.icon) {
            var iconEl = document.createElement('span');
            iconEl.setAttribute('aria-hidden', 'true');
            iconEl.textContent = status.icon;
            badge.appendChild(iconEl);
            badge.appendChild(document.createTextNode(' '));
        }
        badge.appendChild(document.createTextNode(status.label));

        var stockEl = document.getElementById('product-detail-stock');
        if (status.orderable) {
            stockEl.textContent = '残り' + sale.stock_quantity + (sale.product.unit_label || '個');
            stockEl.hidden = false;
        } else {
            stockEl.hidden = true;
        }

        // 注文受付期間(sale_start_date〜sale_end_date)。配達予定日/配達予定期間とは別項目。
        document.getElementById('product-detail-order-period').textContent =
            formatDate(sale.sale_start_date) + '〜' + formatDate(sale.sale_end_date);

        if (sale.requires_delivery_date_selection) {
            document.getElementById('product-detail-delivery-date').textContent =
                formatDate(sale.delivery_date_from) + '〜' + formatDate(sale.delivery_date_to);
            renderDeliveryDateOptions(sale.delivery_date_from, sale.delivery_date_to);
            deliveryDateFieldEl.hidden = false;
        } else {
            document.getElementById('product-detail-delivery-date').textContent = formatDate(sale.delivery_date);
            deliveryDateFieldEl.hidden = true;
        }

        var noteEl = document.getElementById('product-detail-delivery-note');
        if (sale.delivery_note) {
            noteEl.textContent = '※ ' + sale.delivery_note;
            noteEl.hidden = false;
        } else {
            noteEl.hidden = true;
        }

        document.getElementById('product-detail-description').textContent = sale.product.description || '';

        // 在庫数を超える数量を選べないよう上限を設定する(0以下にはならない)
        quantityInput.max = String(Math.max(sale.stock_quantity, 1));

        var orderButton = document.getElementById('product-detail-order-button');
        if (orderButton) {
            if (!status.orderable) {
                orderButton.disabled = true;
            } else {
                orderButton.addEventListener('click', function () {
                    if (orderButton.disabled) {
                        return;
                    }

                    // 配達予定日の選択が必須な商品で、まだ選ばれていない(value==="")場合は
                    // 注文確認画面へ進ませない。サーバー側(preview/store)でも同じ理由で拒否されるが、
                    // ここで止めておくことで、送信して初めてエラーに気づく手戻りを防ぐ。
                    if (sale.requires_delivery_date_selection && !deliveryDateSelect.value) {
                        showMessage('配達予定日を選択してください。');
                        return;
                    }
                    messageEl.hidden = true;

                    // 価格・合計金額はURLに含めない。B5側でPOST /orders/previewを呼び、
                    // そのレスポンスを正として表示する。ただしdelivery_dateだけは、
                    // 購入者がここで選んだ「意思」そのものなのでURLに含めて引き継ぐ
                    // (選択不要な商品ではそもそもこの項目自体を表示していない)。
                    var params = new URLSearchParams();
                    params.set('product_sale_id', sale.id);
                    params.set('quantity', quantityInput.value || '1');
                    if (timeSlotSelect.value) {
                        params.set('delivery_time_slot', timeSlotSelect.value);
                    }
                    if (sale.requires_delivery_date_selection) {
                        params.set('delivery_date', deliveryDateSelect.value);
                    }

                    window.location.href = orderConfirmBaseUrl + '?' + params.toString();
                });
            }
        }
    }

    function loadProduct() {
        fetch(productUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (response.status === 404) {
                loadingEl.hidden = true;
                showMessage('指定された商品が見つかりませんでした。');
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
            renderProduct(data.product);
            contentEl.hidden = false;
        }).catch(function () {
            loadingEl.hidden = true;
            showMessage('商品情報の取得に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    loadProduct();
})();
