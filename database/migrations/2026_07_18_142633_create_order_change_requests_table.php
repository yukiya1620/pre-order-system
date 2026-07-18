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
        Schema::create('order_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            // quantity_reduction(数量変更相談) / cancellation(キャンセル相談)
            $table->string('request_type', 20);
            // 相談時点のorder_items.quantity(数量変更・キャンセルとも保存する)
            $table->unsignedInteger('quantity_at_request');
            // 数量変更相談のみ使う希望数量。キャンセル相談はNULL
            $table->unsignedInteger('requested_quantity')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            // 未処理はNULL。adjustment_applied(数量減少/キャンセルで解消) / no_change(変更せず終了)
            $table->string('resolution_type', 20)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('resolution_note', 255)->nullable();
            $table->timestamp('resolved_at')->nullable();
            // resolution_type=adjustment_appliedのときのみ設定
            $table->foreignId('resolved_order_adjustment_id')->nullable()->constrained('order_adjustments')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // 注文ごとの未処理判定(order_id + resolved_at IS NULL)を高速化
            $table->index(['order_id', 'resolved_at']);
            // 全体の未処理件数集計(F1のcount API)を高速化
            $table->index('resolved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_change_requests');
    }
};
