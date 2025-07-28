<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_file_id')->constrained()->onDelete('cascade');
            $table->string('version', 50);
            $table->string('title');
            $table->text('description');
            $table->text('changelog')->nullable();
            $table->enum('update_type', ['major', 'minor', 'patch', 'hotfix'])->default('minor');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('is_security_update')->default(false);
            $table->boolean('force_update')->default(false);
            $table->boolean('notify_users')->default(true);
            $table->timestamp('released_at');
            $table->json('compatible_versions')->nullable();
            $table->json('system_requirements')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'version']);
            $table->index(['released_at']);
            $table->index(['update_type', 'priority']);
            $table->unique(['product_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_updates');
    }
};
