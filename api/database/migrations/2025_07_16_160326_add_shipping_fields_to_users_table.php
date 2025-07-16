<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_shipping_address_id')->nullable()->after('last_login_ip')->constrained('shipping_addresses')->nullOnDelete();
            $table->json('shipping_preferences')->nullable()->after('default_shipping_address_id');
            $table->boolean('email_shipping_updates')->default(true)->after('shipping_preferences');

            $table->index(['default_shipping_address_id']);
            $table->index(['email_shipping_updates']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_shipping_address_id']);
            $table->dropIndex(['default_shipping_address_id']);
            $table->dropIndex(['email_shipping_updates']);

            $table->dropColumn([
                'default_shipping_address_id',
                'shipping_preferences',
                'email_shipping_updates'
            ]);
        });
    }
};
