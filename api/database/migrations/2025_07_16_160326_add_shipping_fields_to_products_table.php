<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('weight', 8, 2)->default(0)->after('last_reviewed_at');
            $table->decimal('length', 8, 2)->default(0)->after('weight');
            $table->decimal('width', 8, 2)->default(0)->after('length');
            $table->decimal('height', 8, 2)->default(0)->after('width');
            $table->string('shipping_class')->default('standard')->after('height');
            $table->boolean('requires_shipping')->default(true)->after('shipping_class');
            $table->boolean('is_virtual')->default(false)->after('requires_shipping');
            $table->integer('handling_time_days')->default(1)->after('is_virtual');
            $table->json('shipping_restrictions')->nullable()->after('handling_time_days');

            $table->index(['requires_shipping']);
            $table->index(['is_virtual']);
            $table->index(['shipping_class']);
            $table->index(['weight']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['requires_shipping']);
            $table->dropIndex(['is_virtual']);
            $table->dropIndex(['shipping_class']);
            $table->dropIndex(['weight']);

            $table->dropColumn([
                'weight',
                'length',
                'width',
                'height',
                'shipping_class',
                'requires_shipping',
                'is_virtual',
                'handling_time_days',
                'shipping_restrictions'
            ]);
        });
    }
};
