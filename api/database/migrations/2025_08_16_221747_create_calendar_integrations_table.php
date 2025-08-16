<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Create calendar integrations system
     */
    public function up(): void
    {
        // Calendar integrations table
        Schema::create('calendar_integrations', function (Blueprint $table) {
            $table->id();

            // Ownership
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->nullable()->constrained()->onDelete('cascade'); // NULL = all services

            // Provider details
            $table->enum('provider', ['google', 'outlook', 'apple', 'ical']);
            $table->string('calendar_id'); // External calendar ID
            $table->string('calendar_name')->nullable();

            // OAuth tokens (encrypted)
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            // Integration settings
            $table->boolean('is_active')->default(true);
            $table->boolean('sync_bookings')->default(true); // Push bookings to external calendar
            $table->boolean('sync_availability')->default(false); // Pull availability from external calendar
            $table->boolean('auto_block_external_events')->default(false); // Block booking slots when external events exist

            // Sync status
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->integer('sync_error_count')->default(0);

            // Configuration
            $table->json('sync_settings')->nullable(); // Sync frequency, templates, etc.

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'is_active']);
            $table->index(['service_id', 'is_active']);
            $table->index('provider');
            $table->index('token_expires_at');
            $table->index('last_sync_at');

            // Unique constraint: one calendar per service per user
            $table->unique(['user_id', 'service_id', 'calendar_id'], 'unique_calendar_per_service');
        });

        // Calendar events table (for caching external events)
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('calendar_integration_id')->constrained()->onDelete('cascade');
            $table->string('external_event_id'); // ID from external calendar

            // Event details
            $table->string('title');
            $table->text('description')->nullable();
            $table->datetime('starts_at');
            $table->datetime('ends_at');
            $table->boolean('is_all_day')->default(false);

            // Blocking rules
            $table->boolean('blocks_booking')->default(true);
            $table->enum('block_type', ['full', 'partial', 'none'])->default('full');

            // Sync tracking
            $table->timestamp('last_updated_externally')->nullable();
            $table->timestamp('synced_at');

            $table->timestamps();

            // Indexes
            $table->index(['calendar_integration_id', 'starts_at', 'ends_at']);
            $table->index(['starts_at', 'ends_at', 'blocks_booking']);
            $table->index('synced_at');

            // Unique constraint for external events
            $table->unique(['calendar_integration_id', 'external_event_id'], 'unique_external_event');
        });

        // Sync jobs tracking table
        Schema::create('calendar_sync_jobs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('calendar_integration_id')->constrained()->onDelete('cascade');
            $table->enum('job_type', ['sync_bookings', 'sync_availability', 'sync_events']);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed']);

            // Job details
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->integer('events_processed')->default(0);
            $table->text('error_message')->nullable();
            $table->json('job_data')->nullable(); // Additional job parameters

            $table->timestamps();

            // Indexes
            $table->index(['calendar_integration_id', 'status']);
            $table->index(['job_type', 'status']);
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_sync_jobs');
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('calendar_integrations');
    }
};
