/**
 * 商品管理(farmer.products)専用の、操作チュートリアル接続コード。
 * DEMO_MODE=true のときだけ読み込まれる(resources/views/farmer-products.blade.php)。
 *
 * 共通基盤(demo-tutorial.js)や商品一覧の取得・表示処理(farmer-products.js)には
 * 一切手を加えず、この画面のステップ定義・農家ホームからの再開判定・
 * 農家ホームへの引き継ぎだけをここに閉じ込める。
 *
 * 第5-B(今回)の範囲: 最初の商品カード→「もどる」リンク、の2ステップ。
 * 「商品を編集」「去年の商品から再販売」リンクはスポットライト対象に
 * 含めても、チュートリアル側からクリックすることは一切無い(遷移先の
 * 編集画面で保存すると実データが変わるため)。
 */
(function () {
    if (!window.DemoTutorial) {
        return;
    }

    var TOTAL_STEPS = 9;
    // 農家ホーム2 + 配達のかくにん2 + 農家ホーム1(2回目) = 5個の後に続く。
    var STEP_OFFSET = 5;

    var steps = [
        {
            target: 'farmer-product-card',
            description: '販売中の商品・在庫・販売状態を確認できます。編集や新規登録もここから行います。'
        },
        {
            target: 'farmer-back-link',
            description: '次に売上を見てみましょう。農家ホームへ戻ります。'
        }
    ];

    var tutorialActive = false;

    // farmer-products.jsが商品カードの描画(絞り込み再描画含む)を終えたことを
    // 示すカスタムイベント。
    var contentRendered = false;
    var pendingResume = false;

    document.addEventListener('demo-tutorial:farmer-products-rendered', function () {
        contentRendered = true;
        if (pendingResume) {
            pendingResume = false;
            doStart();
        }
    });

    function doStart() {
        var progress = window.DemoTutorial.loadProgress('farmer');
        if (!progress || progress.route !== 'farmer.products') {
            return;
        }

        tutorialActive = true;
        window.DemoTutorial.start(steps, {
            tutorialKey: 'farmer',
            route: 'farmer.products',
            totalSteps: TOTAL_STEPS,
            stepOffset: STEP_OFFSET,
            onFinish: function () {
                tutorialActive = false;
            }
        });
    }

    /**
     * sessionStorageの進行状況を見て、必要なら続きを開始する。
     * 農家ホームから正しく遷移してきた場合(route==='farmer.products')だけ
     * 自動開始し、直接アクセス等は何もしない。0件・API失敗等でカードの
     * 描画完了イベントが発火しない場合は、チュートリアルは開始されない。
     */
    function tryResume() {
        if (window.DemoTutorial.isActive()) {
            return;
        }

        var progress = window.DemoTutorial.loadProgress('farmer');
        if (!progress || progress.route !== 'farmer.products') {
            return;
        }

        if (contentRendered) {
            doStart();
        } else {
            pendingResume = true;
        }
    }

    tryResume();

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            tryResume();
        }
    });

    /**
     * 「もどる」リンク(ステップ2、ローカル最終ステップ)が実際にクリックされた
     * 場合、この後ページが遷移するため、先にオーバーレイ・進行状態を
     * 片付けておく(close()。既読フラグは立たない)。農家ホーム側で3回目の
     * 専用案内(売上確認メニュー)を再開できるよう、reason付きの進行状況を
     * 保存し直す。
     */
    document.addEventListener('click', function (event) {
        if (!tutorialActive) {
            return;
        }

        var target = event.target.closest('[data-demo-tutorial="farmer-back-link"]');
        if (!target) {
            return;
        }

        window.DemoTutorial.close();
        window.DemoTutorial.saveProgress('farmer', {
            stepIndex: 0,
            route: 'farmer.home',
            reason: 'after-products'
        });
    }, true);
})();
