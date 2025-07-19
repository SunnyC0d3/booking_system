<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('integration_type');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->json('configuration');
            $table->json('authentication')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_successful_sync')->nullable();
            $table->timestamp('last_failed_sync')->nullable();
            $table->integer('consecutive_failures')->default(0);
            $table->text('last_error')->nullable();
            $table->json('sync_statistics')->nullable();
            $table->integer('sync_frequency_minutes')->default(60);
            $table->boolean('auto_retry_enabled')->default(true);
            $table->integer('max_retry_attempts')->default(3);
            $table->json('webhook_events')->nullable();
            $table->timestamps();

            $table->index(['supplier_id']);
            $table->index(['integration_type']);
            $table->index(['is_active']);
            $table->index(['status']);
            $table->index(['last_successful_sync']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_integrations');
    }
};
