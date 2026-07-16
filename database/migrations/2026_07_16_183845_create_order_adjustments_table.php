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
        Schema::create('order_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('order_item_id')->constrained('order_items');
            // quantity_reduced(数量減少) / cancelled(全キャンセル)
            $table->string('change_type', 20);
            $table->string('previous_status', 20);
            $table->string('new_status', 20);
            $table->unsignedInteger('previous_quantity');
            $table->unsignedInteger('new_quantity');
            $table->unsignedInteger('stock_restored');
            $table->timestamp('confirmed_with_buyer_at');
            $table->string('note', 255)->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_adjustments');
    }
};
