<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_weight', 8, 2)->default(0);
            $table->decimal('max_weight', 8, 2)->nullable();
            $table->unsignedBigInteger('min_total')->default(0);
            $table->unsignedBigInteger('max_total')->nullable();
            $table->unsignedBigInteger('rate')->default(0);
            $table->unsignedBigInteger('free_threshold')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['shipping_method_id', 'shipping_zone_id']);
            $table->index(['min_total', 'max_total']);
            $table->index(['min_weight', 'max_weight']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
