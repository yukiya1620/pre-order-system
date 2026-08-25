<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 共通基盤(demo-tutorial.js)のclose()/finish()の責務分離(第3-C)を確認する。
 *
 * 【背景】第3-Bまでは、close()(×ボタン・Escキー・ページ横断の後片付けで
 * 共通して呼ばれる関数)がfinish()と同じくmarkTutorialSeen()を呼んでいた
 * ため、たとえば商品詳細で「注文へ進む」を押しただけで既読フラグが
 * 立ってしまっていた。第3-Cで、購入者チュートリアル全体が
 * (未ログイン11ステップ/ログイン済み7ステップで)完成したことに伴い、
 * 「最終ステップで利用者が『完了』を押したときだけ既読にする」という
 * 要件を満たす必要が生じたため、責務を分離した。
 *
 * - close() = チュートリアルを途中終了する = 既読にはしない
 * - finish() = 最終ステップで利用者が「完了」を押した = 既読にする
 *
 * ここではJSの中身をfile_get_contents + assertStringContainsStringで
 * 確認する(既存のF9パターンを踏襲)。実際のブラウザでの動作確認は
 * 別途実施する。
 */
class DemoTutorialSeenFlagTest extends TestCase
{
    private function jsContent(): string
    {
        return file_get_contents(public_path('js/demo-tutorial.js'));
    }

    /**
     * finish()だけがmarkTutorialSeen()を呼ぶこと(close()は呼ばないこと)を、
     * 関数定義の中身をそれぞれ切り出して確認する。
     */
    public function test_only_finish_calls_mark_tutorial_seen(): void
    {
        $js = $this->jsContent();

        $finishStart = strpos($js, 'function finish()');
        $closeStart = strpos($js, 'function close()');
        $teardownStart = strpos($js, 'function teardown()');

        $this->assertNotFalse($finishStart);
        $this->assertNotFalse($closeStart);
        $this->assertNotFalse($teardownStart);
        $this->assertGreaterThan($finishStart, $closeStart);
        $this->assertGreaterThan($closeStart, $teardownStart);

        $finishBody = substr($js, $finishStart, $closeStart - $finishStart);
        $closeBody = substr($js, $closeStart, $teardownStart - $closeStart);

        // finish()はmarkTutorialSeen()を呼ぶ(既読にする)。
        $this->assertStringContainsString('markTutorialSeen(state.tutorialKey)', $finishBody);

        // close()はmarkTutorialSeen()を呼ばない(既読にしない)。
        $this->assertStringNotContainsString('markTutorialSeen', $closeBody);
    }

    /**
     * close()は、既読処理以外の既存挙動(sessionStorageの進行状態の破棄・
     * teardown()呼び出しによるオーバーレイ/吹き出しの削除・イベント解除・
     * フォーカス復元)は引き続き行うことを確認する
     * (ページ横断の後片付けとして使われ続けるため)。
     */
    public function test_close_still_clears_progress_and_tears_down_ui(): void
    {
        $js = $this->jsContent();

        $closeStart = strpos($js, 'function close()');
        $teardownStart = strpos($js, 'function teardown()');
        $closeBody = substr($js, $closeStart, $teardownStart - $closeStart);

        $this->assertStringContainsString('clearProgress(state.tutorialKey)', $closeBody);
        $this->assertStringContainsString('teardown();', $closeBody);
    }

    /**
     * teardown()自体(オーバーレイ削除・吹き出し削除・resize/scroll/keydown
     * イベント解除・フォーカス復元)は、第3-Cで変更していないことを確認する。
     * close()/finish()どちらから呼ばれても同じ後片付けが行われる。
     */
    public function test_teardown_still_removes_overlay_and_restores_focus(): void
    {
        $js = $this->jsContent();

        $teardownStart = strpos($js, 'function teardown()');
        $handleKeydownStart = strpos($js, 'function handleKeydown(event)');
        $teardownBody = substr($js, $teardownStart, $handleKeydownStart - $teardownStart);

        $this->assertStringContainsString("overlayEl.parentNode.removeChild(overlayEl)", $teardownBody);
        $this->assertStringContainsString("tooltipEl.parentNode.removeChild(tooltipEl)", $teardownBody);
        $this->assertStringContainsString("removeEventListener('resize', scheduleReposition)", $teardownBody);
        $this->assertStringContainsString("removeEventListener('scroll', scheduleReposition, true)", $teardownBody);
        $this->assertStringContainsString("removeEventListener('keydown', handleKeydown)", $teardownBody);
        $this->assertStringContainsString('trigger.focus()', $teardownBody);
    }

    /**
     * Escキー・×ボタンは、どちらも(finish()ではなく)close()を呼ぶ設計に
     * なっていることを確認する(既読にならない経路であることの裏付け)。
     */
    public function test_escape_key_and_close_button_call_close_not_finish(): void
    {
        $js = $this->jsContent();

        // Escキー: handleKeydown内でclose()を呼ぶ。
        $handleKeydownStart = strpos($js, 'function handleKeydown(event)');
        $startFunctionStart = strpos($js, 'function start(steps, options)');
        $handleKeydownBody = substr($js, $handleKeydownStart, $startFunctionStart - $handleKeydownStart);
        $this->assertStringContainsString("event.key === 'Escape'", $handleKeydownBody);
        $this->assertStringContainsString('close();', $handleKeydownBody);
        $this->assertStringNotContainsString('finish();', $handleKeydownBody);

        // ×ボタン: closeButtonElのクリックリスナー内でclose()を呼ぶ。
        $this->assertStringContainsString("closeButtonEl.addEventListener('click', function () {\n            close();\n        });", $js);
    }

    /**
     * next()は、ローカル最終ステップに到達した場合でも、
     * isFinalPage(orders.completeだけがtrueで渡す)でない限りfinish()を
     * 呼ばないことを確認する。
     *
     * 【背景】ローカル最終ステップ(そのページのsteps配列で最後、という意味)は
     * 商品詳細の3ステップ目・ログインの2ステップ目・注文確認の2ステップ目など、
     * orders.complete以外の全ページに存在する。isFinalPageの区別が無いと、
     * これらのページで利用者が吹き出しの「完了」ボタンを押しただけで、
     * まだ注文もしていないのに購入者チュートリアル全体が完了(既読)扱いに
     * なってしまう(第3-Cで発見)。
     */
    public function test_next_calls_finish_only_when_is_final_page(): void
    {
        $js = $this->jsContent();

        $nextStart = strpos($js, 'function next()');
        $backStart = strpos($js, 'function back()');
        $nextBody = substr($js, $nextStart, $backStart - $nextStart);

        $this->assertStringContainsString('state.stepIndex >= state.steps.length - 1', $nextBody);
        $this->assertStringContainsString('if (state.isFinalPage) {', $nextBody);
        $this->assertStringContainsString('finish();', $nextBody);
    }

    /**
     * 吹き出しの「次へ/完了」ボタンは、isFinalPageでないページの
     * ローカル最終ステップでは非表示になり(実際の画面操作で次ページへ
     * 進んでもらうため)、isFinalPageのページのローカル最終ステップでだけ
     * 「完了」ボタンとして表示されることを確認する。
     */
    public function test_next_button_hidden_on_local_last_step_unless_final_page(): void
    {
        $js = $this->jsContent();

        $updateStart = strpos($js, 'function updateTooltipContent()');
        $showCurrentStepStart = strpos($js, 'function showCurrentStep()');
        $updateBody = substr($js, $updateStart, $showCurrentStepStart - $updateStart);

        $this->assertStringContainsString('var isTrulyFinal = isLast && state.isFinalPage;', $updateBody);
        $this->assertStringContainsString('if (isLast && !state.isFinalPage) {', $updateBody);
        $this->assertStringContainsString('nextButtonEl.hidden = true;', $updateBody);
        $this->assertStringContainsString("isTrulyFinal ? '完了' : '次へ ▶'", $updateBody);
    }

    /**
     * start()のoptions.isFinalPageがstate.isFinalPageへ反映され、
     * teardown()で次回に持ち越さないようリセットされることを確認する。
     */
    public function test_is_final_page_option_is_stored_and_reset(): void
    {
        $js = $this->jsContent();

        $this->assertStringContainsString('state.isFinalPage = !!options.isFinalPage;', $js);
        $this->assertStringContainsString('state.isFinalPage = false;', $js);
    }
}
