<?php

use App\Constants\PaymentStatuses;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Create comprehensive bookings system
     */
    public function up(): void
    {
        // Main bookings table
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            // Core booking relationships
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_location_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('service_package_id')->nullable()->constrained()->onDelete('set null');

            // Booking identification
            $table->string('booking_reference')->unique();

            // Timing
            $table->datetime('scheduled_at');
            $table->datetime('ends_at');
            $table->integer('duration_minutes');

            // Venue-specific timing
            $table->datetime('venue_access_time')->nullable(); // When to arrive for setup
            $table->datetime('venue_departure_time')->nullable(); // When breakdown should finish

            // Pricing breakdown
            $table->bigInteger('base_price'); // Service base price snapshot (in pence)
            $table->bigInteger('addons_total')->default(0); // Total of all add-ons
            $table->bigInteger('location_surcharge')->default(0); // Location-specific charges
            $table->bigInteger('total_amount'); // Final total amount
            $table->bigInteger('deposit_amount')->nullable(); // Required deposit
            $table->bigInteger('remaining_amount')->nullable(); // Amount still owed

            // Status tracking
            $table->enum('status', [
                'pending',      // Awaiting confirmation
                'confirmed',    // Confirmed and scheduled
                'in_progress',  // Currently happening
                'completed',    // Successfully completed
                'cancelled',    // Cancelled by client or vendor
                'no_show',      // Client didn't show up
                'rescheduled'   // Moved to different time
            ])->default('pending');

            $table->enum('payment_status', [
                PaymentStatuses::DEPOSIT_PAID,
                PaymentStatuses::PENDING,
                PaymentStatuses::PARTIAL,
                PaymentStatuses::PAID,
                PaymentStatuses::REFUNDED,
                PaymentStatuses::FAILED,
                PaymentStatuses::CANCELLED
            ])->default(PaymentStatuses::PENDING);

            // Client information
            $table->string('client_name');
            $table->string('client_email');
            $table->string('client_phone')->nullable();

            // Booking details
            $table->text('notes')->nullable(); // Client's notes/requests
            $table->text('special_requirements')->nullable(); // Special needs, accessibility, etc.
            $table->json('venue_requirements')->nullable(); // Venue-specific requirements
            $table->text('venue_contact_info')->nullable(); // Venue contact details
            $table->json('vendor_coordination')->nullable(); // Other vendors at same venue

            // Basic consultation flag (we'll add foreign key later)
            $table->boolean('requires_consultation')->default(false);

            // Service delivery tracking
            $table->datetime('started_at')->nullable(); // When service actually began
            $table->datetime('completed_at')->nullable(); // When service finished
            $table->text('completion_notes')->nullable(); // Notes about how it went
            $table->integer('actual_duration_minutes')->nullable(); // How long it really took

            // Cancellation and rescheduling
            $table->datetime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('rescheduled_from_booking_id')->nullable()->constrained('bookings')->onDelete('set null');
            $table->datetime('no_show_at')->nullable();

            // Follow-up and feedback
            $table->boolean('follow_up_required')->default(false);
            $table->datetime('follow_up_scheduled_at')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->datetime('feedback_requested_at')->nullable();
            $table->datetime('feedback_received_at')->nullable();

            // Additional data
            $table->json('metadata')->nullable(); // Flexible storage for service-specific data

            $table->timestamps();
            $table->softDeletes();

            // Comprehensive indexing for performance
            $table->index(['user_id', 'status']);
            $table->index(['service_id', 'scheduled_at']);
            $table->index(['service_location_id', 'scheduled_at']);
            $table->index(['status', 'scheduled_at']);
            $table->index(['payment_status', 'created_at']);
            $table->index('booking_reference');
            $table->index('venue_access_time');
            $table->index('venue_departure_time');
            $table->index(['follow_up_required', 'follow_up_scheduled_at']);
            $table->index('requires_consultation');

            // Composite indexes for common queries
            $table->index(['service_id', 'status', 'scheduled_at'], 'service_status_time_idx');
            $table->index(['user_id', 'status', 'scheduled_at'], 'user_status_time_idx');
        });

        // Booking status history for audit trail
        Schema::create('booking_status_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->string('old_status');
            $table->string('new_status');
            $table->text('reason')->nullable();
            $table->json('additional_data')->nullable(); // Extra context about the change

            $table->timestamps();

            $table->index(['booking_id', 'created_at']);
            $table->index('new_status');
        });

        // Booking notifications and reminders
        Schema::create('booking_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->constrained()->onDelete('cascade');

            // Notification details
            $table->enum('notification_type', [
                'confirmation',
                'reminder_24h',
                'reminder_2h',
                'rescheduled',
                'cancelled',
                'follow_up',
                'feedback_request'
            ]);

            $table->enum('delivery_method', ['email', 'sms', 'push', 'webhook']);
            $table->datetime('scheduled_for');
            $table->enum('status', ['pending', 'sent', 'failed', 'cancelled'])->default('pending');

            // Content
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->json('template_data')->nullable(); // Data for template rendering

            // Delivery tracking
            $table->datetime('sent_at')->nullable();
            $table->text('delivery_error')->nullable();
            $table->integer('retry_count')->default(0);

            $table->timestamps();

            $table->index(['booking_id', 'notification_type']);
            $table->index(['scheduled_for', 'status']);
            $table->index(['delivery_method', 'status']);
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_notifications');
        Schema::dropIfExists('booking_status_history');
        Schema::dropIfExists('bookings');
    }
};
