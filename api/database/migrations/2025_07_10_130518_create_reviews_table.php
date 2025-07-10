<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete(); // For verified purchases
            $table->tinyInteger('rating')->unsigned(); // 1-5 stars
            $table->string('title')->nullable();
            $table->text('content');
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_featured')->default(false); // Admin can feature helpful reviews
            $table->boolean('is_approved')->default(true); // For moderation
            $table->integer('helpful_votes')->default(0);
            $table->integer('total_votes')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'is_approved', 'created_at']);
            $table->index(['user_id', 'product_id']);
            $table->index(['rating', 'is_approved']);
            $table->index(['is_verified_purchase', 'is_approved']);

            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
