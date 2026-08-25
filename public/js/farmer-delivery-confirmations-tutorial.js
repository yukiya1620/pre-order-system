/**
 * 配達のかくにん(farmer.delivery-confirmations)専用の、操作チュートリアル
 * 接続コード。DEMO_MODE=true のときだけ読み込まれる
 * (resources/views/farmer-delivery-confirmations.blade.php)。
 *
 * 共通基盤(demo-tutorial.js)や配達確認の表示・回答処理
 * (farmer-delivery-confirmations.js)には一切手を加えず、この画面の
 * ステップ定義・農家ホームからの再開判定・農家ホームへの引き継ぎだけを
 * ここに閉じ込める。
 *
 * 第5-B(今回)の範囲: 最初の注文カード→「もどる」リンク、の2ステップ。
 * 「配達できる」「日程変更」「数量変更」「キャンセル相談」等の回答ボタンは
 * スポットライト対象に含めても、チュートリアル側から実際にクリックする
 * ことは一切無い(利用者本人が押すかどうかは自由)。
 */
(function () {
    if (!window.DemoTutorial) {
        return;
    }

    var TOTAL_STEPS = 9;
    // 農家ホーム(通常)に2ステップあるため、このページは全体の3番目から始まる。
    var STEP_OFFSET = 2;

    var steps = [
        {
            target: 'farmer-delivery-confirmation-card',
            description: '注文内容と、配達可否・日程変更などの対応方法を確認できます。今回は内容を見るだけにします。'
        },
        {
            target: 'farmer-back-link',
            description: '確認したら、農家ホームへ戻りましょう。'
        }
    ];

    var tutorialActive = false;

    // farmer-delivery-confirmations.jsがカードの描画を終えたことを示す
    // カスタムイベント。fetchが速く終わってイベントがtryResume()より先に
    // 発火する心配は無い(deferスクリプトはHTML内の出現順に同期実行され、
    // fetchの完了は必ずその後の非同期タイミングになるため)が、
    // 念のためフラグで管理しておく。
    var contentRendered = false;
    var pendingResume = false;

    document.addEventListener('demo-tutorial:farmer-delivery-confirmations-rendered', function () {
        contentRendered = true;
        if (pendingResume) {
            pendingResume = false;
            doStart();
        }
    });

    function doStart() {
        var progress = window.DemoTutorial.loadProgress('farmer');
        if (!progress || progress.route !== 'farmer.delivery-confirmations') {
            return;
        }

        tutorialActive = true;
        window.DemoTutorial.start(steps, {
            tutorialKey: 'farmer',
            route: 'farmer.delivery-confirmations',
            totalSteps: TOTAL_STEPS,
            stepOffset: STEP_OFFSET,
            onFinish: function () {
                tutorialActive = false;
            }
        });
    }

    /**
     * sessionStorageの進行状況を見て、必要なら続きを開始する。
     * 農家ホームから正しく遷移してきた場合(route==='farmer.delivery-confirmations')
     * だけ自動開始し、直接アクセス等は何もしない。0件・API失敗等でカードの
     * 描画完了イベントが発火しない場合は、チュートリアルは開始されない
     * (通常の画面表示・エラー表示はそのまま機能する)。
     */
    function tryResume() {
        if (window.DemoTutorial.isActive()) {
            return;
        }

        var progress = window.DemoTutorial.loadProgress('farmer');
        if (!progress || progress.route !== 'farmer.delivery-confirmations') {
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
     * 「もどる」リンク(ステップ2、ローカル最終ステップ)が実際にクリックされた
     * 場合、この後ページが遷移するため、先にオーバーレイ・進行状態を
     * 片付けておく(close()。既読フラグは立たない)。実際の遷移(通常の
     * リンク遷移)には一切手を加えない。農家ホーム側で3回目の専用案内を
     * 再開できるよう、reason付きの進行状況を保存し直す。
     */
    document.addEventListener('click', function (event) {
        if (!tutorialActive) {
            return;
        }

        var target = event.target.closest('[data-demo-tutorial="farmer-back-link"]');
        if (!target) {
            return;
        }

        window.DemoTutorial.close();
        window.DemoTutorial.saveProgress('farmer', {
            stepIndex: 0,
            route: 'farmer.home',
            reason: 'after-delivery-confirmations'
        });
    }, true);
})();
