/**
 * 一般公開デモ専用の操作チュートリアル(ガイドツアー)基盤。
 * DEMO_MODE=true のときだけ layouts/app.blade.php から読み込まれる。
 *
 * 【重要】これは「モーダル」ではない。
 * スポットライトで示した対象要素(商品カード・配達日の選択欄・数量入力・
 * 注文ボタンなど)は、利用者がマウス・タッチ・キーボードで実際に操作できる
 * 状態のままにする。フォーカストラップは行わない。
 * 対象要素以外の暗転部分は、上下左右4枚の帯として実装し、対象要素の上には
 * 何も重ねないことで、対象要素の操作性を一切妨げない。暗転部分自体は
 * クリック・タップしても下の要素へは操作が届かない(誤操作防止)が、
 * 「背景クリックで閉じる」動作は行わない(誤って閉じる事故を避けるため)。
 *
 * このファイルは案内表示だけを行う。注文・認証・SMS認証・DB構造・
 * 権限制御・在庫や予約数・売上処理・demo:reset には一切関与しない。
 *
 * 第1段階(今回)の範囲:
 * 共通の土台(オーバーレイ・スポットライト・吹き出し・ステップ管理・
 * キーボード操作・ARIA・localStorage/sessionStorageの共通関数)のみを実装し、
 * 実際の画面(商品一覧・商品詳細等)へは接続しない。
 * ステップ内容(steps配列)は、次段階以降で各画面から DemoTutorial.start() を
 * 呼び出す際に渡してもらう想定。
 */
(function () {
    'use strict';

    // ------------------------------------------------------------------
    // ストレージまわりの共通関数
    // ------------------------------------------------------------------

    // 末尾の _v1 はチュートリアルの内容を大きく作り直した際に、
    // 過去の既読フラグを引きずらないようにするためのバージョン番号。
    var SEEN_KEY_PREFIX = 'kazeyui_demo_tutorial_';
    var SEEN_KEY_SUFFIX = '_seen_v1';
    var PROGRESS_KEY_PREFIX = 'kazeyui_demo_tutorial_';
    var PROGRESS_KEY_SUFFIX = '_progress_v1';

    /**
     * localStorage/sessionStorageは、プライベートブラウジング設定や
     * ブラウザの制限で例外を投げる場合があるため、必ずtry/catchで包む。
     * 読み書きに失敗しても、チュートリアル自体は(その回だけ再表示される
     * 程度の影響で)動作を継続できるようにする。
     */
    function safeGetItem(storage, key) {
        try {
            return storage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function safeSetItem(storage, key, value) {
        try {
            storage.setItem(key, value);
        } catch (error) {
            // 書き込めなくても致命的ではないため、何もしない。
        }
    }

    function safeRemoveItem(storage, key) {
        try {
            storage.removeItem(key);
        } catch (error) {
            // 削除できなくても致命的ではないため、何もしない。
        }
    }

    /**
     * tutorialKeyは 'buyer' や 'farmer' など、チュートリアルの対象を表す識別子。
     * 購入者側・販売者側で既読状態を別々に管理するために使う。
     */
    function hasSeenTutorial(tutorialKey) {
        return safeGetItem(window.localStorage, SEEN_KEY_PREFIX + tutorialKey + SEEN_KEY_SUFFIX) === '1';
    }

    function markTutorialSeen(tutorialKey) {
        safeSetItem(window.localStorage, SEEN_KEY_PREFIX + tutorialKey + SEEN_KEY_SUFFIX, '1');
    }

    /**
     * ページをまたぐチュートリアル用に、現在の進行状況を一時保存する。
     * タブを閉じたら消えてよい情報なので sessionStorage を使う
     * (localStorageの「既読・完了」フラグとは責務を分ける)。
     *
     * progress の形は { stepIndex: number, route: string } を想定。
     * route は「次のページで再開してよいか」を判定するための目印で、
     * 呼び出し側(各画面のstart呼び出し)が、そのページを表す短い文字列
     * (例: 'products.show')を渡す想定。
     */
    function saveProgress(tutorialKey, progress) {
        safeSetItem(window.sessionStorage, PROGRESS_KEY_PREFIX + tutorialKey + PROGRESS_KEY_SUFFIX, JSON.stringify(progress));
    }

    function loadProgress(tutorialKey) {
        var raw = safeGetItem(window.sessionStorage, PROGRESS_KEY_PREFIX + tutorialKey + PROGRESS_KEY_SUFFIX);
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    }

    function clearProgress(tutorialKey) {
        safeRemoveItem(window.sessionStorage, PROGRESS_KEY_PREFIX + tutorialKey + PROGRESS_KEY_SUFFIX);
    }

    // ------------------------------------------------------------------
    // ツアー本体の状態
    // ------------------------------------------------------------------

    var state = {
        active: false,
        tutorialKey: null,
        steps: [],
        stepIndex: 0,
        triggerElement: null,
        onFinish: null
    };

    var overlayEl = null;
    var segmentEls = {};
    var tooltipEl = null;
    var tooltipStepLabelEl = null;
    var tooltipDescEl = null;
    var backButtonEl = null;
    var nextButtonEl = null;
    var closeButtonEl = null;

    var repositionScheduled = false;

    /**
     * オーバーレイ(暗転4帯)と吹き出しのDOMを、初回だけ作って使い回す。
     */
    function ensureDom() {
        if (overlayEl) {
            return;
        }

        overlayEl = document.createElement('div');
        overlayEl.className = 'demo-tutorial-overlay';
        overlayEl.setAttribute('aria-hidden', 'true');

        ['top', 'bottom', 'left', 'right'].forEach(function (name) {
            var segment = document.createElement('div');
            segment.className = 'demo-tutorial-overlay__segment';
            segment.dataset.segment = name;
            overlayEl.appendChild(segment);
            segmentEls[name] = segment;
        });

        tooltipEl = document.createElement('div');
        tooltipEl.className = 'demo-tutorial-tooltip';
        // モーダルではないため role="dialog" は使わない。あくまで現在位置の
        // 案内(グループ)として扱い、見出し・説明文とはARIAで関連付ける。
        tooltipEl.setAttribute('role', 'group');
        tooltipEl.setAttribute('aria-labelledby', 'demo-tutorial-step-label');
        tooltipEl.setAttribute('aria-describedby', 'demo-tutorial-desc');
        tooltipEl.setAttribute('tabindex', '-1');

        closeButtonEl = document.createElement('button');
        closeButtonEl.type = 'button';
        closeButtonEl.className = 'demo-tutorial-tooltip__close';
        closeButtonEl.setAttribute('aria-label', 'チュートリアルを閉じる');
        closeButtonEl.textContent = '×';
        closeButtonEl.addEventListener('click', function () {
            close();
        });

        tooltipStepLabelEl = document.createElement('p');
        tooltipStepLabelEl.className = 'demo-tutorial-tooltip__step';
        tooltipStepLabelEl.id = 'demo-tutorial-step-label';

        tooltipDescEl = document.createElement('p');
        tooltipDescEl.className = 'demo-tutorial-tooltip__desc';
        tooltipDescEl.id = 'demo-tutorial-desc';

        var actionsEl = document.createElement('div');
        actionsEl.className = 'demo-tutorial-tooltip__actions';

        backButtonEl = document.createElement('button');
        backButtonEl.type = 'button';
        backButtonEl.className = 'demo-tutorial-tooltip__button demo-tutorial-tooltip__button--secondary';
        backButtonEl.textContent = '◀ 戻る';
        backButtonEl.addEventListener('click', function () {
            back();
        });

        nextButtonEl = document.createElement('button');
        nextButtonEl.type = 'button';
        nextButtonEl.className = 'demo-tutorial-tooltip__button';
        nextButtonEl.addEventListener('click', function () {
            next();
        });

        actionsEl.appendChild(backButtonEl);
        actionsEl.appendChild(nextButtonEl);

        tooltipEl.appendChild(closeButtonEl);
        tooltipEl.appendChild(tooltipStepLabelEl);
        tooltipEl.appendChild(tooltipDescEl);
        tooltipEl.appendChild(actionsEl);
    }

    function currentStep() {
        return state.steps[state.stepIndex] || null;
    }

    /**
     * data-demo-tutorial="<target>" を持つ要素を探す。
     * 商品カードのようにJavaScriptがAPIレスポンスをもとに後から生成する
     * 要素の場合、ページ読み込み直後にはまだ存在しないことがあるため、
     * 見つからなければ短い間隔で数回だけ再試行し、それでも見つからなければ
     * そのステップは諦めて次へ進む(チュートリアル全体は継続する)。
     */
    function findTargetElement(target, attempt, done) {
        var el = document.querySelector('[data-demo-tutorial="' + target + '"]');
        if (el) {
            done(el);
            return;
        }

        if (attempt >= 20) {
            done(null);
            return;
        }

        window.setTimeout(function () {
            findTargetElement(target, attempt + 1, done);
        }, 100);
    }

    function clearHighlight() {
        var highlighted = document.querySelectorAll('.demo-tutorial-highlight');
        for (var i = 0; i < highlighted.length; i++) {
            highlighted[i].classList.remove('demo-tutorial-highlight');
        }
    }

    /**
     * 対象要素の位置に合わせて、暗転4帯と吹き出しの位置を計算し直す。
     * スクロール・リサイズのたびに呼び出される。
     */
    function positionAround(targetEl) {
        var rect = targetEl.getBoundingClientRect();
        var viewportWidth = window.innerWidth;
        var viewportHeight = window.innerHeight;

        // 上帯: 対象の上端まで
        segmentEls.top.style.top = '0px';
        segmentEls.top.style.left = '0px';
        segmentEls.top.style.width = viewportWidth + 'px';
        segmentEls.top.style.height = Math.max(0, rect.top) + 'px';

        // 下帯: 対象の下端から画面下まで
        segmentEls.bottom.style.top = Math.max(0, rect.bottom) + 'px';
        segmentEls.bottom.style.left = '0px';
        segmentEls.bottom.style.width = viewportWidth + 'px';
        segmentEls.bottom.style.height = Math.max(0, viewportHeight - rect.bottom) + 'px';

        // 左帯: 対象の高さの範囲だけ、左端から対象の左端まで
        segmentEls.left.style.top = Math.max(0, rect.top) + 'px';
        segmentEls.left.style.left = '0px';
        segmentEls.left.style.width = Math.max(0, rect.left) + 'px';
        segmentEls.left.style.height = Math.max(0, rect.height) + 'px';

        // 右帯: 対象の高さの範囲だけ、対象の右端から画面右端まで
        segmentEls.right.style.top = Math.max(0, rect.top) + 'px';
        segmentEls.right.style.left = Math.max(0, rect.right) + 'px';
        segmentEls.right.style.width = Math.max(0, viewportWidth - rect.right) + 'px';
        segmentEls.right.style.height = Math.max(0, rect.height) + 'px';

        positionTooltip(rect, viewportWidth, viewportHeight);
    }

    /**
     * 吹き出しは、対象の下に十分な余白があれば下側、なければ上側に表示する。
     * 横方向は対象の左端に揃えつつ、画面右端・左端からはみ出さないよう調整する。
     * (縦画面390px前後を考慮し、幅はCSS側でmin(360px, 90vw)に制限してある)
     */
    function positionTooltip(targetRect, viewportWidth, viewportHeight) {
        // 実際の高さを測るため、一旦画面外に置いてから測定する。
        tooltipEl.style.visibility = 'hidden';
        tooltipEl.style.top = '0px';
        tooltipEl.style.left = '0px';
        var tooltipRect = tooltipEl.getBoundingClientRect();

        var spacing = 12;
        var top;
        var spaceBelow = viewportHeight - targetRect.bottom;

        if (spaceBelow >= tooltipRect.height + spacing) {
            top = targetRect.bottom + spacing;
        } else if (targetRect.top >= tooltipRect.height + spacing) {
            top = targetRect.top - tooltipRect.height - spacing;
        } else {
            // 上下どちらにも十分な余白がない(対象が非常に大きい・画面が低いなど)
            // 場合は、画面内に収まるようできる範囲で調整する。
            top = Math.max(spacing, Math.min(targetRect.top, viewportHeight - tooltipRect.height - spacing));
        }

        var left = targetRect.left;
        if (left + tooltipRect.width + spacing > viewportWidth) {
            left = viewportWidth - tooltipRect.width - spacing;
        }
        if (left < spacing) {
            left = spacing;
        }

        tooltipEl.style.top = top + 'px';
        tooltipEl.style.left = left + 'px';
        tooltipEl.style.visibility = 'visible';
    }

    function scheduleReposition() {
        if (!state.active || repositionScheduled) {
            return;
        }
        repositionScheduled = true;
        window.requestAnimationFrame(function () {
            repositionScheduled = false;
            var step = currentStep();
            if (!step || !step.targetEl) {
                return;
            }
            positionAround(step.targetEl);
        });
    }

    function updateTooltipContent() {
        var step = currentStep();
        if (!step) {
            return;
        }

        var total = state.steps.length;
        tooltipStepLabelEl.textContent = (state.stepIndex + 1) + ' / ' + total;
        tooltipDescEl.textContent = step.description || '';

        backButtonEl.hidden = state.stepIndex === 0;

        var isLast = state.stepIndex === total - 1;
        nextButtonEl.textContent = isLast ? '完了' : '次へ ▶';
    }

    /**
     * 現在のステップを画面に表示する。対象要素が見つからない場合は
     * そのステップを飛ばして次のステップへ進む(それも無ければ終了する)。
     */
    function showCurrentStep() {
        var step = currentStep();
        if (!step) {
            finish();
            return;
        }

        findTargetElement(step.target, 0, function (el) {
            if (!state.active) {
                // 探している間に閉じられた場合は何もしない。
                return;
            }

            if (!el) {
                // 対象が見つからない場合はスキップする。
                if (state.stepIndex < state.steps.length - 1) {
                    state.stepIndex += 1;
                    showCurrentStep();
                } else {
                    finish();
                }
                return;
            }

            clearHighlight();
            step.targetEl = el;
            el.classList.add('demo-tutorial-highlight');

            if (typeof el.scrollIntoView === 'function') {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            // scrollIntoViewのスムーズスクロールが落ち着くのを少し待ってから位置計算する。
            window.setTimeout(function () {
                if (!state.active) {
                    return;
                }
                positionAround(el);
                updateTooltipContent();
                saveProgress(state.tutorialKey, { stepIndex: state.stepIndex, route: step.route || null });

                // 開始時と同様、ステップが変わるたびに「次へ/完了」ボタンへ
                // フォーカスを移す。対象要素はフォーカストラップの対象外なので、
                // 利用者はいつでもTabキーで対象要素側へ移動して操作できる。
                nextButtonEl.focus();
            }, 350);
        });
    }

    function next() {
        if (!state.active) {
            return;
        }
        if (state.stepIndex >= state.steps.length - 1) {
            finish();
            return;
        }
        state.stepIndex += 1;
        showCurrentStep();
    }

    function back() {
        if (!state.active || state.stepIndex === 0) {
            return;
        }
        state.stepIndex -= 1;
        showCurrentStep();
    }

    /**
     * 完了(最終ステップまで進めて終える)。既読フラグを立てたうえで終了する。
     */
    function finish() {
        if (state.tutorialKey) {
            markTutorialSeen(state.tutorialKey);
            clearProgress(state.tutorialKey);
        }
        teardown();
    }

    /**
     * 途中で閉じる。既読フラグは立てるが(毎回は強制表示しないため)、
     * 途中経過(sessionStorageの進行状況)は破棄する。
     * 通常操作へすぐ戻れるよう、DOMを完全に取り除く。
     */
    function close() {
        if (state.tutorialKey) {
            markTutorialSeen(state.tutorialKey);
            clearProgress(state.tutorialKey);
        }
        teardown();
    }

    function teardown() {
        state.active = false;
        clearHighlight();

        if (overlayEl && overlayEl.parentNode) {
            overlayEl.parentNode.removeChild(overlayEl);
        }
        if (tooltipEl && tooltipEl.parentNode) {
            tooltipEl.parentNode.removeChild(tooltipEl);
        }

        window.removeEventListener('resize', scheduleReposition);
        window.removeEventListener('scroll', scheduleReposition, true);
        document.removeEventListener('keydown', handleKeydown);

        var trigger = state.triggerElement;
        var onFinish = state.onFinish;

        state.tutorialKey = null;
        state.steps = [];
        state.stepIndex = 0;
        state.triggerElement = null;
        state.onFinish = null;

        // 終了時のフォーカス移動: 開始のきっかけとなった要素
        // (「使い方を見る」ボタンなど)へフォーカスを戻す。
        if (trigger && typeof trigger.focus === 'function') {
            trigger.focus();
        }

        if (typeof onFinish === 'function') {
            onFinish();
        }
    }

    function handleKeydown(event) {
        if (event.key === 'Escape' || event.key === 'Esc') {
            close();
        }
    }

    /**
     * チュートリアルを開始する。
     *
     * @param {Array<{target: string, description: string, route?: string}>} steps
     *   target は対象要素の data-demo-tutorial 属性値。
     *   route は、ページをまたぐチュートリアルで次のページ再開時に使う目印
     *   (省略可)。
     * @param {Object} options
     * @param {string} options.tutorialKey 'buyer' や 'farmer' など、既読管理の識別子。
     * @param {HTMLElement} [options.triggerElement] 呼び出し元の要素(終了時にフォーカスを戻す対象)。
     * @param {number} [options.startAtStep] 途中から再開する場合の開始ステップ番号(0始まり)。
     * @param {Function} [options.onFinish] 終了時に呼ばれるコールバック。
     */
    function start(steps, options) {
        options = options || {};

        if (!steps || steps.length === 0) {
            return;
        }

        if (state.active) {
            teardown();
        }

        ensureDom();

        state.active = true;
        state.tutorialKey = options.tutorialKey || 'default';
        state.steps = steps;
        state.stepIndex = options.startAtStep && options.startAtStep < steps.length ? options.startAtStep : 0;
        state.triggerElement = options.triggerElement || null;
        state.onFinish = options.onFinish || null;

        document.body.appendChild(overlayEl);
        document.body.appendChild(tooltipEl);

        window.addEventListener('resize', scheduleReposition);
        // スクロールイベントはバブリングしないため、キャプチャフェーズで拾う
        // (ページ内の一部だけがスクロールする要素があっても位置がずれないようにする)。
        window.addEventListener('scroll', scheduleReposition, true);
        document.addEventListener('keydown', handleKeydown);

        showCurrentStep();
    }

    // ------------------------------------------------------------------
    // 公開API
    // ------------------------------------------------------------------
    window.DemoTutorial = {
        start: start,
        next: next,
        back: back,
        close: close,
        hasSeenTutorial: hasSeenTutorial,
        markTutorialSeen: markTutorialSeen,
        loadProgress: loadProgress,
        clearProgress: clearProgress
    };
})();
