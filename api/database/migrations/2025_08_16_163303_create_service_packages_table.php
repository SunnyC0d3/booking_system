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

        // Create service package items (pivot table)
        Schema::create('service_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_package_id')->constrained('service_packages')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->integer('order')->default(0);
            $table->boolean('is_optional')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['service_package_id', 'service_id'], 'unique_package_service');
            $table->index(['service_package_id', 'order']);
        });

        // Create service bookings table (for individual services within packages)
        Schema::create('service_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->datetime('scheduled_at');
            $table->datetime('ends_at');
            $table->integer('duration_minutes');
            $table->integer('price'); // in pence
            $table->integer('order')->default(0);
            $table->enum('status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'skipped'])->default('pending');
            $table->boolean('is_optional')->default(false);
            $table->text('notes')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'order']);
            $table->index(['service_id', 'scheduled_at']);
            $table->index(['status', 'scheduled_at']);
        });

        // Add service_package_id to existing bookings table
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('service_package_id')->nullable()->after('service_id')->constrained('service_packages')->onDelete('set null');
            $table->index('service_package_id');
        });

        // Create venue details table for enhanced location management
        Schema::create('venue_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_location_id')->constrained('service_locations')->onDelete('cascade');
            $table->string('venue_type')->nullable(); // indoor, outdoor, client_location, studio, etc.
            $table->text('setup_requirements')->nullable();
            $table->text('equipment_available')->nullable();
            $table->text('accessibility_info')->nullable();
            $table->text('parking_info')->nullable();
            $table->text('catering_options')->nullable();
            $table->integer('max_capacity')->nullable();
            $table->integer('setup_time_minutes')->default(0);
            $table->integer('breakdown_time_minutes')->default(0);
            $table->decimal('additional_fee', 8, 2)->default(0); // venue fee in pounds
            $table->json('amenities')->nullable(); // array of amenities
            $table->json('restrictions')->nullable(); // array of restrictions
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('venue_type');
            $table->index('max_capacity');
        });

        // Create service review system (adapted from product reviews)
        Schema::create('service_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('service_id')->nullable()->constrained('services')->onDelete('cascade');
            $table->foreignId('service_package_id')->nullable()->constrained('service_packages')->onDelete('cascade');
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->integer('rating'); // 1-5 stars
            $table->string('title')->nullable();
            $table->text('comment');
            $table->boolean('is_verified')->default(false); // verified purchase
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->integer('helpful_votes')->default(0);
            $table->integer('not_helpful_votes')->default(0);
            $table->datetime('service_date'); // when the service was provided
            $table->json('rating_breakdown')->nullable(); // detailed ratings for different aspects
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_id', 'is_approved', 'created_at']);
            $table->index(['service_package_id', 'is_approved', 'created_at']);
            $table->index(['booking_id']);
            $table->index(['rating', 'is_approved']);
        });

        // Create service review responses
        Schema::create('service_review_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_review_id')->constrained('service_reviews')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // service provider
            $table->text('response');
            $table->boolean('is_approved')->default(true);
            $table->timestamps();

            $table->index(['service_review_id', 'is_approved']);
        });

        // Create booking status history for tracking changes
        Schema::create('booking_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'created_at']);
            $table->index('new_status');
        });

        // Create service availability templates for recurring patterns
        Schema::create('service_availability_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('template_type', ['weekly', 'monthly', 'custom']);
            $table->json('availability_pattern'); // stores the pattern configuration
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['service_id', 'is_active']);
            $table->index('template_type');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        // Drop tables in reverse order
        Schema::dropIfExists('service_availability_templates');
        Schema::dropIfExists('booking_status_history');
        Schema::dropIfExists('service_review_responses');
        Schema::dropIfExists('service_reviews');
        Schema::dropIfExists('venue_details');

        // Remove foreign key from bookings table
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['service_package_id']);
            $table->dropColumn('service_package_id');
        });

        Schema::dropIfExists('service_bookings');
        Schema::dropIfExists('service_package_items');
        Schema::dropIfExists('service_packages');
    }
};
