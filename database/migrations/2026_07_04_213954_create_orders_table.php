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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // 電話・対面での照会用に人が読める注文番号(例: 20260704-0012)
            $table->string('order_number', 20)->unique();
            $table->foreignId('user_id')->constrained('users');
            // 受付済 / 配達確認済 / 配達日変更 / 配達完了 / キャンセル
            $table->string('status', 20);
            $table->unsignedInteger('total_amount');
            // 注文時点の住所をコピーして保存(後から住所変更しても過去注文に影響しないため)
            $table->string('delivery_address', 255);
            $table->date('delivery_date');
            $table->string('delivery_time_slot', 20)->nullable();
            $table->boolean('is_proxy_order')->default(false);
            $table->string('proxy_note', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
