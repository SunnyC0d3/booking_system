<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Create service package system
     */
    public function up(): void
    {
        // Create service packages table
        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->integer('total_price'); // in pence
            $table->integer('discount_amount')->default(0); // in pence
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->integer('individual_price_total')->default(0); // calculated sum of individual service prices
            $table->integer('total_duration_minutes')->default(0); // calculated total duration
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_consultation')->default(false);
            $table->integer('consultation_duration_minutes')->nullable();
            $table->integer('max_advance_booking_days')->nullable();
            $table->integer('min_advance_booking_hours')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
            $table->index('total_price');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('service_packages');
    }
};
