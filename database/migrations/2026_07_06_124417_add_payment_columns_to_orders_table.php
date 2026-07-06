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
        Schema::table('orders', function (Blueprint $table) {
            // cash / card / paypay。初期実装は農家が手元で確認した結果を記録するだけ
            $table->string('payment_method', 10)->default('cash')->after('proxy_note');
            // unpaid / paid / refunded
            $table->string('payment_status', 10)->default('unpaid')->after('payment_method');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_status', 'paid_at']);
        });
    }
};
