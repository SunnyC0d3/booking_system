<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('media_path');
            $table->string('media_type');
            $table->string('original_name');
            $table->string('mime_type');
            $table->bigInteger('file_size');
            $table->json('metadata')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['review_id', 'sort_order']);
            $table->index(['media_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_media');
    }
};
