<?php

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * php artisan demo:reset (ResetDemoDataコマンド)の安全性確認。
 * PortfolioDemoSeeder自体の削除・判定ロジックの詳細は PortfolioDemoSeederTest でカバー済みのため、
 * ここでは「productionガード」「コマンド経由の呼び出し」「複数回実行の安定性」に焦点を当てる。
 */
class ResetDemoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * @return callable(): void  元の環境設定に戻すための後始末コールバック
     */
    private function actingAsProduction(): callable
    {
        $originalEnvironment = app()->environment();
        $this->app->detectEnvironment(fn () => 'production');

        return function () use ($originalEnvironment): void {
            $this->app->detectEnvironment(fn () => $originalEnvironment);
        };
    }

    public function test_production_with_demo_mode_disabled_rejects_reset(): void
    {
        config(['demo.enabled' => false]);
        $restore = $this->actingAsProduction();

        Log::spy();

        try {
            $this->artisan('demo:reset')->assertExitCode(1);
        } finally {
            $restore();
        }

        $this->assertSame(0, User::where('email', 'demo-farmer@example.com')->count());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_production_with_demo_mode_enabled_allows_reset(): void
    {
        config(['demo.enabled' => true]);
        $restore = $this->actingAsProduction();

        Log::spy();

        try {
            $this->artisan('demo:reset')->assertExitCode(0);
        } finally {
            $restore();
        }

        $this->assertSame(1, User::where('email', 'demo-farmer@example.com')->count());
        $this->assertSame(10, Order::where('order_number', 'like', 'DEMO-%')->count());
        Log::shouldHaveReceived('info')->with('[demo:reset] デモデータのリセットが完了しました。', \Mockery::type('array'));
    }

    /**
     * PortfolioDemoSeederを直接(db:seed相当のrun()経由で)呼び出した場合は、
     * DEMO_MODE=trueであってもproductionでは従来通り一切実行できないことを確認する。
     * 「本番での特別な力はdemo:resetコマンドだけに閉じ込める」設計の要である。
     */
    public function test_direct_seeder_run_still_refuses_production_even_when_demo_mode_enabled(): void
    {
        config(['demo.enabled' => true]);
        $restore = $this->actingAsProduction();
        $thrown = null;

        try {
            (new \Database\Seeders\PortfolioDemoSeeder())->run();
        } catch (RuntimeException $e) {
            $thrown = $e;
        } finally {
            $restore();
        }

        $this->assertNotNull($thrown);
        $this->assertSame(0, User::where('email', 'demo-farmer@example.com')->count());
    }

    public function test_direct_seeder_run_refuses_production_when_demo_mode_disabled(): void
    {
        config(['demo.enabled' => false]);
        $restore = $this->actingAsProduction();
        $thrown = null;

        try {
            (new \Database\Seeders\PortfolioDemoSeeder())->run();
        } catch (RuntimeException $e) {
            $thrown = $e;
        } finally {
            $restore();
        }

        $this->assertNotNull($thrown);
        $this->assertSame(0, User::where('email', 'demo-farmer@example.com')->count());
    }

    public function test_reset_can_be_run_multiple_times_and_returns_to_the_same_initial_state(): void
    {
        config(['demo.enabled' => true]);

        $this->artisan('demo:reset')->assertExitCode(0);
        $firstFarmerId = User::where('email', 'demo-farmer@example.com')->value('id');

        $this->artisan('demo:reset')->assertExitCode(0);
        $secondFarmerId = User::where('email', 'demo-farmer@example.com')->value('id');

        $this->assertSame(1, User::where('email', 'demo-farmer@example.com')->count());
        $this->assertSame(10, Order::where('order_number', 'like', 'DEMO-%')->count());
        // 再作成されている(idが変わっている)が、件数は変わらないことを確認する
        $this->assertNotSame($firstFarmerId, $secondFarmerId);
    }

    public function test_reset_does_not_delete_unrelated_normal_data(): void
    {
        config(['demo.enabled' => true]);

        $normalFarmer = User::factory()->farmer()->create(['email' => 'farmer@example.com']);
        $category = Category::create(['name' => '通常カテゴリー', 'display_order' => 99]);
        $normalProduct = Product::create([
            'name' => '通常商品',
            'description' => 'デモとは無関係の商品',
            'category_id' => $category->id,
            'unit_label' => '個',
        ]);

        $this->artisan('demo:reset')->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => $normalFarmer->id, 'email' => 'farmer@example.com']);
        $this->assertDatabaseHas('products', ['id' => $normalProduct->id, 'name' => '通常商品']);
    }

    public function test_reset_aborts_and_leaves_data_unchanged_when_safety_check_fails(): void
    {
        config(['demo.enabled' => true]);

        // デモ注文に紐づかない、デモ商品と同名の商品(PortfolioDemoSeeder側の安全チェックに違反する状態)
        $category = Category::create(['name' => '季節商品', 'display_order' => 1]);
        $ambiguousProduct = Product::create([
            'name' => '朝採れとうもろこし',
            'description' => 'デモ注文に紐づかない、同名の非デモ商品',
            'category_id' => $category->id,
            'unit_label' => '本',
        ]);

        Log::spy();

        $this->artisan('demo:reset')->assertExitCode(1);

        $this->assertDatabaseHas('products', ['id' => $ambiguousProduct->id]);
        $this->assertSame(1, Product::where('name', '朝採れとうもろこし')->count());
        $this->assertSame(0, User::where('email', 'demo-farmer@example.com')->count());
        Log::shouldHaveReceived('error')->once();
    }
}
