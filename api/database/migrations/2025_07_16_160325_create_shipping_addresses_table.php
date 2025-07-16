<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type')->default('shipping');
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('county')->nullable();
            $table->string('postcode');
            $table->string('country', 2)->default('GB');
            $table->string('phone')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_validated')->default(false);
            $table->json('validation_data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
            $table->index(['user_id', 'type']);
            $table->index(['postcode', 'country']);
            $table->index('is_validated');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_addresses');
    }
};
