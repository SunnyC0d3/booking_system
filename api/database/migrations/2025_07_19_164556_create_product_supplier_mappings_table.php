<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_supplier_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('priority_order')->default(1);
            $table->decimal('markup_percentage', 5, 2)->default(0.00);
            $table->unsignedBigInteger('fixed_markup')->default(0);
            $table->string('markup_type')->default('percentage');
            $table->integer('minimum_stock_threshold')->default(1);
            $table->boolean('auto_update_price')->default(true);
            $table->boolean('auto_update_stock')->default(true);
            $table->boolean('auto_update_description')->default(false);
            $table->json('field_mappings')->nullable();
            $table->timestamp('last_price_update')->nullable();
            $table->timestamp('last_stock_update')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'supplier_id']);
            $table->index(['product_id', 'is_primary']);
            $table->index(['supplier_id', 'is_active']);
            $table->index(['is_primary']);
            $table->index(['priority_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_supplier_mappings');
    }
};
