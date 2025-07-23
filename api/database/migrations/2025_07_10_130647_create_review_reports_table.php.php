<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 50);
            $table->text('details')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'resolved', 'dismissed'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['review_id', 'status']);
        });

        DB::statement("ALTER TABLE review_reports ADD CONSTRAINT reason_check CHECK (reason IN ('spam', 'inappropriate_language', 'fake_review', 'off_topic', 'personal_information', 'other'))");
        DB::statement("ALTER TABLE review_reports ADD CONSTRAINT status_check CHECK (status IN ('pending', 'reviewed', 'resolved', 'dismissed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');
    }
};
