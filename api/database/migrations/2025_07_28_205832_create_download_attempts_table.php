<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('download_access_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_file_id')->constrained()->onDelete('cascade');
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->enum('status', ['started', 'completed', 'failed', 'interrupted'])->default('started');
            $table->bigInteger('bytes_downloaded')->default(0);
            $table->bigInteger('total_file_size');
            $table->decimal('download_speed_kbps', 10, 2)->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('headers')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['download_access_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_attempts');
    }
};

