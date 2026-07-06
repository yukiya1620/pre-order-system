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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description');
            // 外部キー。categoriesテーブルが先に存在している必要がある
            $table->foreignId('category_id')->constrained('categories');
            $table->string('image', 255)->nullable();
            $table->string('unit_label', 20)->default('個');
            // 削除ではなく非表示にするためのフラグ(過去の販売実績を残すため)
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
