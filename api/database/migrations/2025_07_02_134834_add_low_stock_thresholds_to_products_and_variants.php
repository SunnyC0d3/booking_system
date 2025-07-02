<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('low_stock_threshold')->default(10)->after('quantity');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->integer('low_stock_threshold')->default(5)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('low_stock_threshold');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('low_stock_threshold');
        });
    }
};
