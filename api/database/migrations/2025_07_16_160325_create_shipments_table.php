<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_method_id')->constrained()->restrictOnDelete();
            $table->string('tracking_number')->nullable();
            $table->string('carrier');
            $table->string('service_name')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('shipping_cost')->default(0);
            $table->string('label_url')->nullable();
            $table->string('tracking_url')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('estimated_delivery')->nullable();
            $table->text('notes')->nullable();
            $table->json('carrier_data')->nullable();
            $table->timestamps();

            $table->unique('tracking_number');
            $table->index(['order_id', 'status']);
            $table->index(['carrier', 'status']);
            $table->index('shipped_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
