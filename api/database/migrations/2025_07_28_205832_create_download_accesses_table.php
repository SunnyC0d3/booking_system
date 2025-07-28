<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_file_id')->nullable()->constrained()->onDelete('set null');
            $table->string('access_token', 100)->unique();
            $table->enum('status', ['active', 'expired', 'revoked', 'suspended'])->default('active');
            $table->integer('download_limit')->default(5);
            $table->integer('downloads_used')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('first_downloaded_at')->nullable();
            $table->timestamp('last_downloaded_at')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'product_id']);
            $table->index(['access_token']);
            $table->index(['status', 'expires_at']);
            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_accesses');
    }
};
