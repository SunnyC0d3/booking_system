<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');

            // Location details
            $table->string('name'); // e.g., "Client's Home", "Studio A", "Main Office"
            $table->text('description')->nullable();
            $table->enum('type', ['business_premises', 'client_location', 'virtual', 'outdoor'])->default('business_premises');

            // Address (for business premises and specific outdoor locations)
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('county')->nullable();
            $table->string('postcode')->nullable();
            $table->string('country')->default('GB');

            // Geographic coordinates
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Capacity and logistics
            $table->integer('max_capacity')->default(1); // Maximum concurrent bookings
            $table->integer('travel_time_minutes')->default(0); // Travel time from base
            $table->bigInteger('additional_charge')->default(0); // In pennies for travel/venue

            // Availability
            $table->boolean('is_active')->default(true);
            $table->json('availability_notes')->nullable(); // Special instructions

            // Virtual location details
            $table->string('virtual_platform')->nullable(); // Zoom, Teams, etc.
            $table->text('virtual_instructions')->nullable();

            // Equipment and facilities
            $table->json('equipment_available')->nullable(); // List of available equipment
            $table->json('facilities')->nullable(); // Parking, wheelchair access, etc.

            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_id', 'is_active']);
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_locations');
    }
};
