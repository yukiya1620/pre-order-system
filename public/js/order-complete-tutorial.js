/**
 * 注文完了(orders.complete)専用の、操作チュートリアル接続コード。
 * DEMO_MODE=true のときだけ読み込まれる(resources/views/order-complete.blade.php)。
 *
 * 共通基盤(demo-tutorial.js)や注文完了の表示処理(order-complete.js)には
 * 一切手を加えず、この画面のステップ定義と、注文確認画面からの再開判定
 * だけをここに閉じ込める。
 *
 * 第3-C(今回)の範囲: 購入者チュートリアルの最終ステップ。
 * 吹き出しの「完了」ボタンを利用者本人が押したときだけ、
 * localStorageの既読フラグ(buyer)が保存される
 * (共通基盤のfinish()に委譲。close/finishの責務分離は
 * demo-tutorial.js側で第3-Cにて対応済み)。
 * 注文完了画面には注文履歴への直接リンクが無いため(第3-C調査で確認済み)、
 * 案内文は「商品一覧の注文履歴から確認できる」という実画面に合わせた表現にする。
 */
(function () {
    if (!window.DemoTutorial) {
        return;
    }

    // 経路ごとの合計ステップ数・開始位置(他画面と同じ考え方)。
    var TOTAL_STEPS_GUEST = 11;
    var TOTAL_STEPS_AUTHENTICATED = 7;
    var STEP_OFFSET_GUEST = 10;
    var STEP_OFFSET_AUTHENTICATED = 6;

    var steps = [
        {
            target: 'buyer-order-complete',
            description: '注文できました。これで購入者側の体験は完了です。注文履歴は、商品一覧画面の「注文履歴」から確認できます。'
        }
    ];

    // order-complete.jsが注文完了内容の表示(GET /api/v1/orders/{id}の結果)を
    // 描き終えたことを示すカスタムイベント。order-confirm-tutorial.jsと同じ考え方。
    var contentRendered = false;
    var pendingResume = false;

    document.addEventListener('demo-tutorial:order-complete-rendered', function () {
        contentRendered = true;
        if (pendingResume) {
            pendingResume = false;
            doStart();
        }
    });

    function doStart() {
        var progress = window.DemoTutorial.loadProgress('buyer');
        if (!progress || progress.route !== 'orders.complete') {
            return;
        }

        var flow = progress.flow === 'authenticated' ? 'authenticated' : 'guest';
        var totalSteps = flow === 'authenticated' ? TOTAL_STEPS_AUTHENTICATED : TOTAL_STEPS_GUEST;
        var stepOffset = flow === 'authenticated' ? STEP_OFFSET_AUTHENTICATED : STEP_OFFSET_GUEST;

        // isFinalPage: trueを渡すことで、共通基盤側が「このページのローカル
        // 最終ステップ(ここでは唯一のステップ)=購入者チュートリアル全体の
        // 本当の最終ステップ」と認識し、吹き出しに「完了」ボタンを表示する。
        // 利用者本人がこれを押した場合だけ、共通基盤のnext()→finish()が
        // 呼ばれ、localStorageの既読フラグが保存される。
        // isFinalPageを渡さない他の全ページでは、ローカル最終ステップで
        // 「次へ」ボタン自体が表示されない設計になっている(第3-C参照)。
        window.DemoTutorial.start(steps, {
            tutorialKey: 'buyer',
            route: 'orders.complete',
            extra: { flow: flow },
            totalSteps: totalSteps,
            stepOffset: stepOffset,
            isFinalPage: true
        });
    }

    /**
     * sessionStorageの進行状況を見て、必要なら続きを開始する。
     * 注文確認画面(orders.confirm)から注文確定に成功して遷移してきた場合
     * (route==='orders.complete')だけ自動開始する。ブラウザの戻る操作で
     * このページがbfcacheから復元された場合(pageshowイベント)にも
     * 同じ判定を再実行する。
     * 一度最終ステップを完了させた後は、finish()によって進行状況が
     * クリアされているため、再度このページに来ても自動的には開始されない
     * (二重に既読処理が走ることもない)。
     */
    function tryResume() {
        if (window.DemoTutorial.isActive()) {
            return;
        }

        var progress = window.DemoTutorial.loadProgress('buyer');
        if (!progress || progress.route !== 'orders.complete') {
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
