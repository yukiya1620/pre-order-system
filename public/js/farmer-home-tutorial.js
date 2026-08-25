/**
 * 農家ホーム(farmer.home)専用の、操作チュートリアル接続コード。
 * DEMO_MODE=true のときだけ読み込まれる(resources/views/farmer-home.blade.php)。
 *
 * 共通基盤(demo-tutorial.js)や概要取得処理(farmer-home.js)には一切手を
 * 加えず、この画面のステップ定義・「使い方を見る」ボタンの制御・
 * 初回自動開始だけをここに閉じ込める。購入者側(buyer-home-tutorial.js等)と
 * 同じ共通基盤・同じ考え方を、tutorialKey: 'farmer' で再利用する。
 *
 * 第5-B(今回)の範囲: 農家ホーム→注文確認(配達のかくにん)→農家ホーム→
 * 商品管理→農家ホーム→売上確認、の9ステップ。経路分岐は無く、
 * 常に9ステップで固定(購入者側のguest/authenticatedのような分岐は無い)。
 *
 * farmer.homeは合計3回チュートリアルに登場する。
 *   1回目(通常開始・自動開始): 概要カード→「注文確認」メニュー、の2ステップ
 *   2回目(配達のかくにんの「もどる」から戻ってきた場合): 「商品管理」メニュー、の1ステップ
 *   3回目(商品管理の「もどる」から戻ってきた場合): 「売上確認」メニュー、の1ステップ
 * 2回目・3回目は、購入者側の「認証後専用案内」(buyer-home-tutorial.jsの
 * postAuthSteps)と同じ考え方で、sessionStorageの進行状況のreasonフィールドで
 * 区別する(同じ内容を繰り返し説明しないため)。
 *
 * データを変更する操作(配達回答・配達完了・商品編集・お知らせ投稿等)は
 * このチュートリアルからは一切行わない。実際のページ遷移はすべて
 * 利用者本人のクリックによる(チュートリアル側が強制的に遷移させない)。
 */
(function () {
    if (!window.DemoTutorial) {
        return;
    }

    var TOTAL_STEPS = 9;

    // 1回目(通常開始): 概要カード→「注文確認」メニュー。
    var normalSteps = [
        {
            target: 'farmer-overview',
            description: 'ここでは、対応が必要な件数や本日の売上をひと目で確認できます。'
        },
        {
            target: 'farmer-menu-delivery-confirmations',
            description: '新しく入った注文は、ここで配達できるか確認します。すべての注文は「予約一覧」から確認できます。'
        }
    ];

    // 2回目(配達のかくにんから戻ってきた場合): 「商品管理」メニューのみ。
    var afterDeliveryConfirmationsSteps = [
        {
            target: 'farmer-menu-products',
            description: '販売する商品や在庫の管理はこちらから行えます。'
        }
    ];

    // 3回目(商品管理から戻ってきた場合): 「売上確認」メニューのみ。
    var afterProductsSteps = [
        {
            target: 'farmer-menu-sales',
            description: '売上状況はこちらから確認できます。'
        }
    ];

    var startButton = document.getElementById('farmer-home-tutorial-start-button');

    var tutorialActive = false;

    /**
     * phaseに応じたsteps配列・stepOffset・extra(reason)を選び、
     * 共通基盤のstart()を呼ぶ。「使い方を見る」ボタン(常にnormal)、
     * tryResume()(after-delivery-confirmations/after-products)、
     * 初回自動開始(常にnormal)のすべてから、この関数を経由して開始する。
     */
    function beginTutorial(phase) {
        tutorialActive = true;

        var stepsToUse;
        var stepOffset;
        var extra = null;

        if (phase === 'after-delivery-confirmations') {
            stepsToUse = afterDeliveryConfirmationsSteps;
            // 農家ホーム2 + 配達のかくにん2 = 4個の後に続く。
            stepOffset = 4;
            extra = { reason: 'after-delivery-confirmations' };
        } else if (phase === 'after-products') {
            stepsToUse = afterProductsSteps;
            // 農家ホーム2 + 配達のかくにん2 + 農家ホーム1 + 商品管理2 = 7個の後に続く。
            stepOffset = 7;
            extra = { reason: 'after-products' };
        } else {
            stepsToUse = normalSteps;
            stepOffset = 0;
        }

        window.DemoTutorial.start(stepsToUse, {
            tutorialKey: 'farmer',
            route: 'farmer.home',
            triggerElement: startButton || null,
            extra: extra,
            totalSteps: TOTAL_STEPS,
            stepOffset: stepOffset,
            onFinish: function () {
                tutorialActive = false;
            }
        });
    }

    // ------------------------------------------------------------------
    // 「使い方を見る」ボタン: auto_shown/seenの状態にかかわらず、
    // 常に通常の2ステップ(1回目)から手動で開始できる。
    // ------------------------------------------------------------------
    if (startButton) {
        startButton.addEventListener('click', function () {
            beginTutorial('normal');
        });
    }

    /**
     * メニューリンクの実クリックを検知し、次ページ用の進行状況を保存する。
     * スポットライトの範囲外はオーバーレイに覆われ操作できないため、
     * 実際にクリックされ得るのは常に「現在のステップの対象リンク」だけである。
     * 遷移そのもの(既存のhref・ブラウザの標準的なページ遷移)には
     * 一切手を加えない(preventDefault等は行わない)。
     */
    document.addEventListener('click', function (event) {
        if (!tutorialActive) {
            return;
        }

        var deliveryLink = event.target.closest('[data-demo-tutorial="farmer-menu-delivery-confirmations"]');
        if (deliveryLink) {
            window.DemoTutorial.close();
            window.DemoTutorial.saveProgress('farmer', { stepIndex: 0, route: 'farmer.delivery-confirmations' });
            return;
        }

        var productsLink = event.target.closest('[data-demo-tutorial="farmer-menu-products"]');
        if (productsLink) {
            window.DemoTutorial.close();
            window.DemoTutorial.saveProgress('farmer', { stepIndex: 0, route: 'farmer.products' });
            return;
        }

        var salesLink = event.target.closest('[data-demo-tutorial="farmer-menu-sales"]');
        if (salesLink) {
            window.DemoTutorial.close();
            window.DemoTutorial.saveProgress('farmer', { stepIndex: 0, route: 'farmer.sales' });
            return;
        }
    }, true);

    /**
     * sessionStorageの進行状況を見て、2回目・3回目(戻ってきた場合)の
     * 専用ステップを再開してよいか判定する。reasonが無い(通常のroute予約)
     * 場合は自動的には再開しない(「使い方を見る」または初回自動開始のみ)。
     * ブラウザの戻る操作でこのページがbfcacheから復元された場合
     * (pageshowイベント)にも同じ判定を再実行する。
     */
    function tryResume() {
        if (window.DemoTutorial.isActive()) {
            return;
        }

        var progress = window.DemoTutorial.loadProgress('farmer');
        if (!progress || progress.route !== 'farmer.home') {
            return;
        }

        if (progress.reason === 'after-delivery-confirmations') {
            beginTutorial('after-delivery-confirmations');
        } else if (progress.reason === 'after-products') {
            beginTutorial('after-products');
        }
    }

    tryResume();

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            tryResume();
        }
    });

    // ------------------------------------------------------------------
    // 第4段階(購入者側)と同じ考え方の、farmer.home初回訪問時の自動開始。
    // ------------------------------------------------------------------
    //
    // 「seen」(farmer_seen、最後まで完了したか)とは別に「auto_shown」
    // (farmer_auto_shown、このブラウザで初回自動表示を一度でも実行したか)を
    // 独立管理する。Esc・×・途中離脱で完了しなくても、次回訪問時にまた
    // 勝手に始まらないようにするため、自動開始した瞬間にauto_shownを保存する。
    // ストレージキーはtutorialKey:'farmer'により、購入者側(buyer)とは
    // 完全に分離される(共通基盤側の仕組みをそのまま利用するだけで、
    // このファイル・共通基盤どちらにも新たなキー管理コードは不要)。

    /**
     * 自動開始してよいかを判定する。以下のいずれかに該当する場合は行わない。
     *   - 既に何らかのツアーが表示中(tryResume()による2回目・3回目の再開など)
     *   - このブラウザで既に一度、自動表示を実行済み(hasAutoShownTutorial)
     *   - sessionStorageに何らかの進行状態が残っている(直接アクセスではなく
     *     途中のページから戻ってきた形跡がある場合は、初回訪問とみなさない)
     */
    function tryAutoShow() {
        if (window.DemoTutorial.isActive()) {
            return;
        }
        if (window.DemoTutorial.hasAutoShownTutorial('farmer')) {
            return;
        }
        if (window.DemoTutorial.loadProgress('farmer')) {
            return;
        }

        beginAutoShow();
    }

    function beginAutoShow() {
        // 自動開始した「その瞬間」にauto_shownを保存する。この後の経緯
        // (最後まで見た・Escで閉じた・別ページへ移動した等)にかかわらず、
        // 「一度は自動的に見せた」という事実を残すための意図的なタイミング。
        window.DemoTutorial.markAutoShownTutorial('farmer');
        beginTutorial('normal');
    }

    tryAutoShow();
})();
