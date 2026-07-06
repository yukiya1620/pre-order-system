<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 商品を分類するカテゴリー(設計書の例に合わせた初期データ)
        Category::create(['name' => '季節商品', 'display_order' => 1]);
        Category::create(['name' => '定番商品', 'display_order' => 2]);

        // 動作確認用の農家アカウント(メール+パスワードでログインする)
        User::factory()->farmer()->create([
            'name' => 'やまだ農園',
            'email' => 'farmer@example.com',
            'password' => bcrypt('password'),
        ]);
    }
}
