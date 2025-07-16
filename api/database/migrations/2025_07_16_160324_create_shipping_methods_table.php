<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('carrier')->nullable();
            $table->string('service_code')->nullable();
            $table->integer('estimated_days_min')->default(1);
            $table->integer('estimated_days_max')->default(3);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('carrier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
