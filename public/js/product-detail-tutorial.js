/**
 * 商品詳細(products.show)専用の、操作チュートリアル接続コード。
 * DEMO_MODE=true のときだけ読み込まれる(resources/views/product-detail.blade.php)。
 *
 * 共通基盤(demo-tutorial.js)や商品表示・注文処理(product-detail.js)には
 * 一切手を加えず、この画面のステップ定義と、商品一覧からの再開判定
 * だけをここに閉じ込める。
 *
 * 【重要】このスクリプトは、商品一覧(buyer.home)のチュートリアルを実際に
 * 進めていた利用者が、商品カードをクリックしてこのページに来た場合だけ
 * 自動的に続きを始める。sessionStorageに正しい進行状況(route==='products.show')
 * が無い場合(URLを直接開いた、他のリンクから来た等)は、何もしない。
 *
 * 第3-A(今回)の範囲: 配達希望日→数量→注文へ進む、の3ステップのみ。
 * ログイン・SMS認証・注文確認・注文完了への接続は次段階(第3-B)以降。
 * 注文へ進むボタン(またはログインリンク)が実際にクリックされた場合は、
 * このページの範囲でチュートリアルを片付ける(オーバーレイ・進行状態を残さない)。
 */
(function () {
    if (!window.DemoTutorial) {
        return;
    }

    // 購入者チュートリアル全体の通し番号表示("n / 4")に使う値。
    // 商品一覧側(buyer-home-tutorial.js)の合計値と揃えている。
    var TOTAL_STEPS = 4;
    // 商品一覧に1ステップあるため、このページのステップは全体の2番目から始まる。
    var STEP_OFFSET = 1;

    var steps = [
        {
            target: 'buyer-delivery-date',
            description: 'ご希望の配達日を選べます。都合のよい日を選んでみましょう。'
        },
        {
            target: 'buyer-quantity',
            description: '注文したい数量を指定できます。必要な数を選んでみましょう。'
        },
        {
            target: 'buyer-order-button',
            description: '内容を決めたら、ここから注文へ進みます。ログインしていない場合は、先にログイン画面へ移動します。'
        }
    ];

    var progress = window.DemoTutorial.loadProgress('buyer');
    if (!progress || progress.route !== 'products.show') {
        // buyer.homeのチュートリアルから正しく遷移してきた形跡が無いため、
        // 直接アクセス等とみなして、自動的には開始しない。
        return;
    }

    var startAtStep = typeof progress.stepIndex === 'number' ? progress.stepIndex : 0;

    var tutorialActive = true;

    window.DemoTutorial.start(steps, {
        tutorialKey: 'buyer',
        startAtStep: startAtStep,
        totalSteps: TOTAL_STEPS,
        stepOffset: STEP_OFFSET,
        onFinish: function () {
            tutorialActive = false;
        }
    });

    /**
     * ステップ4(注文へ進む/ログインする)の対象が実際にクリックされた場合、
     * ページがこの後すぐ遷移する(注文確認画面、またはログイン画面)。
     * 遷移先の画面ではまだチュートリアルを再開しない設計(第3-Bで対応予定)
     * のため、遷移前にオーバーレイ・進行状態をきちんと片付けておく。
     * 実際のクリック処理(注文確認画面へのURL遷移、ログイン画面への遷移)は
     * product-detail.js側の処理・通常のリンク遷移のままで、ここでは妨げない。
     */
    document.addEventListener('click', function (event) {
        if (!tutorialActive) {
            return;
        }

        var target = event.target.closest('[data-demo-tutorial="buyer-order-button"]');
        if (!target) {
            return;
        }

        window.DemoTutorial.close();
    }, true);
})();
