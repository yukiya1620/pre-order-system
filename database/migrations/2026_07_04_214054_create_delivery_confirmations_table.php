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
        Schema::create('delivery_confirmations', function (Blueprint $table) {
            $table->id();
            // 1注文に1件までなのでuniqueを付ける
            $table->foreignId('order_id')->unique()->constrained('orders');
            $table->timestamp('notified_at');
            // 配達可能 / 配達日変更 / 数量変更 / キャンセル相談
            $table->string('response', 20)->nullable();
            $table->date('new_delivery_date')->nullable();
            $table->string('response_note', 255)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('buyer_notified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_confirmations');
    }
};
