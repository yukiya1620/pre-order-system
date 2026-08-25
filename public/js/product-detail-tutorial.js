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
 * 第3-B: 配達希望日→数量→注文へ進む、の3ステップに加えて、
 * ログイン画面(auth-form-tutorial.js)への引き継ぎまで。
 * 第3-C(今回)の範囲: orders.confirm・注文確定・注文完了への接続。
 * これに伴い合計ステップ数が確定した('?'表示の解消)。
 * 認証を経由してこのページへ再訪した場合(reason:'post-auth')は、
 * 配達日・数量・注文ボタンをすでに一度説明済みのため、3ステップを
 * 繰り返さず「まとめステップ」1つにまとめる(条件は下記条件付きsteps参照)。
 * 注文へ進むボタン(またはログインリンク)が実際にクリックされた場合は、
 * このページの範囲でチュートリアルを片付ける(オーバーレイ・進行状態を残さない)。
 * ログイン済みで注文ボタンを押した場合は、orders.confirm側で続きを
 * 再開できるよう進行状況を保存する(第3-Cで追加)。
 */
(function () {
    if (!window.DemoTutorial) {
        return;
    }

    // 経路ごとの合計ステップ数(buyer-home-tutorial.jsと同じ値)。
    var TOTAL_STEPS_GUEST = 11;
    var TOTAL_STEPS_AUTHENTICATED = 7;
    // 商品一覧に1ステップあるため、通常の3ステップ版は全体の2番目から始まる。
    var STEP_OFFSET_NORMAL = 1;
    // まとめステップ(認証後再訪)は、商品一覧1+商品詳細3+ログイン2+post-auth案内1
    // = 7個の後に続くため、全体の8番目から始まる(常にguest経路)。
    var STEP_OFFSET_CONDENSED = 7;

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

    // 認証後の再訪専用。配達日・数量・注文ボタンをまとめて1ステップで案内する。
    // 対象はbuyer-order-actions(配達日欄〜注文ボタンまでを囲む要素)。
    // スポットライトの範囲外は操作できないため、個別要素ではなくこの
    // まとめ要素を対象にすることで、配達日・数量・注文ボタンのすべてを
    // 実際に操作できる状態のまま案内できる。
    var condensedSteps = [
        {
            target: 'buyer-order-actions',
            description: 'ログイン前に確認した手順と同じように、配達希望日と数量を選び、注文へ進みましょう。'
        }
    ];

    var tutorialActive = false;
    // クリックリスナーで進行状況を保存する際に使うため、tryResume()で
    // 判定したflowを保持しておく。
    var currentFlow = 'guest';

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
        currentFlow = progress.flow === 'authenticated' ? 'authenticated' : 'guest';
        var isPostAuthRevisit = progress.reason === 'post-auth';
        // 認証後の再訪は常にguest経路(SMS認証を経由するのはguestのみ)。
        var totalSteps = currentFlow === 'authenticated' ? TOTAL_STEPS_AUTHENTICATED : TOTAL_STEPS_GUEST;
        var stepsToUse = isPostAuthRevisit ? condensedSteps : steps;
        var stepOffset = isPostAuthRevisit ? STEP_OFFSET_CONDENSED : STEP_OFFSET_NORMAL;

        tutorialActive = true;
        window.DemoTutorial.start(stepsToUse, {
            tutorialKey: 'buyer',
            route: 'products.show',
            startAtStep: startAtStep < stepsToUse.length ? startAtStep : 0,
            extra: { flow: currentFlow },
            totalSteps: totalSteps,
            stepOffset: stepOffset,
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
     * ログインリンク(<a>)かログイン済みの注文ボタン(<button>)かで、
     * 次に保存する進行状況(route)を出し分ける(遷移先の区別には
     * data-demo-tutorial属性ではなくDOM構造の違い=tagNameを使う)。
     * どちらの場合も、続く画面(login / orders.confirm)側で
     * チュートリアルを再開できるよう、新しい進行状況を保存し直す。
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
        } else {
            // ログイン済みで注文ボタンを押した場合(orders.confirmへ遷移する場合)。
            // flowを引き継ぐことで、orders.confirm側が正しい合計ステップ数
            // (11/7)を計算できる(第3-Cで追加)。
            window.DemoTutorial.saveProgress('buyer', { stepIndex: 0, route: 'orders.confirm', flow: currentFlow });
        }
    }, true);
})();
