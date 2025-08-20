<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();

            // Core identification
            $table->string('notification_id', 100)->index(); // Laravel notification ID
            $table->string('batch_id', 100)->nullable()->index(); // For batch processing

            // Relationships
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('booking_id')->nullable()->constrained()->onDelete('set null');
            $table->string('notifiable_type')->nullable(); // Polymorphic relation
            $table->unsignedBigInteger('notifiable_id')->nullable();

            // Notification details
            $table->string('notification_class')->index(); // Full class name
            $table->string('notification_type', 50)->index(); // Type like 'booking_reminder'
            $table->enum('channel', ['mail', 'sms', 'push', 'database', 'slack', 'custom'])->index();

            // Status tracking
            $table->enum('status', [
                'pending', 'queued', 'sending', 'sent', 'delivered', 'read',
                'failed', 'bounced', 'complained', 'unsubscribed', 'blocked'
            ])->default('pending')->index();

            // Timing information
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Attempt tracking
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('next_retry_at')->nullable()->index();

            // Content and metadata
            $table->string('subject')->nullable(); // Email subject or SMS/push title
            $table->text('content')->nullable(); // Notification content (truncated for privacy)
            $table->json('recipients')->nullable(); // Email addresses, phone numbers, device tokens
            $table->json('metadata')->nullable(); // Additional data for debugging

            // Provider information
            $table->string('provider', 50)->nullable(); // twilio, fcm, ses, etc.
            $table->string('provider_message_id')->nullable()->index(); // External ID for tracking
            $table->json('provider_response')->nullable(); // Provider API response

            // Error tracking
            $table->string('error_code', 50)->nullable()->index();
            $table->string('error_type', 100)->nullable(); // General error category
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable(); // Full error information

            // Performance metrics
            $table->unsignedInteger('processing_time_ms')->nullable(); // Time to process
            $table->unsignedInteger('queue_wait_time_ms')->nullable(); // Time spent in queue
            $table->unsignedInteger('delivery_time_ms')->nullable(); // Time to deliver

            // User interaction tracking
            $table->timestamp('opened_at')->nullable(); // Email opened or push clicked
            $table->timestamp('clicked_at')->nullable(); // Link clicked
            $table->string('clicked_url')->nullable(); // Which link was clicked
            $table->string('user_agent')->nullable(); // For email opens
            $table->string('ip_address', 45)->nullable(); // For email opens

            // Campaign and segmentation
            $table->string('campaign_id', 100)->nullable()->index(); // For marketing campaigns
            $table->string('segment', 100)->nullable()->index(); // User segment
            $table->json('tags')->nullable(); // Additional categorization

            // Priority and routing
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal')->index();
            $table->string('queue_name', 100)->nullable()->index();
            $table->string('worker_id', 100)->nullable(); // Which worker processed this

            // Rate limiting and throttling
            $table->boolean('rate_limited')->default(false)->index();
            $table->timestamp('rate_limit_reset_at')->nullable();
            $table->string('rate_limit_key', 100)->nullable();

            // Compliance and privacy
            $table->boolean('gdpr_compliant')->default(true);
            $table->timestamp('data_retention_until')->nullable()->index();
            $table->boolean('anonymized')->default(false)->index();

            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'notification_type']);
            $table->index(['booking_id', 'status']);
            $table->index(['status', 'scheduled_at']);
            $table->index(['channel', 'status']);
            $table->index(['created_at', 'status']);
            $table->index(['provider', 'provider_message_id']);
            $table->index(['error_code', 'failed_at']);
            $table->index(['attempts', 'next_retry_at']);
            $table->index(['data_retention_until']);

            // Composite indexes for common queries
            $table->index(['notification_type', 'channel', 'status']);
            $table->index(['user_id', 'channel', 'sent_at']);
            $table->index(['booking_id', 'notification_type', 'status']);

            // Foreign key indexes
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
