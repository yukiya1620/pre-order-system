(function () {
    var container = document.querySelector('.product-resell-page');
    if (!container) {
        return;
    }

    var productApiUrl = container.dataset.productApiUrl;
    var salesApiUrl = container.dataset.salesApiUrl;
    var productsPageUrl = container.dataset.productsPageUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    var loadingEl = document.getElementById('product-resell-loading');
    var generalMessageEl = document.getElementById('product-resell-message');
    var form = document.getElementById('product-resell-form');
    var productNameEl = document.getElementById('product-resell-name');
    var activeWarningEl = document.getElementById('product-resell-active-warning');
    var previousEl = document.getElementById('product-resell-previous');

    var priceInput = document.getElementById('price');
    var stockInput = document.getElementById('stock_quantity');
    var saleStartInput = document.getElementById('sale_start_date');
    var saleEndInput = document.getElementById('sale_end_date');
    var deliveryFromInput = document.getElementById('delivery_date_from');
    var deliveryToInput = document.getElementById('delivery_date_to');
    var submitButton = document.getElementById('submit-button');

    var fieldNames = ['price', 'stock_quantity', 'sale_start_date', 'sale_end_date', 'delivery_date_from', 'delivery_date_to'];
    var productName = '';

    function showGeneralMessage(text) {
        generalMessageEl.textContent = text;
        generalMessageEl.hidden = false;
    }

    function clearFieldErrors() {
        fieldNames.forEach(function (field) {
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

    function formatDate(dateString) {
        var date = new Date(dateString);
        return (date.getMonth() + 1) + '月' + date.getDate() + '日';
    }

    function formatDateRange(fromString, toString) {
        if (!toString) {
            return formatDate(fromString);
        }
        return formatDate(fromString) + ' 〜 ' + formatDate(toString);
    }

    function loadProduct() {
        fetch(productApiUrl, {
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
            var product = data.product;
            productName = product.name;
            productNameEl.textContent = product.name;

            var sale = product.latest_product_sale;
            if (sale) {
                previousEl.hidden = false;
                document.getElementById('previous-price').textContent = '¥' + Number(sale.price).toLocaleString();
                document.getElementById('previous-stock').textContent = sale.initial_stock;
                document.getElementById('previous-sale-period').textContent = formatDateRange(sale.sale_start_date, sale.sale_end_date);
                document.getElementById('previous-delivery-period').textContent = formatDateRange(sale.delivery_date_from, sale.delivery_date_to);

                priceInput.value = sale.price;
                stockInput.value = sale.initial_stock;

                if (sale.status === '準備中' || sale.status === '販売中') {
                    activeWarningEl.hidden = false;
                }
            }

            loadingEl.hidden = true;
            form.hidden = false;
        }).catch(function () {
            loadingEl.hidden = true;
            showGeneralMessage('商品情報の取得に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (submitButton.disabled) {
            return;
        }

        var saleStartLabel = saleStartInput.value ? formatDate(saleStartInput.value) : '';
        var saleEndLabel = saleEndInput.value ? formatDate(saleEndInput.value) : '';
        var deliveryFromLabel = deliveryFromInput.value ? formatDate(deliveryFromInput.value) : '';
        var deliveryToLabel = deliveryToInput.value ? formatDate(deliveryToInput.value) : '(単日)';

        var confirmText = '商品名: ' + productName
            + ' / 価格: ¥' + Number(priceInput.value || 0).toLocaleString()
            + ' / 在庫: ' + stockInput.value + '\n'
            + '販売期間: ' + saleStartLabel + ' 〜 ' + saleEndLabel + '\n'
            + '配達期間: ' + deliveryFromLabel + ' 〜 ' + deliveryToLabel + '\n'
            + 'この内容で販売を開始してよろしいですか?';

        if (!window.confirm(confirmText)) {
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = '送信しています…';
        generalMessageEl.hidden = true;
        clearFieldErrors();

        var payload = {
            price: priceInput.value,
            stock_quantity: stockInput.value,
            sale_start_date: saleStartInput.value,
            sale_end_date: saleEndInput.value,
            delivery_date_from: deliveryFromInput.value,
            delivery_date_to: deliveryToInput.value || null,
            is_reservation_open: 1
        };

        fetch(salesApiUrl, {
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
                submitButton.disabled = false;
                submitButton.textContent = 'この内容で販売を開始';
                return;
            }
            window.location.href = productsPageUrl;
        }).catch(function () {
            showGeneralMessage('送信に失敗しました。時間をおいてもう一度お試しください。');
            submitButton.disabled = false;
            submitButton.textContent = 'この内容で販売を開始';
        });
    });

    loadProduct();
})();
