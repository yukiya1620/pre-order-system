/**
 * 購入者トップ(商品一覧)専用の、操作チュートリアル接続コード。
 * DEMO_MODE=true のときだけ読み込まれる(resources/views/buyer-home.blade.php)。
 *
 * 共通基盤(demo-tutorial.js)や商品取得・表示処理(buyer-home.js)には
 * 一切手を加えず、この画面のステップ定義と「使い方を見る」ボタンの
 * 制御だけをここに閉じ込める。
 *
 * 第2段階(今回)の範囲: 商品一覧の1ステップのみ。
 * 商品詳細・ログイン・注文確認・注文完了へのページ横断は次段階以降。
 */
(function () {
    var startButton = document.getElementById('buyer-home-tutorial-start-button');
    if (!startButton || !window.DemoTutorial) {
        return;
    }

    var DEFAULT_BUTTON_LABEL = startButton.textContent;

    var steps = [
        {
            target: 'buyer-product-card',
            description: 'まずは気になる商品を選んでみましょう。商品を選ぶと、詳しい内容や配達希望日を確認できます。'
        }
    ];

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
        window.DemoTutorial.start(steps, {
            tutorialKey: 'buyer',
            triggerElement: startButton
        });
    }

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
