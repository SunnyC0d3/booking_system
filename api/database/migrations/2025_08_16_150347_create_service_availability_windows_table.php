<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_availability_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_location_id')->nullable()->constrained()->onDelete('cascade');

            // Window type and pattern
            $table->enum('type', ['regular', 'exception', 'special_hours', 'blocked'])->default('regular');
            $table->enum('pattern', ['weekly', 'daily', 'date_range', 'specific_date'])->default('weekly');

            // Day of week (for weekly patterns) - 0=Sunday, 1=Monday, etc.
            $table->tinyInteger('day_of_week')->nullable(); // 0-6, null for non-weekly patterns

            // Specific dates (for date_range and specific_date patterns)
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Time slots
            $table->time('start_time');
            $table->time('end_time');

            // Capacity and limits
            $table->integer('max_bookings')->default(1); // Maximum concurrent bookings in this window
            $table->integer('slot_duration_minutes')->nullable(); // Override service duration
            $table->integer('break_duration_minutes')->default(0); // Break between slots

            // Booking rules
            $table->integer('min_advance_booking_hours')->nullable(); // Override service minimum
            $table->integer('max_advance_booking_days')->nullable(); // Override service maximum

            // Availability status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_bookable')->default(true); // False for blocked time

            // Special pricing
            $table->bigInteger('price_modifier')->nullable(); // In pennies, can be positive or negative
            $table->enum('price_modifier_type', ['fixed', 'percentage'])->default('fixed');

            // Metadata
            $table->string('title')->nullable(); // e.g., "Weekend Hours", "Holiday Special"
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional configuration

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['service_id', 'is_active', 'is_bookable']);
            $table->index(['service_location_id', 'is_active']);
            $table->index(['day_of_week', 'start_time', 'end_time']);
            $table->index(['start_date', 'end_date']);
            $table->index(['type', 'pattern']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_availability_windows');
    }
};
