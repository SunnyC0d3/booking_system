<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropship_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dropship_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_product_id')->constrained()->restrictOnDelete();
            $table->string('supplier_sku');
            $table->integer('quantity');
            $table->unsignedBigInteger('supplier_price');
            $table->unsignedBigInteger('retail_price');
            $table->unsignedBigInteger('profit_per_item');
            $table->json('product_details')->nullable();
            $table->json('supplier_item_data')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['dropship_order_id']);
            $table->index(['order_item_id']);
            $table->index(['supplier_product_id']);
            $table->index(['supplier_sku']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropship_order_items');
    }
};
