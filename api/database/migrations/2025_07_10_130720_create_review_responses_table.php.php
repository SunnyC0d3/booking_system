<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_approved')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['review_id', 'vendor_id']);
            $table->index(['review_id', 'is_approved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_responses');
    }
};
