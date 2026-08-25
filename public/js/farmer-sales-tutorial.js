/**
 * 売上確認(farmer.sales)専用の、操作チュートリアル接続コード。
 * DEMO_MODE=true のときだけ読み込まれる(resources/views/farmer-sales.blade.php)。
 *
 * 共通基盤(demo-tutorial.js)や売上集計の取得・表示処理(farmer-sales.js)には
 * 一切手を加えず、この画面のステップ定義と、商品管理からの再開判定だけを
 * ここに閉じ込める。
 *
 * 第5-B(今回)の範囲: 販売者側チュートリアルの最終ステップ(9/9)。
 * 吹き出しの「完了」ボタンを利用者本人が押したときだけ、
 * localStorageの既読フラグ(farmer)が保存される(共通基盤のfinish()に委譲。
 * isFinalPage: trueを渡すことで、共通基盤側がこのステップを本当の最終
 * ステップと認識する)。期間切替ボタン(今日/今月/今年)はスポットライト
 * 対象に含めないが、データを変更しない表示切替のため実際に操作しても問題ない。
 */
(function () {
    if (!window.DemoTutorial) {
        return;
    }

    var TOTAL_STEPS = 9;
    // 農家ホーム2 + 配達のかくにん2 + 農家ホーム1 + 商品管理2 + 農家ホーム1 = 8個の後に続く。
    var STEP_OFFSET = 8;

    var steps = [
        {
            target: 'farmer-sales-summary',
            description: '本日・今月・今年の売上や、商品ごとの売れ行きを確認できます。これで販売者側の体験は完了です。'
        }
    ];

    // farmer-sales.jsが売上サマリーの表示を終えたことを示すカスタムイベント。
    // 取得に失敗した場合(このイベントが発火しない場合)は、対象要素が
    // 見つからないまま最終ステップが完了扱いになってしまうのを避けるため、
    // イベントを待ってから開始する(order-complete-tutorial.jsと同じ考え方)。
    var contentRendered = false;
    var pendingResume = false;

    document.addEventListener('demo-tutorial:farmer-sales-rendered', function () {
        contentRendered = true;
        if (pendingResume) {
            pendingResume = false;
            doStart();
        }
    });

    function doStart() {
        var progress = window.DemoTutorial.loadProgress('farmer');
        if (!progress || progress.route !== 'farmer.sales') {
            return;
        }

        // 最終ステップなので、吹き出しの「次へ」ボタンは共通基盤側の判定
        // (isLast && isFinalPage)により自動的に「完了」表示になる。
        window.DemoTutorial.start(steps, {
            tutorialKey: 'farmer',
            route: 'farmer.sales',
            totalSteps: TOTAL_STEPS,
            stepOffset: STEP_OFFSET,
            isFinalPage: true
        });
    }

    /**
     * sessionStorageの進行状況を見て、必要なら続きを開始する。
     * 商品管理から正しく遷移してきた場合(route==='farmer.sales')だけ
     * 自動開始する。一度最終ステップを完了させた後は、finish()によって
     * 進行状況がクリアされているため、再度このページに来ても自動的には
     * 開始されない(二重に既読処理が走ることもない)。
     */
    function tryResume() {
        if (window.DemoTutorial.isActive()) {
            return;
        }

        var progress = window.DemoTutorial.loadProgress('farmer');
        if (!progress || progress.route !== 'farmer.sales') {
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
})();
