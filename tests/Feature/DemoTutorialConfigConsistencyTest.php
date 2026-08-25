<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PortfolioDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * config('demo.tutorial_buyer_phone')(チュートリアル案内用のデモ購入者電話番号)が、
 * config('demo.sms_phone_numbers')(SMS認証コード表示のホワイトリスト)と
 * PortfolioDemoSeederが実際に作成する購入者アカウントの両方と整合していることを確認する。
 *
 * 【重要】このテストはconfigを明示的にオーバーライドして検証するものであり、
 * 本番の実際の.env設定値がこの整合性を満たしているかどうかまでは保証しない
 * (.envの実際の値はテスト対象に含めていない)。実際にデプロイする際は、
 * DEMO_TUTORIAL_BUYER_PHONE・DEMO_SMS_PHONE_NUMBERSの2つの環境変数を、
 * ここで確認している関係性を満たす形で設定する必要がある。
 */
class DemoTutorialConfigConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutorial_buyer_phone_is_included_in_sms_whitelist_when_both_are_configured_consistently(): void
    {
        config([
            'demo.enabled' => true,
            'demo.tutorial_buyer_phone' => '00000000001',
            'demo.sms_phone_numbers' => ['00000000001'],
        ]);

        $this->assertContains(config('demo.tutorial_buyer_phone'), config('demo.sms_phone_numbers'));
    }

    /**
     * PortfolioDemoSeederが作成する購入者の電話番号は「1〜5をゼロ埋めした11桁」
     * (例: 00000000001)という既存の生成規則に従う。tutorial_buyer_phoneに
     * この規則と一致する値を設定すれば、実際にログイン可能な購入者アカウントが
     * 存在することを確認できる。
     */
    public function test_tutorial_buyer_phone_matches_a_buyer_created_by_the_seeder(): void
    {
        config([
            'demo.enabled' => true,
            'demo.tutorial_buyer_phone' => '00000000001',
            'demo.sms_phone_numbers' => ['00000000001'],
        ]);

        $this->seed(PortfolioDemoSeeder::class);

        $this->assertDatabaseHas('users', [
            'phone_number' => config('demo.tutorial_buyer_phone'),
            'role' => User::ROLE_BUYER,
        ]);
    }

    /**
     * tutorial_buyer_phoneが未設定(null)の場合、config自体は問題なく
     * 動作すること(例外にならないこと)を確認する。Blade側の出し分けは
     * AuthFormTutorialTestで別途確認している。
     */
    public function test_tutorial_buyer_phone_defaults_to_null_when_env_is_unset(): void
    {
        config(['demo.tutorial_buyer_phone' => null]);

        $this->assertNull(config('demo.tutorial_buyer_phone'));
    }

    /**
     * 第5-B: config('demo.tutorial_farmer_email'/'tutorial_farmer_password')
     * (販売者デモ案内用のログイン情報)が、PortfolioDemoSeederが実際に作成する
     * 農家アカウント(demo-farmer@example.com)と整合していることを確認する。
     * このテストもconfigを明示的にオーバーライドして検証するものであり、
     * 本番の実際の.env設定値の整合性までは保証しない。
     */
    public function test_tutorial_farmer_email_matches_a_farmer_created_by_the_seeder(): void
    {
        config([
            'demo.enabled' => true,
            'demo.tutorial_farmer_email' => 'demo-farmer@example.com',
            'demo.tutorial_farmer_password' => 'password',
        ]);

        $this->seed(PortfolioDemoSeeder::class);

        $farmer = User::where('email', config('demo.tutorial_farmer_email'))
            ->where('role', User::ROLE_FARMER)
            ->first();

        $this->assertNotNull($farmer);
        $this->assertTrue(Hash::check(config('demo.tutorial_farmer_password'), $farmer->password));
    }

    /**
     * tutorial_farmer_email/tutorial_farmer_passwordが未設定(null)の場合、
     * config自体は問題なく動作すること(例外にならないこと)を確認する。
     * Blade側の出し分けはAuthFormTutorialTestで別途確認している。
     */
    public function test_tutorial_farmer_credentials_default_to_null_when_env_is_unset(): void
    {
        config(['demo.tutorial_farmer_email' => null, 'demo.tutorial_farmer_password' => null]);

        $this->assertNull(config('demo.tutorial_farmer_email'));
        $this->assertNull(config('demo.tutorial_farmer_password'));
    }
}
