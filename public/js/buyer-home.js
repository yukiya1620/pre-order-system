(function () {
    var container = document.querySelector('.buyer-home-page');
    if (!container) {
        return;
    }

    var productsUrl = container.dataset.productsUrl;
    var announcementsUrl = container.dataset.announcementsUrl;
    var productDetailBaseUrl = container.dataset.productDetailBaseUrl;

    var generalMessageEl = document.getElementById('buyer-home-message');
    var announcementsLoadingEl = document.getElementById('announcements-loading');
    var announcementsEmptyEl = document.getElementById('announcements-empty');
    var announcementsListEl = document.getElementById('announcements-list');
    var categoryTabsEl = document.getElementById('category-tabs');
    var productsLoadingEl = document.getElementById('products-loading');
    var productsEmptyEl = document.getElementById('products-empty');
    var productsListEl = document.getElementById('products-list');
    var logoutButton = document.getElementById('buyer-home-logout-button');

    var allSales = [];
    var currentCategoryFilter = '';

    function showGeneralMessage(text) {
        generalMessageEl.textContent = text;
        generalMessageEl.hidden = false;
    }

    function formatDate(dateString) {
        var parts = dateString.split('-');
        return Number(parts[1]) + '月' + Number(parts[2]) + '日';
    }

    /**
     * 画像が無い、または読み込みに失敗した場合は必ずプレースホルダーを表示する(F5と同じ考え方)。
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

    /**
     * 商品の状態を「色+アイコン+文字」の3点セットで表す(設計書5.1、product-detail.jsと同じ判定ロジック)。
     * 注文可能条件はサーバー側(OrderPlacementService::availabilityError)と同じく、
     * status === '販売中' かつ is_reservation_open === true の場合のみ orderable: true にする。
     */
    function statusInfo(sale) {
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

    function buildCard(sale) {
        var card = document.createElement('div');
        card.className = 'product-card';

        card.appendChild(buildImageElement(sale.product));

        var infoEl = document.createElement('div');
        infoEl.className = 'product-card__info';

        var nameEl = document.createElement('p');
        nameEl.className = 'product-card__name';
        nameEl.textContent = sale.product.name;
        infoEl.appendChild(nameEl);

        var priceEl = document.createElement('p');
        priceEl.className = 'product-card__price';
        priceEl.textContent = '¥' + Number(sale.price).toLocaleString();
        infoEl.appendChild(priceEl);

        var status = statusInfo(sale);
        var badge = document.createElement('span');
        badge.className = 'product-status-badge ' + status.cls;
        if (status.icon) {
            var iconEl = document.createElement('span');
            iconEl.setAttribute('aria-hidden', 'true');
            iconEl.textContent = status.icon;
            badge.appendChild(iconEl);
            badge.appendChild(document.createTextNode(' '));
        }
        badge.appendChild(document.createTextNode(status.label));
        infoEl.appendChild(badge);

        if (status.orderable) {
            var stockEl = document.createElement('p');
            stockEl.className = 'product-card__stock';
            stockEl.textContent = '残り' + sale.stock_quantity + (sale.product.unit_label || '個');
            infoEl.appendChild(stockEl);
        }

        var deliveryEl = document.createElement('p');
        deliveryEl.className = 'product-card__delivery';
        deliveryEl.textContent = '🚚 配達予定 ' + formatDate(sale.delivery_date);
        infoEl.appendChild(deliveryEl);

        var link = document.createElement('a');
        link.className = 'product-card__detail-link';
        link.href = productDetailBaseUrl + '/' + sale.id;
        link.textContent = 'この商品を見る ▶';
        infoEl.appendChild(link);

        card.appendChild(infoEl);

        return card;
    }

    function matchesCategoryFilter(sale) {
        if (!currentCategoryFilter) {
            return true;
        }
        return String(sale.product.category_id) === String(currentCategoryFilter);
    }

    function renderProducts() {
        productsListEl.innerHTML = '';

        var filtered = allSales.filter(matchesCategoryFilter);

        if (filtered.length === 0) {
            productsEmptyEl.hidden = false;
            return;
        }
        productsEmptyEl.hidden = true;

        filtered.forEach(function (sale) {
            productsListEl.appendChild(buildCard(sale));
        });
    }

    /**
     * 取得した商品一覧からカテゴリーの重複を除いてタブを作る
     * (購入者向けの専用カテゴリーAPIは無いため、product.categoryから組み立てる)。
     * display_orderが取れる場合はその順序で並べる。
     */
    function buildCategoryTabs() {
        var categoriesById = {};
        allSales.forEach(function (sale) {
            var category = sale.product.category;
            if (category && !categoriesById[category.id]) {
                categoriesById[category.id] = category;
            }
        });

        var categories = Object.keys(categoriesById).map(function (id) {
            return categoriesById[id];
        });
        categories.sort(function (a, b) {
            return (a.display_order || 0) - (b.display_order || 0);
        });

        categoryTabsEl.innerHTML = '';

        if (categories.length === 0) {
            categoryTabsEl.hidden = true;
            return;
        }
        categoryTabsEl.hidden = false;

        var allTab = document.createElement('button');
        allTab.type = 'button';
        allTab.className = 'buyer-home-page__category-tab buyer-home-page__category-tab--active';
        allTab.setAttribute('aria-pressed', 'true');
        allTab.textContent = 'すべて';
        allTab.dataset.categoryId = '';
        categoryTabsEl.appendChild(allTab);

        categories.forEach(function (category) {
            var tab = document.createElement('button');
            tab.type = 'button';
            tab.className = 'buyer-home-page__category-tab';
            tab.setAttribute('aria-pressed', 'false');
            tab.textContent = category.name;
            tab.dataset.categoryId = category.id;
            categoryTabsEl.appendChild(tab);
        });
    }

    categoryTabsEl.addEventListener('click', function (event) {
        var tab = event.target.closest('.buyer-home-page__category-tab');
        if (!tab) {
            return;
        }

        currentCategoryFilter = tab.dataset.categoryId;

        categoryTabsEl.querySelectorAll('.buyer-home-page__category-tab').forEach(function (el) {
            el.classList.toggle('buyer-home-page__category-tab--active', el === tab);
            el.setAttribute('aria-pressed', el === tab ? 'true' : 'false');
        });

        renderProducts();
    });

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
            productsLoadingEl.hidden = true;
            allSales = data.products || [];
            buildCategoryTabs();
            renderProducts();
        }).catch(function () {
            productsLoadingEl.hidden = true;
            showGeneralMessage('商品一覧の取得に失敗しました。時間をおいてもう一度お試しください。');
        });
    }

    function buildAnnouncementItem(announcement) {
        var item = document.createElement('li');
        item.className = 'announcements-summary-list__item';
        item.textContent = announcement.title;
        return item;
    }

    function loadAnnouncements() {
        fetch(announcementsUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            announcementsLoadingEl.hidden = true;
            var announcements = data.announcements || [];

            if (announcements.length === 0) {
                announcementsEmptyEl.hidden = false;
                return;
            }

            announcements.forEach(function (announcement) {
                announcementsListEl.appendChild(buildAnnouncementItem(announcement));
            });
        }).catch(function () {
            announcementsLoadingEl.hidden = true;
            // お知らせの取得に失敗しても商品一覧は見られるようにしたいので、
            // 全体を止めるgeneralMessageではなくこの区画だけの表示にする
            announcementsEmptyEl.hidden = false;
            announcementsEmptyEl.textContent = 'お知らせの取得に失敗しました。';
        });
    }

    if (logoutButton) {
        var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

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
                if (response.ok || response.status === 401) {
                    window.location.reload();
                    return;
                }
                throw new Error('unexpected status ' + response.status);
            }).catch(function () {
                logoutButton.disabled = false;
                logoutButton.textContent = 'ログアウト';
            });
        });
    }

    loadAnnouncements();
    loadProducts();
})();
