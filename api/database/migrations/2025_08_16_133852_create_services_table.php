<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Create services table
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description');
            $table->text('short_description')->nullable();
            $table->string('category', 100)->index();

            // Pricing and duration
            $table->bigInteger('base_price'); // in pence
            $table->integer('duration_minutes');

            // Service availability and status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_bookable')->default(true);

            // Consultation requirements
            $table->boolean('requires_consultation')->default(false);
            $table->integer('consultation_duration_minutes')->nullable();

            // Deposit requirements
            $table->boolean('requires_deposit')->default(false);
            $table->decimal('deposit_percentage', 5, 2)->nullable(); // e.g., 25.00 for 25%
            $table->bigInteger('deposit_amount')->nullable(); // Fixed deposit amount in pence

            // Booking constraints
            $table->integer('min_advance_booking_hours')->nullable(); // Minimum notice required
            $table->integer('max_advance_booking_days')->nullable(); // How far in advance bookings allowed

            // Business rules and policies
            $table->text('cancellation_policy')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('preparation_notes')->nullable(); // Instructions for client preparation

            // Service logistics
            $table->integer('buffer_minutes')->default(0); // Setup/cleanup time
            $table->integer('sort_order')->default(0);

            // Additional data
            $table->json('metadata')->nullable(); // Flexible storage for additional service attributes

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['category', 'is_active']);
            $table->index(['is_active', 'is_bookable']);
            $table->index('base_price');
            $table->index('sort_order');
            $table->index('requires_consultation');
            $table->index('requires_deposit');

            // Full text search indexes
            $table->fullText(['name', 'description', 'short_description'], 'services_search_index');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
