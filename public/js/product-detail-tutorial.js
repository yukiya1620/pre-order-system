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
 * 第3-B(今回)の範囲: 配達希望日→数量→注文へ進む、の3ステップに加えて、
 * ログイン画面(auth-form-tutorial.js)への引き継ぎまで。
 * orders.confirm・注文確定・注文完了への接続はまだ第3-Cで行う。
 * 注文へ進むボタン(またはログインリンク)が実際にクリックされた場合は、
 * このページの範囲でチュートリアルを片付ける(オーバーレイ・進行状態を残さない)。
 */
(function () {
    if (!window.DemoTutorial) {
        return;
    }

    // 購入者チュートリアル全体の通し番号表示に使う値。第3-Cで注文確認・
    // 注文完了のステップが加わるまで、合計ステップ数はまだ確定していないため、
    // 具体的な数値を決め打ちせず '?' を表示する(例:"2 / ?")。
    var TOTAL_STEPS = '?';
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

    var tutorialActive = false;

    /**
     * sessionStorageの進行状況を見て、必要なら続きを開始する。
     * ページの初回読み込み時に加えて、ブラウザの戻る操作でこのページが
     * bfcacheから復元された場合(pageshowイベント、event.persisted===true)にも
     * 呼び出す。基盤(demo-tutorial.js)側のpageshowリスナーが、復元時に
     * 古いオーバーレイをいったん片付けてくれるため、ここでは
     * 「もう一度、今の状況に応じて開始し直してよいか」だけを判定すればよい。
     */
    function tryResume() {
        if (window.DemoTutorial.isActive()) {
            // 既に何らかのツアーが表示中なら(例えば基盤がまだ片付け切っていない
            // 一瞬の間など)、二重に開始しない。
            return;
        }

        var progress = window.DemoTutorial.loadProgress('buyer');
        if (!progress || progress.route !== 'products.show') {
            // buyer.homeのチュートリアルから正しく遷移してきた形跡が無いため、
            // 直接アクセス等とみなして、自動的には開始しない。
            return;
        }

        var startAtStep = typeof progress.stepIndex === 'number' ? progress.stepIndex : 0;

        tutorialActive = true;
        window.DemoTutorial.start(steps, {
            tutorialKey: 'buyer',
            route: 'products.show',
            startAtStep: startAtStep,
            totalSteps: TOTAL_STEPS,
            stepOffset: STEP_OFFSET,
            onFinish: function () {
                tutorialActive = false;
            }
        });
    }

    tryResume();

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            tryResume();
        }
    });

    /**
     * ステップ4(注文へ進む/ログインする)の対象が実際にクリックされた場合、
     * ページがこの後すぐ遷移する(注文確認画面、またはログイン画面)。
     * 遷移前にオーバーレイ・進行状態をきちんと片付けておく。
     * 実際のクリック処理(注文確認画面へのURL遷移、ログイン画面への遷移)は
     * product-detail.js側の処理・通常のリンク遷移のままで、ここでは妨げない。
     *
     * ログインリンク(<a>)の場合だけ、ログイン画面(auth-form-tutorial.js)で
     * チュートリアルを再開できるよう、新しい進行状況を保存し直す。
     * ログイン済みで注文ボタン(<button>)を押した場合(orders.confirmへ進む
     * 場合)は、ログイン画面を経由しないためこの保存は行わない
     * (route要素をtagNameで区別する。data-demo-tutorial属性はどちらにも
     * 付与されているため、遷移先の区別にはDOM構造の違いを使う)。
     */
    document.addEventListener('click', function (event) {
        if (!tutorialActive) {
            return;
        }

        var target = event.target.closest('[data-demo-tutorial="buyer-order-button"]');
        if (!target) {
            return;
        }

        var isLoginLink = target.tagName === 'A';

        window.DemoTutorial.close();

        if (isLoginLink) {
            window.DemoTutorial.saveProgress('buyer', { stepIndex: 0, route: 'login' });
        }
    }, true);
})();
