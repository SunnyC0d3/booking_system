<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_location_id')->nullable()->constrained()->onDelete('set null');

            // Booking details
            $table->string('booking_reference')->unique();
            $table->dateTime('scheduled_at');
            $table->dateTime('ends_at');
            $table->integer('duration_minutes');

            // Pricing
            $table->bigInteger('base_price'); // In pennies
            $table->bigInteger('addons_total')->default(0); // In pennies
            $table->bigInteger('total_amount'); // In pennies
            $table->bigInteger('deposit_amount')->nullable(); // In pennies
            $table->bigInteger('remaining_amount')->nullable(); // In pennies

            // Status management
            $table->enum('status', [
                'pending', 'confirmed', 'in_progress', 'completed',
                'cancelled', 'no_show', 'rescheduled'
            ])->default('pending');
            $table->enum('payment_status', [
                'pending', 'deposit_paid', 'fully_paid', 'refunded', 'partially_refunded'
            ])->default('pending');

            // Client information
            $table->string('client_name');
            $table->string('client_email');
            $table->string('client_phone')->nullable();
            $table->text('notes')->nullable();
            $table->text('special_requirements')->nullable();

            // Consultation workflow
            $table->boolean('requires_consultation')->default(false);
            $table->dateTime('consultation_completed_at')->nullable();
            $table->text('consultation_notes')->nullable();

            // Cancellation/Rescheduling
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('rescheduled_from_booking_id')->nullable()->constrained('bookings')->onDelete('set null');

            // Metadata
            $table->json('metadata')->nullable(); // For service-specific data

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['scheduled_at', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['service_id', 'scheduled_at']);
            $table->index(['booking_reference']);
            $table->index(['client_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
