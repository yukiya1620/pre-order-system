/**
 * ログイン画面(login)専用の、操作チュートリアル接続コード。
 * DEMO_MODE=true のときだけ読み込まれる(resources/views/auth-form.blade.php)。
 *
 * 共通基盤(demo-tutorial.js)や認証処理(auth-form.js・SmsAuthController等)には
 * 一切手を加えず、この画面のステップ定義・商品詳細からの再開判定・
 * 認証成功後にbuyer.homeへ引き継ぐための予約保存だけをここに閉じ込める。
 *
 * 【重要】認証処理そのもの(電話番号検証・SMS認証コードの送信/検証・
 * ログイン成功後のリダイレクト先)は一切変更しない。第3-B監査の結果、
 * 認証成功後は常にbuyer.home(商品一覧)へ戻る現在の仕様(redirectAfterAuth)を
 * そのまま前提とする。
 *
 * 第3-B(今回)の範囲: 電話番号入力→認証コード入力、の2ステップ、
 * および認証成功後にbuyer.homeで専用案内を再開するための予約保存のみ。
 * 会員登録(register)画面は対象外(mode==='login'のときだけ動作する)。
 */
(function () {
    if (!window.DemoTutorial) {
        return;
    }

    var container = document.querySelector('.auth-form-page');
    if (!container || container.dataset.mode !== 'login') {
        return;
    }

    // 購入者チュートリアル全体の通し番号表示に使う値。第3-Cで注文確認・
    // 注文完了のステップが加わるまで、合計ステップ数はまだ確定していないため、
    // 具体的な数値を決め打ちせず '?' を表示する(例:"5 / ?")。
    var TOTAL_STEPS = '?';
    // 商品一覧1 + 商品詳細3 = 4個の後に続くため、このページは5番目から始まる。
    var STEP_OFFSET = 4;

    /**
     * 一般公開デモ環境で、実際にログインできるデモ購入者の電話番号を案内文へ
     * 組み込む。値はBlade側がconfig('demo.tutorial_buyer_phone')をそのまま
     * data属性へ出力したものを読むだけで、この関数自体に電話番号を
     * ハードコードしていない。設定が無い場合(data属性が無い場合)は、
     * 電話番号の案内を付け足さない。
     */
    function buildPhoneStepDescription() {
        var base = '注文にはログインが必要です。';
        var demoPhone = container.dataset.demoTutorialBuyerPhone;
        if (demoPhone) {
            base += ' デモ用の電話番号(' + demoPhone + ')でログインできます。';
        }
        return base;
    }

    var steps = [
        {
            target: 'buyer-login-phone',
            description: buildPhoneStepDescription()
        },
        {
            target: 'buyer-login-code',
            description: '画面に表示された認証コードを入力します。実際にSMSを受け取らなくても体験できます。'
        }
    ];

    var tutorialActive = false;

    /**
     * sessionStorageの進行状況を見て、必要なら続きを開始する。
     * 商品詳細(products.show)のログインリンクから正しく遷移してきた場合
     * (route==='login')だけ自動開始し、ログイン画面を直接開いた場合は
     * 何もしない。ブラウザの戻る操作でこのページがbfcacheから復元された
     * 場合(pageshowイベント)にも同じ判定を再実行する。
     */
    function tryResume() {
        if (window.DemoTutorial.isActive()) {
            return;
        }

        var progress = window.DemoTutorial.loadProgress('buyer');
        if (!progress || progress.route !== 'login') {
            return;
        }

        var startAtStep = typeof progress.stepIndex === 'number' ? progress.stepIndex : 0;

        tutorialActive = true;
        window.DemoTutorial.start(steps, {
            tutorialKey: 'buyer',
            route: 'login',
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
     * 実際の画面(auth-form.js)が電話番号ステップから認証コードステップへ
     * 切り替わったことを検知したら、チュートリアル側のステップも自動的に
     * 追従させる。「吹き出しの次へボタン」を押さなくても、実際の操作
     * (電話番号を入力して画面の送信ボタンを押す)だけでチュートリアルが
     * 自然に進む。DOM構造(hidden属性の切り替え)を外部から観測するだけで、
     * auth-form.js自体には一切手を入れない。
     *
     * currentStepIndex()===0の確認は、hidden属性の変化が複数のステップ要素で
     * 同時に起きた場合にMutationObserverのコールバックが複数回呼ばれても、
     * 誤って2回以上next()を呼んでチュートリアルを飛び級させないための安全策。
     */
    var wizardEl = document.getElementById('auth-step-wizard');
    if (wizardEl && typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function () {
            if (!tutorialActive || !window.DemoTutorial.isActive()) {
                return;
            }
            var visibleStep = wizardEl.querySelector('.auth-step:not([hidden])');
            if (!visibleStep) {
                return;
            }
            if (visibleStep.dataset.stepName === 'code' && window.DemoTutorial.currentStepIndex() === 0) {
                window.DemoTutorial.next();
            }
        });
        observer.observe(wizardEl, { attributes: true, attributeFilter: ['hidden'], subtree: true });
    }

    /**
     * 「認証する」ボタンが押された時点で、実際に認証が成功するかどうかは
     * まだ分からないが、成功した場合に備えてbuyer.home側の専用案内
     * (認証直後の再開案内)をあらかじめ予約しておく。
     *
     * 認証に失敗した場合(コード誤り等でこのページに留まる場合)にこの予約が
     * 残っても問題ない: buyer.home側は「この予約があること」に加えて
     * 「実際にログイン中であること」も併せて確認するため
     * (buyer-home-tutorial.jsのisLoggedInチェック)、認証していなければ
     * 専用案内は表示されない。
     */
    document.addEventListener('click', function (event) {
        if (!event.target.closest('#auth-verify-button')) {
            return;
        }
        window.DemoTutorial.saveProgress('buyer', { stepIndex: 0, route: 'buyer.home', reason: 'post-auth' });
    }, true);
})();
