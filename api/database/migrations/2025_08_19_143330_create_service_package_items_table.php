<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Create service package items (pivot table)
     */
    public function up(): void
    {
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

        // Add service_package_id to existing bookings table if it doesn't exist
        if (!Schema::hasColumn('bookings', 'service_package_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->foreignId('service_package_id')->nullable()->after('service_id')->constrained('service_packages')->onDelete('set null');
                $table->index('service_package_id');
            });
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        // Remove foreign key from bookings table if it exists
        if (Schema::hasColumn('bookings', 'service_package_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropForeign(['service_package_id']);
                $table->dropColumn('service_package_id');
            });
        }

        Schema::dropIfExists('service_bookings');
        Schema::dropIfExists('service_package_items');
    }
};
