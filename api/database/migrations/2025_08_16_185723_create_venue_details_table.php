<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_location_id')->constrained('service_locations')->onDelete('cascade');

            // Venue classification
            $table->enum('venue_type', [
                'studio', 'hall', 'garden', 'ballroom', 'restaurant', 'hotel',
                'church', 'outdoor', 'home', 'client_location', 'office',
                'warehouse', 'general'
            ])->default('general');

            // Setup and requirements
            $table->text('setup_requirements')->nullable();
            $table->text('equipment_available')->nullable();
            $table->text('accessibility_info')->nullable();
            $table->text('parking_info')->nullable();
            $table->text('catering_options')->nullable();

            // Capacity and timing
            $table->integer('max_capacity')->default(0);
            $table->integer('setup_time_minutes')->default(30);
            $table->integer('breakdown_time_minutes')->default(20);

            // Pricing
            $table->decimal('additional_fee', 8, 2)->default(0);

            // Features and restrictions
            $table->json('amenities')->nullable(); // Array of amenities
            $table->json('restrictions')->nullable(); // Array of restrictions
            $table->json('contact_info')->nullable(); // Contact details specific to venue
            $table->json('operating_hours')->nullable(); // Venue-specific hours

            // Policies and instructions
            $table->text('cancellation_policy')->nullable();
            $table->text('special_instructions')->nullable();

            // Additional metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('venue_type');
            $table->index('max_capacity');
            $table->index('additional_fee');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_details');
    }
};
