<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_add_on_id')->constrained()->onDelete('cascade');

            // Quantity and pricing snapshot
            $table->integer('quantity')->default(1);
            $table->bigInteger('unit_price'); // Price snapshot at time of booking
            $table->bigInteger('total_price'); // unit_price * quantity
            $table->integer('duration_minutes')->default(0); // Duration snapshot

            $table->timestamps();

            // Ensure unique booking + add-on combination
            $table->unique(['booking_id', 'service_add_on_id']);
            $table->index(['booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_add_ons');
    }
};
