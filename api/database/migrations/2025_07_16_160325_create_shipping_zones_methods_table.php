<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['shipping_zone_id', 'shipping_method_id'], 'zone_method_unique');
            $table->index(['shipping_zone_id', 'is_active']);
            $table->index(['shipping_method_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones_methods');
    }
};
