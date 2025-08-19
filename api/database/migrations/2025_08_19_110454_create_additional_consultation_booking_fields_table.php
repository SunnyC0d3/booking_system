<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add consultation fields to bookings table
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('consultation_booking_id')->nullable()->after('service_package_id')->constrained('consultation_bookings')->onDelete('set null');
            $table->boolean('consultation_completed')->default(false)->after('requires_consultation');
            $table->datetime('consultation_completed_at')->nullable()->after('consultation_completed');
            $table->text('consultation_summary')->nullable()->after('consultation_completed_at');

            // Indexes
            $table->index('consultation_booking_id');
            $table->index(['requires_consultation', 'consultation_completed']);
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['consultation_booking_id']);
            $table->dropColumn([
                'consultation_booking_id',
                'consultation_completed',
                'consultation_completed_at',
                'consultation_summary'
            ]);
        });
    }
};
