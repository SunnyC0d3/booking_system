<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->text('short_description')->nullable();
            $table->bigInteger('base_price'); // In pennies
            $table->integer('duration_minutes')->default(60); // Default 1 hour
            $table->integer('buffer_minutes')->default(15); // Time between bookings
            $table->integer('max_advance_booking_days')->default(30); // How far in advance can bookings be made
            $table->integer('min_advance_booking_hours')->default(24); // Minimum notice required
            $table->boolean('requires_deposit')->default(false);
            $table->decimal('deposit_percentage', 5, 2)->nullable(); // e.g., 50.00 for 50%
            $table->bigInteger('deposit_amount')->nullable(); // Fixed deposit amount in pennies
            $table->enum('status', ['active', 'inactive', 'draft'])->default('active');
            $table->json('metadata')->nullable(); // For additional service-specific data
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
