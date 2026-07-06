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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('product_sale_id')->constrained('product_sales');
            // 注文時点の商品名を保存。後から商品名を変えても注文履歴が変わらない
            $table->string('product_name', 100);
            // 単価も注文時点の値をコピー保存(スナップショット)
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('quantity');
            // 小計 = unit_price × quantity
            $table->unsignedInteger('subtotal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
