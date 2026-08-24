/**
 * 購入者トップ(商品一覧)専用の、操作チュートリアル接続コード。
 * DEMO_MODE=true のときだけ読み込まれる(resources/views/buyer-home.blade.php)。
 *
 * 共通基盤(demo-tutorial.js)や商品取得・表示処理(buyer-home.js)には
 * 一切手を加えず、この画面のステップ定義と「使い方を見る」ボタンの
 * 制御だけをここに閉じ込める。
 *
 * 第3-A(今回)の範囲: 商品一覧(1ステップ)→商品詳細(3ステップ)の
 * ページ横断まで。ログイン・SMS認証・注文確認・注文完了への接続は次段階以降。
 */
(function () {
    var startButton = document.getElementById('buyer-home-tutorial-start-button');
    if (!startButton || !window.DemoTutorial) {
        return;
    }

    // 購入者チュートリアル全体の通し番号表示("n / 4")に使う合計ステップ数。
    // 商品一覧1ステップ + 商品詳細3ステップ(public/js/product-detail-tutorial.js)。
    // モジュールバンドラを使わない構成のため、値は各ファイルに個別に持たせている。
    var TOTAL_STEPS = 4;

    var DEFAULT_BUTTON_LABEL = startButton.textContent;

    var steps = [
        {
            target: 'buyer-product-card',
            description: 'まずは気になる商品を選んでみましょう。商品を選ぶと、詳しい内容や配達希望日を確認できます。'
        }
    ];

    // このステップがアクティブな間だけ、商品カードのリンクが実際にクリック
    // されたことを検知できるようにする(チュートリアルを開いていない通常の
    // 閲覧では一切関与しない)。
    var tutorialActive = false;

    // 商品カードは public/js/buyer-home.js がAPIレスポンスをもとに
    // 動的に生成する。カードの描画が終わるまでは data-demo-tutorial 属性を
    // 持つ要素が存在しないため、buyer-home.js が描画完了時に発火する
    // カスタムイベント(demo-tutorial:buyer-products-rendered)を合図にする。
    // setTimeoutの秒数決め打ちに頼らない、疎結合な連携方法。
    var productsReady = false;
    var pendingStart = false;
    var readyTimeoutId = null;

    document.addEventListener('demo-tutorial:buyer-products-rendered', function onProductsRendered() {
        productsReady = true;
        if (pendingStart) {
            pendingStart = false;
            if (readyTimeoutId !== null) {
                window.clearTimeout(readyTimeoutId);
                readyTimeoutId = null;
            }
            beginTutorial();
        }
    });

    function resetButton() {
        startButton.disabled = false;
        startButton.textContent = DEFAULT_BUTTON_LABEL;
    }

    function beginTutorial() {
        resetButton();
        tutorialActive = true;
        window.DemoTutorial.start(steps, {
            tutorialKey: 'buyer',
            triggerElement: startButton,
            totalSteps: TOTAL_STEPS,
            stepOffset: 0,
            onFinish: function () {
                tutorialActive = false;
            }
        });
    }

    /**
     * ステップ1が表示されている間に、実際に商品カードのリンクがクリック
     * されたら、商品詳細(products.show)側でステップ2から自然に続けられる
     * よう、遷移前に進行状況を保存しておく。
     *
     * 重要: ここではリンクの遷移そのもの(既存のhref・ブラウザの標準的な
     * ページ遷移)には一切手を加えない。preventDefault等は行わず、
     * 「遷移する前に一言メモを残すだけ」の副作用に留める。
     * チュートリアルを開いていない場合(tutorialActive === false)は
     * 何もしない。
     */
    document.addEventListener('click', function (event) {
        if (!tutorialActive) {
            return;
        }

        var link = event.target.closest('.product-card__detail-link');
        if (!link) {
            return;
        }

        var card = link.closest('[data-demo-tutorial="buyer-product-card"]');
        if (!card) {
            return;
        }

        // products.show側のローカルなステップ配列における開始位置(0=配達希望日)。
        window.DemoTutorial.saveProgress('buyer', { stepIndex: 0, route: 'products.show' });
    }, true);

    startButton.addEventListener('click', function () {
        if (startButton.disabled) {
            return;
        }

        if (productsReady) {
            beginTutorial();
            return;
        }

        // 商品一覧の読み込みが遅い、または通信状況などにより、まだ商品カードが
        // 描画されていない場合は、描画完了イベントを待ってから開始する。
        // ボタンを一時的に無効化し、待っていることを利用者に伝える。
        pendingStart = true;
        startButton.disabled = true;
        startButton.textContent = '読み込み中です…';

        // 商品取得APIが失敗した場合など、描画完了イベントが永久に来ない
        // ケースに備えた保険。一定時間待っても準備できなければ諦めて
        // ボタンを元の状態に戻す(この秒数は「対象を探す待ち時間」ではなく、
        // あくまで異常系のフェイルセーフとして設けている)。
        readyTimeoutId = window.setTimeout(function () {
            if (pendingStart) {
                pendingStart = false;
                resetButton();
            }
        }, 8000);
    });
})();
