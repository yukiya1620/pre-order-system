(function () {
    var container = document.querySelector('.products-page');
    if (!container) {
        return;
    }

    var productsUrl = container.dataset.productsUrl;
    var productEditBaseUrl = container.dataset.productEditBaseUrl;
    var loadingEl = document.getElementById('products-loading');
    var generalMessageEl = document.getElementById('products-message');
    var emptyEl = document.getElementById('products-empty');
    var listEl = document.getElementById('products-list');
    var filterSelect = document.getElementById('products-status-filter');

    var saleStatusClasses = {
        '準備中': 'product-sale-status--preparing',
        '販売中': 'product-sale-status--on-sale',
        '売り切れ': 'product-sale-status--sold-out',
        '販売終了': 'product-sale-status--ended'
    };

    var allProducts = [];

    function showGeneralMessage(text) {
        generalMessageEl.textContent = text;
        generalMessageEl.hidden = false;
    }

    function formatDate(dateString) {
        var date = new Date(dateString);
        return (date.getMonth() + 1) + '月' + date.getDate() + '日';
    }

    /**
     * 画像が無い、または読み込みに失敗した場合は必ずプレースホルダーを表示する
     * (壊れた画像アイコンのまま放置しない)。
     */
    function buildImageElement(product) {
        var wrap = document.createElement('div');
        wrap.className = 'product-card__image-wrap';

        function showPlaceholder() {
            wrap.innerHTML = '';
            var placeholder = document.createElement('div');
            placeholder.className = 'product-card__image-placeholder';
            placeholder.textContent = '画像なし';
            wrap.appendChild(placeholder);
        }

        if (!product.image) {
            showPlaceholder();
            return wrap;
        }

        var img = document.createElement('img');
        img.className = 'product-card__image';
        img.src = '/storage/' + product.image;
        img.alt = product.name;
        img.addEventListener('error', showPlaceholder);
        wrap.appendChild(img);

        return wrap;
    }

    function buildSaleInfo(sale) {
        var wrap = document.createElement('div');
        wrap.className = 'product-card__sale';

        if (!sale) {
            var noneEl = document.createElement('p');
            noneEl.className = 'product-card__sale-none';
            noneEl.textContent = '販売設定なし';
            wrap.appendChild(noneEl);
            return wrap;
        }

        var badge = document.createElement('span');
        badge.className = 'product-sale-status-badge ' + (saleStatusClasses[sale.status] || '');
        badge.textContent = sale.status;
        wrap.appendChild(badge);

        var priceEl = document.createElement('p');
        priceEl.textContent = '価格: ¥' + Number(sale.price).toLocaleString();
        wrap.appendChild(priceEl);

        var stockEl = document.createElement('p');
        stockEl.textContent = '残り在庫: ' + sale.stock_quantity;
        wrap.appendChild(stockEl);

        var periodEl = document.createElement('p');
        periodEl.textContent = '販売期間: ' + formatDate(sale.sale_start_date) + ' 〜 ' + formatDate(sale.sale_end_date);
        wrap.appendChild(periodEl);

        return wrap;
    }

    function buildCard(product) {
        var card = document.createElement('div');
        card.className = 'product-card';

        card.appendChild(buildImageElement(product));

        var infoEl = document.createElement('div');
        infoEl.className = 'product-card__info';

        var nameLine = document.createElement('p');
        nameLine.className = 'product-card__name-line';

        var nameEl = document.createElement('span');
        nameEl.className = 'product-card__name';
        nameEl.textContent = product.name;
        nameLine.appendChild(nameEl);

        if (product.is_archived) {
            var archivedBadge = document.createElement('span');
            archivedBadge.className = 'product-card__archived-badge';
            archivedBadge.textContent = '非表示';
            nameLine.appendChild(archivedBadge);
        }
        infoEl.appendChild(nameLine);

        var categoryEl = document.createElement('p');
        categoryEl.className = 'product-card__category';
        categoryEl.textContent = 'カテゴリー: ' + (product.category ? product.category.name : '未設定') + ' / 単位: ' + product.unit_label;
        infoEl.appendChild(categoryEl);

        infoEl.appendChild(buildSaleInfo(product.latest_product_sale));

        var actionsEl = document.createElement('div');
        actionsEl.className = 'product-card__actions';

        var editLink = document.createElement('a');
        editLink.className = 'product-card__edit-link';
        editLink.href = productEditBaseUrl + '/' + product.id + '/edit';
        editLink.textContent = '商品を編集 ▶';
        actionsEl.appendChild(editLink);

        var resellItem = document.createElement('div');
        resellItem.className = 'orders-page__unimplemented-item';
        resellItem.setAttribute('aria-disabled', 'true');
        resellItem.textContent = '去年の商品から再販売 準備中';
        actionsEl.appendChild(resellItem);

        infoEl.appendChild(actionsEl);
        card.appendChild(infoEl);

        return card;
    }

    function matchesFilter(product) {
        var filter = filterSelect.value;
        if (filter === 'visible') {
            return !product.is_archived;
        }
        if (filter === 'archived') {
            return product.is_archived;
        }
        return true;
    }

    function render() {
        listEl.innerHTML = '';

        var filtered = allProducts.filter(matchesFilter);

        if (filtered.length === 0) {
            emptyEl.hidden = false;
            return;
        }
        emptyEl.hidden = true;

        filtered.forEach(function (product) {
            listEl.appendChild(buildCard(product));
        });
    }

    function loadProducts() {
        fetch(productsUrl, {
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
            allProducts = data.products || [];
            render();
        }).catch(function () {
            loadingEl.hidden = true;
            showGeneralMessage('商品一覧の取得に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    filterSelect.addEventListener('change', render);

    loadProducts();
})();
