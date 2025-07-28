<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('license_key', 100)->unique();
            $table->enum('type', ['single_use', 'multi_use', 'subscription', 'trial'])->default('single_use');
            $table->enum('status', ['active', 'expired', 'revoked', 'suspended'])->default('active');
            $table->integer('activation_limit')->default(1);
            $table->integer('activations_used')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('first_activated_at')->nullable();
            $table->timestamp('last_activated_at')->nullable();
            $table->json('activated_devices')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['license_key']);
            $table->index(['user_id', 'product_id']);
            $table->index(['status', 'expires_at']);
            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_keys');
    }
};
