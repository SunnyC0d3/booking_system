<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');

            // Add-on details
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('price'); // In pennies
            $table->integer('duration_minutes')->default(0); // Additional time required

            // Availability
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(false);
            $table->integer('max_quantity')->default(1); // Maximum quantity per booking

            // Display and ordering
            $table->integer('sort_order')->default(0);
            $table->enum('category', ['equipment', 'service_enhancement', 'location', 'other'])->default('other');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_id', 'is_active']);
            $table->index(['category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_add_ons');
    }
};
