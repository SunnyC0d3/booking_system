<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Create consultation workflow system
     */
    public function up(): void
    {
        // Consultation bookings table (separate from main service bookings)
        Schema::create('consultation_bookings', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->foreignId('main_booking_id')->nullable()->constrained('bookings')->onDelete('set null'); // Links to main service booking

            // Consultation details
            $table->string('consultation_reference')->unique();
            $table->datetime('scheduled_at');
            $table->datetime('ends_at');
            $table->integer('duration_minutes');

            // Status tracking
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->enum('type', ['pre_booking', 'design', 'planning', 'technical', 'follow_up'])->default('pre_booking');
            $table->enum('format', ['phone', 'video', 'in_person', 'site_visit'])->default('phone');

            // Contact information
            $table->string('client_name');
            $table->string('client_email');
            $table->string('client_phone')->nullable();

            // Consultation specifics
            $table->text('consultation_notes')->nullable(); // Client's initial notes/requirements
            $table->text('preparation_instructions')->nullable(); // What client should prepare
            $table->json('consultation_questions')->nullable(); // Pre-consultation questionnaire responses

            // Meeting details (for video/in-person)
            $table->string('meeting_link')->nullable(); // Video call link
            $table->text('meeting_location')->nullable(); // Physical address for in-person
            $table->json('meeting_instructions')->nullable(); // Join instructions, what to bring, etc.

            // Completion tracking
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->text('completion_notes')->nullable(); // Consultant's notes after consultation
            $table->json('outcome_summary')->nullable(); // Key decisions, requirements identified

            // Follow-up tracking
            $table->boolean('requires_follow_up')->default(false);
            $table->datetime('follow_up_scheduled_at')->nullable();
            $table->text('follow_up_notes')->nullable();

            // Pricing and billing
            $table->bigInteger('consultation_fee')->default(0); // in pence, 0 for free consultations
            $table->boolean('fee_waived_if_booking')->default(true); // Waive fee if client books main service
            $table->enum('payment_status', ['free', 'unpaid', 'paid', 'refunded', 'waived'])->default('free');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'scheduled_at']);
            $table->index(['service_id', 'status']);
            $table->index(['status', 'scheduled_at']);
            $table->index('consultation_reference');
            $table->index(['type', 'format']);
            $table->index('requires_follow_up');
        });

        // Consultation templates table (for different service types)
        Schema::create('consultation_templates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_id')->nullable()->constrained()->onDelete('cascade'); // NULL = global template
            $table->string('name');
            $table->text('description')->nullable();

            // Template settings
            $table->integer('default_duration_minutes')->default(30);
            $table->enum('default_type', ['pre_booking', 'design', 'planning', 'technical', 'follow_up'])->default('pre_booking');
            $table->enum('default_format', ['phone', 'video', 'in_person', 'site_visit'])->default('phone');
            $table->bigInteger('default_fee')->default(0); // in pence

            // Template content
            $table->json('pre_consultation_questions')->nullable(); // Questions to ask before consultation
            $table->text('preparation_instructions_template')->nullable();
            $table->text('meeting_instructions_template')->nullable();
            $table->json('consultation_checklist')->nullable(); // Items to cover during consultation
            $table->text('follow_up_template')->nullable(); // Template for follow-up communication

            // Availability settings
            $table->json('available_time_slots')->nullable(); // When consultations can be scheduled
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['service_id', 'is_active']);
            $table->index('sort_order');
        });

        // Consultation notes and documentation
        Schema::create('consultation_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultation_booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade'); // Who made the note

            // Note content
            $table->enum('note_type', ['preparation', 'discussion', 'decision', 'action_item', 'follow_up']);
            $table->string('title')->nullable();
            $table->text('content');
            $table->json('structured_data')->nullable(); // For specific data like measurements, preferences

            // Organization
            $table->boolean('is_private')->default(false); // Internal notes vs client-visible
            $table->boolean('is_action_item')->default(false);
            $table->datetime('action_due_date')->nullable();
            $table->boolean('action_completed')->default(false);

            $table->timestamps();

            // Indexes
            $table->index(['consultation_booking_id', 'note_type']);
            $table->index(['created_by_user_id', 'created_at']);
            $table->index(['is_action_item', 'action_completed']);
        });

        // Consultation outcomes and next steps
        Schema::create('consultation_outcomes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consultation_booking_id')->constrained()->onDelete('cascade');

            // Outcome tracking
            $table->enum('outcome_type', ['booking_confirmed', 'quote_requested', 'follow_up_needed', 'not_interested', 'needs_more_info']);
            $table->text('outcome_description')->nullable();

            // Service requirements identified
            $table->json('service_requirements')->nullable(); // Colors, sizes, themes, etc.
            $table->json('timeline_requirements')->nullable(); // Event date, setup times, etc.
            $table->json('budget_information')->nullable(); // Budget range, payment preferences
            $table->json('logistical_details')->nullable(); // Venue, access, equipment needed

            // Next steps
            $table->json('action_items')->nullable(); // What needs to happen next
            $table->datetime('follow_up_by_date')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');

            // Quote/proposal tracking
            $table->boolean('quote_requested')->default(false);
            $table->datetime('quote_due_date')->nullable();
            $table->bigInteger('estimated_quote_amount')->nullable(); // in pence

            $table->timestamps();

            // Indexes
            $table->index(['consultation_booking_id', 'outcome_type']);
            $table->index('follow_up_by_date');
            $table->index(['quote_requested', 'quote_due_date']);
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        // Drop consultation tables
        Schema::dropIfExists('consultation_outcomes');
        Schema::dropIfExists('consultation_notes');
        Schema::dropIfExists('consultation_templates');
        Schema::dropIfExists('consultation_bookings');
    }
};
