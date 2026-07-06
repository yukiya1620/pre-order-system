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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            // 注文受付 / 新規注文 / 配達確認依頼 / 配達予定確認 / 配達予定変更 / 配達完了
            $table->string('type', 30);
            $table->string('title', 100);
            $table->text('body');
            // 関連する注文が無い通知(お知らせなど)もあるためnullable
            $table->foreignId('related_order_id')->nullable()->constrained('orders');
            $table->boolean('is_read')->default(false);
            $table->timestamp('sent_email_at')->nullable();
            $table->timestamp('sent_sms_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
