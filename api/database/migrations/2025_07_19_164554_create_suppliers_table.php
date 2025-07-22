<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('country', 2)->default('GB');
            $table->string('contact_person')->nullable();
            $table->string('status')->default('pending_approval');
            $table->string('integration_type')->default('manual');
            $table->decimal('commission_rate', 5, 2)->default(0.00);
            $table->integer('processing_time_days')->default(1);
            $table->json('shipping_methods')->nullable();
            $table->json('integration_config')->nullable();
            $table->string('api_endpoint')->nullable();
            $table->string('api_key')->nullable();
            $table->string('webhook_url')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('auto_fulfill')->default(false);
            $table->boolean('stock_sync_enabled')->default(true);
            $table->boolean('price_sync_enabled')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->decimal('minimum_order_value', 10, 2)->default(0.00);
            $table->decimal('maximum_order_value', 10, 2)->nullable();
            $table->json('supported_countries')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['integration_type']);
            $table->index(['auto_fulfill']);
            $table->index(['last_sync_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
