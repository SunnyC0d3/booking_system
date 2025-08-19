<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Create service review system and availability templates
     */
    public function up(): void
    {
        // Create service review system (new functionality)
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
        // Drop only the tables we actually created
        Schema::dropIfExists('service_availability_templates');
        Schema::dropIfExists('service_review_responses');
        Schema::dropIfExists('service_reviews');
    }
};
