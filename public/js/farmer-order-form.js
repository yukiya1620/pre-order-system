(function () {
    var container = document.querySelector('.order-form-page');
    if (!container) {
        return;
    }

    var productsUrl = container.dataset.productsUrl;
    var ordersApiUrl = container.dataset.ordersApiUrl;
    var orderDetailBaseUrl = container.dataset.orderDetailBaseUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    var loadingEl = document.getElementById('order-form-loading');
    var generalMessageEl = document.getElementById('order-form-message');
    var form = document.getElementById('order-form');
    var registrationFieldsEl = document.getElementById('registration-fields');
    var phoneInput = document.getElementById('phone-number');
    var nameInput = document.getElementById('buyer-name');
    var addressInput = document.getElementById('buyer-address');
    var productSelect = document.getElementById('product-sale');
    var quantityInput = document.getElementById('quantity');
    var estimateEl = document.getElementById('order-form-estimate');
    var deliveryTimeSlotSelect = document.getElementById('delivery-time-slot');
    var paymentMethodSelect = document.getElementById('payment-method');
    var paymentStatusSelect = document.getElementById('payment-status');
    var proxyNoteInput = document.getElementById('proxy-note');
    var submitButton = document.getElementById('order-form-submit-button');

    var successEl = document.getElementById('order-form-success');
    var successOrderNumberEl = document.getElementById('success-order-number');
    var successDeliveryDateEl = document.getElementById('success-delivery-date');
    var successOrderDetailLink = document.getElementById('success-order-detail-link');
    var continueButton = document.getElementById('order-form-continue-button');

    var paymentMethodLabels = { cash: '現金', card: 'カード', paypay: 'PayPay' };
    var paymentStatusLabels = { unpaid: '未払い', paid: '支払い済み' };

    var productSales = {};

    function showGeneralMessage(text) {
        generalMessageEl.textContent = text;
        generalMessageEl.hidden = false;
    }

    function clearFieldErrors() {
        ['phone_number', 'name', 'address', 'product_sale_id', 'quantity', 'proxy_note'].forEach(function (field) {
            var el = document.getElementById(field + '-error');
            if (el) {
                el.textContent = '';
                el.hidden = true;
            }
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

    function formatDate(dateString) {
        var date = new Date(dateString);
        return (date.getMonth() + 1) + '月' + date.getDate() + '日';
    }

    /**
     * 現在時刻を日本時間(Asia/Tokyo)基準で取得する。ブラウザの設定時刻がJST以外でも
     * 正しく計算できるよう、Intl.DateTimeFormatでタイムゾーンを明示する。
     */
    function getJstParts() {
        var formatter = new Intl.DateTimeFormat('en-CA', {
            timeZone: 'Asia/Tokyo',
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
        });
        var parts = {};
        formatter.formatToParts(new Date()).forEach(function (part) {
            if (part.type !== 'literal') {
                parts[part.type] = part.value;
            }
        });
        return parts;
    }

    /**
     * 配達予定日の目安を計算する(設計書3.5と同じロジックをJS側に再現)。
     * - fixed: delivery_date_from をそのまま使う
     * - auto: 今日(JST) + earliest_delivery_days。締切時刻(order_deadline_time)を過ぎていたら+1日
     * あくまで確認ダイアログ表示用の目安であり、正式な配達予定日はAPIが確定した値を使う。
     */
    function estimateDeliveryDate(sale) {
        if (sale.delivery_date_type === 'fixed') {
            return sale.delivery_date_from;
        }

        var parts = getJstParts();
        var days = sale.earliest_delivery_days || 0;
        var currentTime = parts.hour + ':' + parts.minute + ':' + parts.second;

        if (sale.order_deadline_time && currentTime > sale.order_deadline_time) {
            days += 1;
        }

        // 日付計算のズレを避けるため、JSTの「今日」をUTC基準のDateとして扱い、日数だけ加算する
        var estimated = new Date(Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day)));
        estimated.setUTCDate(estimated.getUTCDate() + days);

        return estimated.toISOString().slice(0, 10);
    }

    function updateEstimate() {
        var sale = productSales[productSelect.value];
        if (!sale) {
            estimateEl.hidden = true;
            return;
        }

        var estimatedDate = estimateDeliveryDate(sale);
        estimateEl.textContent = '配達予定日(目安): ' + formatDate(estimatedDate) + '  ※正式な配達予定日は注文確定後に表示されます';
        estimateEl.hidden = false;
    }

    function buildProductOptionLabel(sale) {
        var unit = sale.product.unit_label || '個';
        return sale.product.name + '(' + unit + ') ¥' + Number(sale.price).toLocaleString() + ' / 残り' + sale.stock_quantity + unit;
    }

    function loadProducts() {
        fetch(productsUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            var sales = data.products || [];

            if (sales.length === 0) {
                loadingEl.hidden = true;
                showGeneralMessage('現在注文できる商品がありません。商品管理から販売設定を行ってください。');
                return;
            }

            sales.forEach(function (sale) {
                productSales[sale.id] = sale;

                var option = document.createElement('option');
                option.value = sale.id;
                option.textContent = buildProductOptionLabel(sale);
                productSelect.appendChild(option);
            });

            loadingEl.hidden = true;
            form.hidden = false;
            updateEstimate();
        }).catch(function () {
            loadingEl.hidden = true;
            showGeneralMessage('商品情報の取得に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    productSelect.addEventListener('change', updateEstimate);
    quantityInput.addEventListener('change', updateEstimate);

    function buildConfirmMessage(payload, sale, isNewBuyer) {
        var lines = [];
        lines.push('電話番号: ' + payload.phone_number);

        if (isNewBuyer) {
            lines.push('お名前: ' + payload.name + ' 様(新規登録)');
            lines.push('ご住所: ' + payload.address);
        }

        var unit = sale.product.unit_label || '個';
        var subtotal = sale.price * payload.quantity;

        lines.push('商品: ' + sale.product.name + ' × ' + payload.quantity + unit);
        lines.push('合計金額: ¥' + subtotal.toLocaleString());
        lines.push('配達予定日(目安): ' + formatDate(estimateDeliveryDate(sale)) + '  ※正式な配達予定日は注文確定後に表示されます');
        lines.push('配達時間帯: ' + (payload.delivery_time_slot || '指定なし'));
        lines.push('支払い方法: ' + (paymentMethodLabels[payload.payment_method] || '未選択')
            + ' / 支払い状況: ' + (paymentStatusLabels[payload.payment_status] || payload.payment_status));

        if (payload.proxy_note) {
            lines.push('メモ: ' + payload.proxy_note);
        }

        lines.push('');
        lines.push('この内容で注文を確定しますか?');

        return lines.join('\n');
    }

    function resetForm() {
        form.reset();
        registrationFieldsEl.hidden = true;
        paymentStatusSelect.value = 'unpaid';
        clearFieldErrors();
        generalMessageEl.hidden = true;
        estimateEl.hidden = true;
        updateEstimate();
    }

    function showSuccess(order) {
        form.hidden = true;
        successEl.hidden = false;
        successOrderNumberEl.textContent = order.order_number;
        successOrderDetailLink.href = orderDetailBaseUrl + '/' + order.id;

        if (order.delivery_date) {
            successDeliveryDateEl.textContent = formatDate(order.delivery_date);
        } else {
            fetchDeliveryDate(order.id);
        }
    }

    /**
     * 注文作成レスポンスにdelivery_dateが含まれない場合の保険。
     * 注文詳細APIから正式な配達予定日を取り直す。
     */
    function fetchDeliveryDate(orderId) {
        successDeliveryDateEl.textContent = '確認しています…';

        fetch(ordersApiUrl + '/' + orderId, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            if (data && data.order && data.order.delivery_date) {
                successDeliveryDateEl.textContent = formatDate(data.order.delivery_date);
            } else {
                successDeliveryDateEl.textContent = '取得できませんでした(注文詳細でご確認ください)';
            }
        }).catch(function () {
            successDeliveryDateEl.textContent = '取得できませんでした(注文詳細でご確認ください)';
        });
    }

    continueButton.addEventListener('click', function () {
        successEl.hidden = true;
        form.hidden = false;
        resetForm();
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (submitButton.disabled) {
            return;
        }

        var sale = productSales[productSelect.value];
        if (!sale) {
            showGeneralMessage('商品を選択してください。');
            return;
        }

        var isNewBuyer = !registrationFieldsEl.hidden;

        var payload = {
            phone_number: phoneInput.value,
            product_sale_id: Number(productSelect.value),
            quantity: Number(quantityInput.value),
            delivery_time_slot: deliveryTimeSlotSelect.value || null,
            payment_method: paymentMethodSelect.value || null,
            payment_status: paymentStatusSelect.value || null,
            proxy_note: proxyNoteInput.value || null
        };

        if (isNewBuyer) {
            payload.name = nameInput.value;
            payload.address = addressInput.value;
        }

        if (!window.confirm(buildConfirmMessage(payload, sale, isNewBuyer))) {
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = '送信しています…';
        generalMessageEl.hidden = true;
        clearFieldErrors();

        fetch(ordersApiUrl, {
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
                return null;
            }
            if (response.status === 422) {
                return response.json().then(function (data) {
                    if (data.error) {
                        if (data.error.code === 'REGISTRATION_INFO_REQUIRED') {
                            registrationFieldsEl.hidden = false;
                            showGeneralMessage('初めての購入者です。お名前とご住所を入力してから、もう一度送信してください。');
                            nameInput.focus();
                        } else {
                            showGeneralMessage(data.error.message || 'この注文は登録できませんでした。内容をご確認ください。');
                        }
                    } else if (data.errors) {
                        showFieldErrors(data.errors);
                    }
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
            showSuccess(data.order);
        }).catch(function () {
            showGeneralMessage('送信に失敗しました。時間をおいてもう一度お試しください。');
        }).finally(function () {
            submitButton.disabled = false;
            submitButton.textContent = 'この内容で注文する';
        });
    });

    loadProducts();
})();
