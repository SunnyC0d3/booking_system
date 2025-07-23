<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_sku');
            $table->string('supplier_product_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('supplier_price');
            $table->bigInteger('retail_price')->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->decimal('weight', 8, 2)->default(0);
            $table->decimal('length', 8, 2)->default(0);
            $table->decimal('width', 8, 2)->default(0);
            $table->decimal('height', 8, 2)->default(0);
            $table->string('sync_status')->default('pending_sync');
            $table->json('images')->nullable();
            $table->json('attributes')->nullable();
            $table->json('categories')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_mapped')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('sync_errors')->nullable();
            $table->integer('minimum_order_quantity')->default(1);
            $table->integer('processing_time_days')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'supplier_sku']);
            $table->index(['supplier_id', 'is_active']);
            $table->index(['sync_status']);
            $table->index(['is_mapped']);
            $table->index(['last_synced_at']);
            $table->index(['stock_quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }
};
