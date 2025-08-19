<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Enhance venue and location management
     */
    public function up(): void
    {
        // Enhanced venue information table (extends service_locations)
        Schema::create('venue_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_location_id')->constrained()->onDelete('cascade');

            // Venue characteristics
            $table->enum('venue_type', ['indoor', 'outdoor', 'mixed', 'studio', 'client_home', 'corporate', 'public_space'])->nullable();
            $table->enum('space_style', ['modern', 'traditional', 'rustic', 'industrial', 'garden', 'ballroom', 'casual', 'formal'])->nullable();

            // Physical specifications
            $table->decimal('ceiling_height_meters', 4, 2)->nullable();
            $table->decimal('floor_area_sqm', 8, 2)->nullable();
            $table->json('room_dimensions')->nullable(); // length, width, height for different areas
            $table->json('color_scheme')->nullable(); // Primary colors of the space

            // Access and logistics
            $table->text('access_instructions')->nullable(); // How to get in, gate codes, etc.
            $table->text('parking_information')->nullable();
            $table->text('loading_instructions')->nullable(); // Where/how to bring equipment
            $table->boolean('lift_access')->default(false);
            $table->boolean('step_free_access')->default(false);
            $table->integer('stairs_count')->nullable();

            // Utilities and power
            $table->json('power_outlets')->nullable(); // Location and type of available power
            $table->boolean('has_adequate_lighting')->default(true);
            $table->text('lighting_notes')->nullable();
            $table->boolean('climate_controlled')->default(false);
            $table->decimal('typical_temperature', 4, 1)->nullable();

            // Setup considerations
            $table->json('setup_restrictions')->nullable(); // Times when setup not allowed
            $table->integer('setup_time_minutes')->nullable(); // How long setup typically takes
            $table->integer('breakdown_time_minutes')->nullable(); // How long breakdown takes
            $table->text('noise_restrictions')->nullable();
            $table->json('prohibited_items')->nullable(); // What can't be brought (candles, confetti, etc.)

            // Venue contacts
            $table->json('venue_contacts')->nullable(); // Manager, security, etc.
            $table->text('special_instructions')->nullable();

            // Photo/event restrictions
            $table->boolean('photography_allowed')->default(true);
            $table->text('photography_restrictions')->nullable();
            $table->boolean('social_media_allowed')->default(true);

            $table->timestamps();

            // Indexes
            $table->index('venue_type');
            $table->index('space_style');
            $table->index(['lift_access', 'step_free_access']);
        });

        // Venue availability and booking windows
        Schema::create('venue_availability_windows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_location_id')->constrained()->onDelete('cascade');

            // Time windows when venue is available
            $table->enum('window_type', ['regular', 'special_event', 'maintenance', 'seasonal']);
            $table->tinyInteger('day_of_week')->nullable(); // 0=Sunday, 1=Monday, etc. (null for specific dates)
            $table->date('specific_date')->nullable(); // For one-off availability changes
            $table->date('date_range_start')->nullable(); // For seasonal/temporary changes
            $table->date('date_range_end')->nullable();

            // Time slots
            $table->time('earliest_access')->nullable(); // When setup can begin
            $table->time('latest_departure')->nullable(); // When breakdown must finish
            $table->time('quiet_hours_start')->nullable(); // When noise restrictions apply
            $table->time('quiet_hours_end')->nullable();

            // Capacity and restrictions during this window
            $table->integer('max_concurrent_events')->default(1);
            $table->json('restrictions')->nullable(); // Special rules for this time period
            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['service_location_id', 'window_type']);
            $table->index(['day_of_week', 'earliest_access']);
            $table->index(['specific_date', 'date_range_start', 'date_range_end']);
        });

        // Venue equipment and amenities
        Schema::create('venue_amenities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_location_id')->constrained()->onDelete('cascade');

            // Amenity details
            $table->enum('amenity_type', ['equipment', 'furniture', 'infrastructure', 'service', 'restriction']);
            $table->string('name');
            $table->text('description')->nullable();

            // Availability
            $table->boolean('included_in_booking')->default(true); // Free to use
            $table->bigInteger('additional_cost')->nullable(); // Extra charge in pence
            $table->integer('quantity_available')->nullable();
            $table->boolean('requires_advance_notice')->default(false);
            $table->integer('notice_hours_required')->nullable();

            // Specifications
            $table->json('specifications')->nullable(); // Size, power requirements, etc.
            $table->text('usage_instructions')->nullable();
            $table->text('restrictions')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['service_location_id', 'amenity_type']);
            $table->index(['is_active', 'sort_order']);
        });

        // Client venue requirements (for consultation and planning)
        Schema::create('client_venue_requirements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_location_id')->nullable()->constrained()->onDelete('set null');

            // Event details that affect venue needs
            $table->integer('expected_guest_count')->nullable();
            $table->json('age_groups')->nullable(); // Children, adults, elderly
            $table->json('accessibility_needs')->nullable(); // Wheelchair, hearing loop, etc.

            // Setup requirements
            $table->text('theme_requirements')->nullable();
            $table->json('color_preferences')->nullable();
            $table->text('special_requests')->nullable();
            $table->json('equipment_needed')->nullable(); // From venue amenities

            // Timing requirements
            $table->datetime('earliest_setup_time')->nullable();
            $table->datetime('event_start_time')->nullable();
            $table->datetime('event_end_time')->nullable();
            $table->datetime('latest_breakdown_time')->nullable();

            // Restrictions and considerations
            $table->json('dietary_restrictions')->nullable(); // If catering involved
            $table->text('noise_considerations')->nullable();
            $table->json('prohibited_elements')->nullable(); // No confetti, no open flames, etc.

            // Vendor coordination
            $table->json('other_vendors')->nullable(); // Caterers, photographers, etc.
            $table->text('coordination_notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('booking_id');
            $table->index(['service_location_id', 'event_start_time']);
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        // Drop venue tables (no booking fields to remove since we didn't add any)
        Schema::dropIfExists('client_venue_requirements');
        Schema::dropIfExists('venue_amenities');
        Schema::dropIfExists('venue_availability_windows');
        Schema::dropIfExists('venue_details');
    }
};
