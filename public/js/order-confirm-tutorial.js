/**
 * 注文内容の確認(orders.confirm)専用の、操作チュートリアル接続コード。
 * DEMO_MODE=true のときだけ読み込まれる(resources/views/order-confirm.blade.php)。
 *
 * 共通基盤(demo-tutorial.js)や注文確認・注文確定処理(order-confirm.js)には
 * 一切手を加えず、この画面のステップ定義と、商品詳細からの再開判定、
 * 注文完了画面への引き継ぎだけをここに閉じ込める。
 *
 * 第3-C(今回)の範囲: 注文内容確認→注文確定ボタン、の2ステップ。
 * 「この内容で注文する」ボタンは利用者本人が実際にクリックしたときだけ
 * 注文が確定する(チュートリアル側が自動でクリックすることは無い)。
 * 注文確定APIが失敗した場合はorder-confirm.js側の既存エラー表示に任せ、
 * チュートリアル独自のリトライは行わない。この場合、次ページ(注文完了)への
 * 進行状況も保存しない(下記の demo-tutorial:order-submitted 参照)。
 */
(function () {
    if (!window.DemoTutorial) {
        return;
    }

    // 経路ごとの合計ステップ数・開始位置(buyer-home-tutorial.js等と同じ考え方)。
    var TOTAL_STEPS_GUEST = 11;
    var TOTAL_STEPS_AUTHENTICATED = 7;
    var STEP_OFFSET_GUEST = 8;
    var STEP_OFFSET_AUTHENTICATED = 4;

    var steps = [
        {
            target: 'buyer-order-summary',
            description: '注文する商品・数量・配達日をここで確認できます。まだこの時点では注文は確定していません。'
        },
        {
            target: 'buyer-order-submit',
            description: '内容に問題がなければ、このボタンで注文を確定します。'
        }
    ];

    var tutorialActive = false;
    var currentFlow = 'guest';
    // 注文確定ボタンがクリックされた時点で、チュートリアルを実際に見ていたか。
    // window.DemoTutorial.close()を呼ぶとtutorialActiveはonFinish経由で
    // すぐにfalseへ戻ってしまうため、demo-tutorial:order-submitted
    // (非同期に届く成功通知)を受け取った時点でも判定できるよう、
    // 別のフラグとして保持しておく。
    var wasActiveWhenSubmitted = false;

    // order-confirm.jsが注文内容の表示(POST /api/v1/orders/previewの結果)を
    // 描き終えたことを示すカスタムイベント。fetchが速く終わってイベントが
    // tryResume()より先に発火する心配は無い(deferスクリプトはHTML内の
    // 出現順に同期実行され、fetchの完了は必ずその後の非同期タイミングに
    // なるため)が、念のためフラグで管理しておく。
    var contentRendered = false;
    var pendingResume = false;

    document.addEventListener('demo-tutorial:order-confirm-rendered', function () {
        contentRendered = true;
        if (pendingResume) {
            pendingResume = false;
            doStart();
        }
    });

    function doStart() {
        var progress = window.DemoTutorial.loadProgress('buyer');
        if (!progress || progress.route !== 'orders.confirm') {
            return;
        }

        currentFlow = progress.flow === 'authenticated' ? 'authenticated' : 'guest';
        var totalSteps = currentFlow === 'authenticated' ? TOTAL_STEPS_AUTHENTICATED : TOTAL_STEPS_GUEST;
        var stepOffset = currentFlow === 'authenticated' ? STEP_OFFSET_AUTHENTICATED : STEP_OFFSET_GUEST;

        tutorialActive = true;
        window.DemoTutorial.start(steps, {
            tutorialKey: 'buyer',
            route: 'orders.confirm',
            extra: { flow: currentFlow },
            totalSteps: totalSteps,
            stepOffset: stepOffset,
            onFinish: function () {
                tutorialActive = false;
            }
        });
    }

    /**
     * sessionStorageの進行状況を見て、必要なら続きを開始する。
     * 商品詳細(products.show)から正しく遷移してきた場合(route==='orders.confirm')
     * だけ自動開始し、直接アクセス等は何もしない。ブラウザの戻る操作で
     * このページがbfcacheから復元された場合(pageshowイベント)にも
     * 同じ判定を再実行する。
     */
    function tryResume() {
        if (window.DemoTutorial.isActive()) {
            return;
        }

        var progress = window.DemoTutorial.loadProgress('buyer');
        if (!progress || progress.route !== 'orders.confirm') {
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
     * 「この内容で注文する」ボタンが実際にクリックされた場合、この後
     * ページが遷移するかもしれないため、先にオーバーレイ・進行状態を
     * 片付けておく(close()。既読フラグは立たない)。実際の注文確定処理
     * (POST /api/v1/orders)はorder-confirm.js側の処理のままで、ここでは
     * 一切妨げない。
     */
    document.addEventListener('click', function (event) {
        if (!tutorialActive) {
            return;
        }

        var target = event.target.closest('[data-demo-tutorial="buyer-order-submit"]');
        if (!target) {
            return;
        }

        wasActiveWhenSubmitted = true;
        window.DemoTutorial.close();
    }, true);

    /**
     * 注文確定APIが成功した場合だけ、order-confirm.js側が発火する
     * カスタムイベント(業務ロジックには手を加えず、成功通知の発火のみ追加)。
     * 失敗した場合はこのイベントが発火しないため、次ページ用の進行状況は
     * 保存されず、既存のエラー表示(order-confirm.js)のまま操作可能な状態が保たれる。
     * wasActiveWhenSubmittedがfalseの場合(チュートリアルを開いていない
     * 通常の利用者が注文した場合)は何もしない。
     */
    document.addEventListener('demo-tutorial:order-submitted', function () {
        if (!wasActiveWhenSubmitted) {
            return;
        }
        wasActiveWhenSubmitted = false;
        window.DemoTutorial.saveProgress('buyer', { stepIndex: 0, route: 'orders.complete', flow: currentFlow });
    });
})();
