<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('file_type');
            $table->string('mime_type');
            $table->bigInteger('file_size');
            $table->string('file_hash');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('download_limit')->nullable();
            $table->integer('download_count')->default(0);
            $table->json('metadata')->nullable();
            $table->string('version', 50)->default('1.0.0');
            $table->text('description')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
            $table->index(['file_hash']);
            $table->index(['is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_files');
    }
};
