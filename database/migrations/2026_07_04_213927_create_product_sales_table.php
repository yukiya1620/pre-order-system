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
        Schema::create('product_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedInteger('price');
            // 注文確定ごとに減っていく、現在の残り予約可能数
            $table->unsignedInteger('stock_quantity');
            // 販売開始時点の在庫数(現在予約数 = initial_stock - stock_quantity で算出する)
            $table->unsignedInteger('initial_stock');
            $table->date('sale_start_date');
            $table->date('sale_end_date');
            $table->date('delivery_date_from');
            // NULLなら配達予定日は単日
            $table->date('delivery_date_to')->nullable();
            $table->string('delivery_note', 255)->default('天候等により前後する場合があります');
            $table->boolean('is_reservation_open')->default(true);
            // 準備中 / 販売中 / 売り切れ / 販売終了
            $table->string('status', 15);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sales');
    }
};
