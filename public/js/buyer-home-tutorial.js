/**
 * 購入者トップ(商品一覧)専用の、操作チュートリアル接続コード。
 * DEMO_MODE=true のときだけ読み込まれる(resources/views/buyer-home.blade.php)。
 *
 * 共通基盤(demo-tutorial.js)や商品取得・表示処理(buyer-home.js)には
 * 一切手を加えず、この画面のステップ定義と「使い方を見る」ボタンの
 * 制御だけをここに閉じ込める。
 *
 * 第3-B: 商品一覧(1ステップ)→商品詳細(3ステップ)→ログイン(2ステップ)の
 * ページ横断に加えて、認証成功後にbuyer.homeへ戻ってきたときの専用案内
 * (「もう一度商品を選びましょう」)まで。
 * 第3-C(今回)の範囲: orders.confirm・注文確定・注文完了までの接続。
 * これに伴い、合計ステップ数が確定した('?'表示の解消)。
 * 未ログインから開始(認証を経由する)経路と、最初からログイン済みで
 * 開始する経路とで、合計ステップ数(11 / 7)が変わる。この区別を
 * 「flow」('guest' / 'authenticated')という値として進行状況(sessionStorage)に
 * 一緒に保存し、ページを横断してもorders.confirm等が正しいステップ数・
 * 開始位置を計算できるようにする。
 */
(function () {
    if (!window.DemoTutorial) {
        return;
    }

    // 経路ごとの合計ステップ数(第2章「未ログインから開始した場合の完成形」
    // 「最初からログイン済みの場合」に対応)。
    var TOTAL_STEPS_GUEST = 11;
    var TOTAL_STEPS_AUTHENTICATED = 7;

    /**
     * buyer.home自身がログイン中かどうかの判定。認証成功後の専用案内
     * (postAuthSteps)と同じく、buyer-home.blade.phpの@authブロックが
     * 出力する既存の要素(#buyer-home-logout-button)の有無で判定するだけで、
     * Blade自体は変更しない。
     */
    function isBuyerLoggedIn() {
        return !!document.getElementById('buyer-home-logout-button');
    }

    // 商品カードは public/js/buyer-home.js がAPIレスポンスをもとに
    // 動的に生成する。カードの描画が終わるまでは data-demo-tutorial 属性を
    // 持つ要素が存在しないため、buyer-home.js が描画完了時に発火する
    // カスタムイベント(demo-tutorial:buyer-products-rendered)を合図にする。
    // setTimeoutの秒数決め打ちに頼らない、疎結合な連携方法。
    var productsReady = false;

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
        if (postAuthPending) {
            postAuthPending = false;
            beginPostAuthTutorial();
        }
    });

    // ------------------------------------------------------------------
    // 認証成功後の専用案内(「もう一度、注文したい商品を選んでみましょう」)
    // ------------------------------------------------------------------
    //
    // 第3-B監査の結論により、認証(SMSコード確認)に成功すると常にbuyer.home
    // (商品一覧)へ戻る現在の仕様(auth-form.jsのredirectAfterAuth)は変更しない。
    // また、認証前に選んでいた商品・配達日・数量はURLクエリ文字列のみに
    // 保持されており、ログイン画面をまたぐと失われる現在の仕様も変更しない。
    // そのためログイン後は、通常の「使い方を見る」ボタンによるステップ1とは
    // 別の専用ステップとして、もう一度商品を選ぶよう案内する。
    //
    // 「認証後専用」であることの判定は、次の2つを両方満たす場合だけに絞る。
    //   1. sessionStorageに { route: 'buyer.home', reason: 'post-auth' } の
    //      予約がある(auth-form-tutorial.jsが「認証する」ボタン押下時に保存)
    //   2. 実際にログイン中である(#buyer-home-logout-buttonの有無で判定。
    //      buyer-home.blade.phpの@authブロックが出力する既存の要素をそのまま
    //      利用するだけで、Blade自体は変更しない)
    // 認証に失敗してこのページへ来た場合は2を満たさないため、専用案内は
    // 表示されない。
    var postAuthSteps = [
        {
            target: 'buyer-product-card',
            description: 'ログインできました。もう一度、注文したい商品を選んでみましょう。'
        }
    ];

    var postAuthActive = false;
    var postAuthPending = false;

    function beginPostAuthTutorial() {
        postAuthActive = true;
        window.DemoTutorial.start(postAuthSteps, {
            tutorialKey: 'buyer',
            route: 'buyer.home',
            // reason/flowは進行状況に一緒に保存され、次に読み込むときも
            // 「認証を経由した専用案内である」ことを区別できる。
            // 認証を経由するのは常にguest経路のみのため、flowは固定でよい。
            extra: { reason: 'post-auth', flow: 'guest' },
            totalSteps: TOTAL_STEPS_GUEST,
            // 商品一覧1 + 商品詳細3 + ログイン2 = 6個の後に続く専用案内。
            stepOffset: 6,
            onFinish: function () {
                postAuthActive = false;
            }
        });
    }

    /**
     * 認証後専用案内(ステップ7)が表示されている間に、実際に商品カードの
     * リンクがクリックされたら、商品詳細(products.show)側で
     * 「まとめステップ(ステップ8)」から自然に続けられるよう、遷移前に
     * 進行状況を保存しておく。通常フロー用のリスナー(下記)とは別に
     * 用意する(postAuthActiveとtutorialActiveは同時にtrueにならない)。
     */
    document.addEventListener('click', function (event) {
        if (!postAuthActive) {
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

        // reason: 'post-auth' を引き継ぐことで、product-detail-tutorial.js側が
        // 「まとめステップ(1ステップ版)」を使うべきと判定できる。
        window.DemoTutorial.saveProgress('buyer', {
            stepIndex: 0,
            route: 'products.show',
            reason: 'post-auth',
            flow: 'guest'
        });
    }, true);

    /**
     * sessionStorageの進行状況を見て、認証直後の専用案内を再開してよいか
     * 判定する。ページの初回読み込み時に加えて、ブラウザの戻る操作で
     * このページがbfcacheから復元された場合(pageshowイベント)にも呼び出す。
     */
    function tryResumePostAuth() {
        if (window.DemoTutorial.isActive()) {
            return;
        }

        var progress = window.DemoTutorial.loadProgress('buyer');
        var isLoggedIn = !!document.getElementById('buyer-home-logout-button');

        if (!progress || progress.route !== 'buyer.home' || progress.reason !== 'post-auth' || !isLoggedIn) {
            return;
        }

        if (productsReady) {
            beginPostAuthTutorial();
        } else {
            // 商品カードの描画がまだ終わっていない場合は、描画完了イベントを待つ。
            postAuthPending = true;
        }
    }

    tryResumePostAuth();

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            tryResumePostAuth();
        }
    });

    // ------------------------------------------------------------------
    // 通常の「使い方を見る」ボタン(ステップ1: 商品一覧)
    // ------------------------------------------------------------------

    var startButton = document.getElementById('buyer-home-tutorial-start-button');
    if (!startButton) {
        return;
    }

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
    // beginTutorial()で決めたflowを、後続の商品カードクリック時の
    // saveProgressへ引き継ぐために保持しておく。
    var currentFlow = 'guest';

    var pendingStart = false;
    var readyTimeoutId = null;

    function resetButton() {
        startButton.disabled = false;
        startButton.textContent = DEFAULT_BUTTON_LABEL;
    }

    function beginTutorial() {
        resetButton();
        tutorialActive = true;
        // ログイン済み利用者が「使い方を見る」から始めた場合は、SMS認証を
        // 案内しない短縮フロー(7ステップ)にする(第4章)。
        currentFlow = isBuyerLoggedIn() ? 'authenticated' : 'guest';
        window.DemoTutorial.start(steps, {
            tutorialKey: 'buyer',
            route: 'buyer.home',
            triggerElement: startButton,
            extra: { flow: currentFlow },
            totalSteps: currentFlow === 'authenticated' ? TOTAL_STEPS_AUTHENTICATED : TOTAL_STEPS_GUEST,
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
        // flowを引き継ぐことで、products.show側・orders.confirm側が正しい
        // 合計ステップ数(11/7)を計算できる。
        window.DemoTutorial.saveProgress('buyer', { stepIndex: 0, route: 'products.show', flow: currentFlow });
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
