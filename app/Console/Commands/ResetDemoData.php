<?php

namespace App\Console\Commands;

use Database\Seeders\PortfolioDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 一般公開デモ環境専用: PortfolioDemoSeederが管理するデモデータだけを初期状態へ戻すコマンド。
 *
 * 対話確認は行わない(Cron/Laravel Schedulerからの無人実行を想定)。
 * production環境で実行してよいのは、DEMO_MODE=true が明示的に設定されている場合のみ。
 * それ以外(DEMO_MODE=false・未設定)のproduction環境では、
 * PortfolioDemoSeeder::run()側のガードにより、通常のdb:seed経由も含めて従来通り一切実行できない。
 *
 * このコマンドは PortfolioDemoSeeder::execute() を直接呼び出す(run()は経由しない)。
 * migrate:fresh・db:wipe等、デモデータ以外を巻き込みうる操作は一切行わない。
 */
class ResetDemoData extends Command
{
    protected $signature = 'demo:reset';

    protected $description = '一般公開デモ環境専用: PortfolioDemoSeederが管理するデモデータだけを初期状態にリセットする';

    public function handle(): int
    {
        // コマンド側でも独立してDEMO_MODEを確認する(Seeder側のガードとの二重チェック)。
        // これにより、将来Seeder側の実装が変わった場合でも、
        // このコマンド単体でDEMO_MODE=false環境での実行を防げる。
        if (config('demo.enabled') !== true) {
            $this->error('DEMO_MODEが有効になっていないため、demo:resetは実行できません。');
            Log::warning('[demo:reset] DEMO_MODEが無効なため実行を拒否しました。', [
                'app_env' => app()->environment(),
            ]);

            return self::FAILURE;
        }

        Log::info('[demo:reset] デモデータのリセットを開始します。', [
            'app_env' => app()->environment(),
        ]);

        try {
            app(PortfolioDemoSeeder::class)
                ->setContainer(app())
                ->setCommand($this)
                ->execute();
        } catch (Throwable $exception) {
            Log::error('[demo:reset] デモデータのリセットに失敗しました。', [
                'app_env' => app()->environment(),
                'message' => $exception->getMessage(),
            ]);
            $this->error('デモデータのリセットに失敗しました: '.$exception->getMessage());

            return self::FAILURE;
        }

        Log::info('[demo:reset] デモデータのリセットが完了しました。', [
            'app_env' => app()->environment(),
        ]);
        $this->info('デモデータのリセットが完了しました。');

        return self::SUCCESS;
    }
}
