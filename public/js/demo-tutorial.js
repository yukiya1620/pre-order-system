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
    // 第4段階: 「最後まで完了したか」(seen)とは別に、「このブラウザで
    // 初回自動表示を一度でも実行したか」を独立して管理する。
    // 自動表示は、Escや×で途中終了しても(seenは立たなくても)auto_shownだけは
    // 残すことで、次回訪問時に勝手にまた自動開始しないようにするための仕組み。
    var AUTO_SHOWN_KEY_PREFIX = 'kazeyui_demo_tutorial_';
    var AUTO_SHOWN_KEY_SUFFIX = '_auto_shown_v1';

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
     * hasSeenTutorial/markTutorialSeenと同じ形の、初回自動表示専用の関数。
     * seenとは意図的に別のキーで管理する(「完了したか」と「一度自動表示したか」は
     * 別の問い)。画面固有の接続コード(buyer-home-tutorial.js)が、
     * 自動開始を試みる前にhasAutoShownTutorialを確認し、自動開始した
     * 瞬間にmarkAutoShownTutorialを呼ぶ想定。
     */
    function hasAutoShownTutorial(tutorialKey) {
        return safeGetItem(window.localStorage, AUTO_SHOWN_KEY_PREFIX + tutorialKey + AUTO_SHOWN_KEY_SUFFIX) === '1';
    }

    function markAutoShownTutorial(tutorialKey) {
        safeSetItem(window.localStorage, AUTO_SHOWN_KEY_PREFIX + tutorialKey + AUTO_SHOWN_KEY_SUFFIX, '1');
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
        // route: このツアーが動いているページを表す短い文字列
        // (例: 'products.show')。ページをまたいで再開してよいかどうかの
        // 判定に使うため、ツアー全体を通して1つの値を保持する
        // (ステップごとに変わるものではない)。
        route: null,
        // extra: routeだけでは表現しきれない追加の目印(例: {reason: 'post-auth'})を
        // 進行状況へ一緒に保存したい場合に使う。routeと同様、ツアー全体で1つ保持する。
        extra: null,
        // totalSteps/stepOffset: ページをまたぐチュートリアル(例: 商品一覧の
        // 1ステップ→商品詳細の3ステップ)全体を通した「n / 合計」表示のための値。
        // 省略時はそのページのsteps配列だけを基準にする(従来通り)。
        totalSteps: 0,
        stepOffset: 0,
        // isFinalPage: このページが購入者チュートリアル全体で本当に最後の
        // ページかどうか(第3-C時点ではorders.completeだけがtrue)。
        // trueのページのローカル最終ステップだけが「完了」ボタンを表示し、
        // 押すとfinish()(既読フラグを立てる)へ進む。それ以外のページの
        // ローカル最終ステップは、次へボタン自体を表示せず、利用者が実際の
        // 画面要素を操作することで次のページへ進んでもらう(第3-C参照)。
        isFinalPage: false,
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
     * 対象がその場に存在しても、hidden属性やdisplay:noneで隠れている場合
     * (例: 配達予定日の選択が不要な商品では配達日欄が非表示のまま)は、
     * 「まだ用意できていない」のと同じ扱いにする。offsetWidth/offsetHeightは
     * 非表示要素だと0になることを利用した、追加ライブラリ不要の簡易判定。
     */
    function isVisible(el) {
        return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    }

    /**
     * data-demo-tutorial="<target>" を持つ、かつ表示されている要素を探す。
     * 商品カードのようにJavaScriptがAPIレスポンスをもとに後から生成する
     * 要素の場合、ページ読み込み直後にはまだ存在しないことがあるため、
     * 見つからなければ短い間隔で数回だけ再試行し、それでも見つからなければ
     * そのステップは諦めて次へ進む(チュートリアル全体は継続する)。
     * その商品では最初から表示されない項目(配達日選択欄など)も、この
     * リトライの末にスキップされる形で自然に無視される。
     */
    function findTargetElement(target, attempt, done) {
        var el = document.querySelector('[data-demo-tutorial="' + target + '"]');
        if (el && isVisible(el)) {
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

        var localTotal = state.steps.length;
        // 表示用の分子・分母は、ページをまたぐ全体の通し番号(totalSteps/stepOffset)
        // が渡されていればそちらを優先する。渡されていなければ従来通り
        // このページのsteps配列だけを基準にする。
        // totalStepsには数値だけでなく、合計ステップ数がまだ確定していないことを
        // 示す '?' という文字列も渡せる(例:"5 / ?")。第3-C以降で最終的な
        // ステップ数が固まるまで、途中の数値を決め打ちしないための仕組み。
        var displayTotal = state.totalSteps || localTotal;
        var displayIndex = state.stepIndex + state.stepOffset + 1;
        tooltipStepLabelEl.textContent = displayIndex + ' / ' + displayTotal;
        tooltipDescEl.textContent = step.description || '';

        backButtonEl.hidden = state.stepIndex === 0;

        // isLastは「このページのローカルなsteps配列で最後」というだけの意味で、
        // 購入者チュートリアル全体の最終ステップとは限らない(第3-Cで発見)。
        // 例えば商品詳細ページの3ステップ目(注文へ進む)はローカルには最後だが、
        // この後まだログイン・注文確認・注文完了が続く。ここで誤って「完了」
        // ボタンを表示し利用者がそれを押してしまうと、まだ注文もしていないのに
        // 購入者チュートリアル全体が完了(既読)扱いになってしまう。
        // そのため、start()の呼び出し元がisFinalPage: trueを渡した場合
        // (orders.completeだけ)に限り「完了」ボタンを表示する。それ以外の
        // ページのローカル最終ステップでは、次へボタン自体を表示せず、
        // 実際の画面要素(商品カード・注文ボタン・認証するボタン等)を
        // 利用者本人が操作することで次のページへ進んでもらう設計にする。
        var isLast = state.stepIndex === localTotal - 1;
        var isTrulyFinal = isLast && state.isFinalPage;

        if (isLast && !state.isFinalPage) {
            nextButtonEl.hidden = true;
        } else {
            nextButtonEl.hidden = false;
            nextButtonEl.textContent = isTrulyFinal ? '完了' : '次へ ▶';
        }
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
                // route/extraはステップごとではなく、start()呼び出し時に渡された
                // ツアー全体の値(state.route/state.extra)を使う。以前はstep.routeを
                // 見ていたため、ステップが表示されるたびにroute:nullで上書きされ、
                // browser back→forwardで戻ってきたときに再開できなくなる
                // 不具合があった(第3-B監査で発見)。
                var progressToSave = { stepIndex: state.stepIndex, route: state.route };
                if (state.extra) {
                    for (var extraKey in state.extra) {
                        if (Object.prototype.hasOwnProperty.call(state.extra, extraKey)) {
                            progressToSave[extraKey] = state.extra[extraKey];
                        }
                    }
                }
                saveProgress(state.tutorialKey, progressToSave);

                // 開始時と同様、ステップが変わるたびに「次へ/完了」ボタンへ
                // フォーカスを移す。対象要素はフォーカストラップの対象外なので、
                // 利用者はいつでもTabキーで対象要素側へ移動して操作できる。
                // 非最終ページのローカル最終ステップでは次へボタン自体が
                // 非表示になるため、その場合は戻るボタン、それも無ければ
                // 吹き出し自体(tabindex="-1")へフォーカスを移す。
                if (!nextButtonEl.hidden) {
                    nextButtonEl.focus();
                } else if (!backButtonEl.hidden) {
                    backButtonEl.focus();
                } else {
                    tooltipEl.focus();
                }
            }, 350);
        });
    }

    function next() {
        if (!state.active) {
            return;
        }
        if (state.stepIndex >= state.steps.length - 1) {
            // 基盤側でも、isFinalPage(orders.completeだけ)のときに限り
            // finish()(既読フラグを立てる)へ到達できるようにする。
            // これにより、将来別のページでローカル最終ステップを作った際に、
            // 万一UI側の対策(次へボタンの非表示)をし忘れても、
            // 購入者チュートリアル全体が誤って既読になることはない。
            if (state.isFinalPage) {
                finish();
            }
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
     * 完了(最終ステップで利用者が「完了」を押して終える)。
     * この場合だけ既読フラグ(markTutorialSeen)を立てる。
     * 第3-Cで、「Esc・×ボタン・ページ横断の後片付け」で使うclose()とは
     * 責務を分離した(以前はどちらもmarkTutorialSeenしていたため、
     * 商品詳細で「注文へ進む」を押しただけで既読になってしまっていた)。
     */
    function finish() {
        if (state.tutorialKey) {
            markTutorialSeen(state.tutorialKey);
            clearProgress(state.tutorialKey);
        }
        teardown();
    }

    /**
     * 途中で閉じる(×ボタン・Escキー、またはページ横断のために画面固有の
     * 接続コードが呼ぶ後片付け)。既読フラグは立てない。
     * 途中経過(sessionStorageの進行状況)は破棄するが、呼び出し側が
     * この直後に次ページ用の進行状況をsaveProgressで保存し直す使い方
     * (product-detail-tutorial.js等)を妨げない。
     * 通常操作へすぐ戻れるよう、DOMは完全に取り除く(teardown()に委譲)。
     */
    function close() {
        if (state.tutorialKey) {
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
        state.route = null;
        state.extra = null;
        state.totalSteps = 0;
        state.stepOffset = 0;
        state.isFinalPage = false;
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
     * @param {Array<{target: string, description: string}>} steps
     *   target は対象要素の data-demo-tutorial 属性値。
     * @param {Object} options
     * @param {string} options.tutorialKey 'buyer' や 'farmer' など、既読管理の識別子。
     * @param {string} [options.route] このツアーが動いているページを表す短い文字列
     *   (例: 'products.show')。ページをまたいで再開してよいかどうかの判定に
     *   使うため、sessionStorageの進行状況へそのまま保存される。
     * @param {Object} [options.extra] routeだけでは表現しきれない追加の目印
     *   (例: {reason: 'post-auth'})。指定した場合、進行状況に一緒に保存される。
     * @param {HTMLElement} [options.triggerElement] 呼び出し元の要素(終了時にフォーカスを戻す対象)。
     * @param {number} [options.startAtStep] 途中から再開する場合の開始ステップ番号(0始まり)。
     * @param {number|string} [options.totalSteps] ページをまたぐ全体のステップ数(表示用)。
     *   省略時はsteps.length。まだ確定していない場合は '?' を渡せる(例:"5 / ?")。
     * @param {number} [options.stepOffset] このページのステップが全体の何番目から始まるか(0始まり、表示用)。
     * @param {boolean} [options.isFinalPage] このページが購入者チュートリアル全体で
     *   本当に最後のページかどうか(第3-C時点ではorders.completeだけtrue)。
     *   trueの場合だけ、ローカル最終ステップで「完了」ボタンが表示され、
     *   押すとfinish()(既読フラグを立てる)へ進む。省略時はfalse
     *   (ローカル最終ステップでは次へボタン自体を表示しない)。
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
        state.route = options.route || null;
        state.extra = options.extra || null;
        state.totalSteps = options.totalSteps || 0;
        state.stepOffset = options.stepOffset || 0;
        state.isFinalPage = !!options.isFinalPage;
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

    /**
     * 現在チュートリアルが表示中かどうか。画面固有の接続コード
     * (buyer-home-tutorial.js等)が、「表示中に対象要素がクリックされたか」を
     * 判定する際に使う。
     */
    function isActive() {
        return state.active;
    }

    /**
     * 現在表示しているステップの番号(0始まり、そのページのsteps配列内での
     * ローカルな番号)。画面固有の接続コードが、「まだ最初のステップにいるか」
     * のような判定をしたい場合(例: DOM変化を監視して自動でnext()を呼ぶが、
     * 二重に呼んでしまわないようにする)に使う。
     */
    function currentStepIndex() {
        return state.stepIndex;
    }

    /**
     * bfcache(ブラウザの「戻る/進む」で、ページを再読み込みせずメモリ上の
     * 状態をそのまま復元する仕組み)対策。
     *
     * ブラウザの戻る操作でこのページに復元されたとき、pageshowイベントの
     * event.persisted が true になる。この場合、クリック時点のDOM
     * (オーバーレイ・スポットライト・吹き出しが開いた状態)がそのまま
     * 復元されてしまうことがあるため、いったんteardown()で見た目を
     * 片付ける。teardown()はlocalStorage/sessionStorageの既読・進行状態には
     * 一切触れないため、必要なら画面固有の接続コード側が、自分の
     * pageshowリスナーでsessionStorageの進行状況を見て安全に再開できる。
     */
    window.addEventListener('pageshow', function (event) {
        if (event.persisted && state.active) {
            teardown();
        }
    });

    // ------------------------------------------------------------------
    // 公開API
    // ------------------------------------------------------------------
    window.DemoTutorial = {
        start: start,
        next: next,
        back: back,
        close: close,
        isActive: isActive,
        currentStepIndex: currentStepIndex,
        hasSeenTutorial: hasSeenTutorial,
        markTutorialSeen: markTutorialSeen,
        hasAutoShownTutorial: hasAutoShownTutorial,
        markAutoShownTutorial: markAutoShownTutorial,
        saveProgress: saveProgress,
        loadProgress: loadProgress,
        clearProgress: clearProgress
    };
})();
