<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropship_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('supplier_order_id')->nullable();
            $table->string('status')->default('pending');
            $table->bigInteger('total_cost');
            $table->bigInteger('total_retail');
            $table->bigInteger('profit_margin');
            $table->json('shipping_address');
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->timestamp('sent_to_supplier_at')->nullable();
            $table->timestamp('confirmed_by_supplier_at')->nullable();
            $table->timestamp('shipped_by_supplier_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('estimated_delivery')->nullable();
            $table->json('supplier_response')->nullable();
            $table->text('notes')->nullable();
            $table->text('supplier_notes')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();
            $table->boolean('auto_retry_enabled')->default(true);
            $table->json('webhook_data')->nullable();
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['supplier_id']);
            $table->index(['status']);
            $table->index(['sent_to_supplier_at']);
            $table->index(['tracking_number']);
            $table->index(['estimated_delivery']);
            $table->unique(['order_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropship_orders');
    }
};
