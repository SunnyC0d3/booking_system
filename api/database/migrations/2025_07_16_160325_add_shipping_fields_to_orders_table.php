<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_method_id')->nullable()->after('status_id')->constrained()->nullOnDelete();
            $table->foreignId('shipping_address_id')->nullable()->after('shipping_method_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('shipping_cost')->default(0)->after('total_amount');
            $table->string('tracking_number')->nullable()->after('shipping_cost');
            $table->timestamp('shipped_at')->nullable()->after('tracking_number');
            $table->string('fulfillment_status')->default('unfulfilled')->after('shipped_at');
            $table->text('shipping_notes')->nullable()->after('fulfillment_status');

            $table->index(['shipping_method_id']);
            $table->index(['shipping_address_id']);
            $table->index(['fulfillment_status']);
            $table->index(['shipped_at']);
            $table->index(['tracking_number']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['shipping_method_id']);
            $table->dropForeign(['shipping_address_id']);
            $table->dropIndex(['shipping_method_id']);
            $table->dropIndex(['shipping_address_id']);
            $table->dropIndex(['fulfillment_status']);
            $table->dropIndex(['shipped_at']);
            $table->dropIndex(['tracking_number']);

            $table->dropColumn([
                'shipping_method_id',
                'shipping_address_id',
                'shipping_cost',
                'tracking_number',
                'shipped_at',
                'fulfillment_status',
                'shipping_notes'
            ]);
        });
    }
};
