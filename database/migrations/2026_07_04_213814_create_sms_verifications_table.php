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
        Schema::create('sms_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 15);
            $table->string('code', 6);
            // 発行から5分後に切れる有効期限
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            // 5回失敗したら無効化するための試行回数カウンター
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_verifications');
    }
};
