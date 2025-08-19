<?php

use App\Constants\PaymentStatuses;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->noActionOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('status');
            $table->string('transaction_reference')->unique()->nullable();
            $table->dateTime('processed_at');
            $table->longText('response_payload')->nullable();

            $table->enum('payment_type', [
                PaymentStatuses::DEPOSIT_PAID,
                PaymentStatuses::PENDING,
                PaymentStatuses::PARTIAL,
                PaymentStatuses::PAID,
                PaymentStatuses::REFUNDED,
                PaymentStatuses::FAILED,
                PaymentStatuses::CANCELLED
            ])->default('full_payment');
            $table->text('payment_notes')->nullable();
            $table->string('gateway')->nullable();
            $table->string('gateway_payment_id')->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'payment_type']);
            $table->index(['status', 'processed_at']);
            $table->index('gateway_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
