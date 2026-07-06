<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_sales', function (Blueprint $table) {
            // fixed(delivery_date_fromをそのまま使う) / auto(注文日時から自動計算する)
            $table->string('delivery_date_type', 10)->default('fixed')->after('status');
            // autoのとき使用。0=当日配達可、1=翌日配達 など
            $table->unsignedTinyInteger('earliest_delivery_days')->nullable()->after('delivery_date_type');
            // autoのとき使用。当日・翌日配達の受付締切時刻
            $table->time('order_deadline_time')->nullable()->after('earliest_delivery_days');
            // 既存仕様(全注文が配達確認の対象)との互換性を優先し、デフォルトはtrue。
            // 確認不要な商品だけ、農家が明示的にfalseにする運用
            $table->boolean('requires_delivery_confirmation')->default(true)->after('order_deadline_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_sales', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_date_type',
                'earliest_delivery_days',
                'order_deadline_time',
                'requires_delivery_confirmation',
            ]);
        });
    }
};
