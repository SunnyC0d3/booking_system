<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE FULLTEXT INDEX idx_products_fulltext ON products(name, description)');

        Schema::table('products', function (Blueprint $table) {
            $table->index(['product_status_id', 'price'], 'idx_products_status_price');
            $table->index(['product_category_id', 'product_status_id'], 'idx_products_category_status');
            $table->index(['vendor_id', 'product_status_id'], 'idx_products_vendor_status');
            $table->index(['quantity', 'product_status_id'], 'idx_products_quantity_status');
            $table->index(['created_at', 'product_status_id'], 'idx_products_created_status');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->index(['product_id', 'quantity'], 'idx_variants_product_quantity');
            $table->index(['value', 'product_attribute_id'], 'idx_variants_value_attribute');
            $table->index(['additional_price'], 'idx_variants_additional_price');
        });

        Schema::table('product_tags', function (Blueprint $table) {
            $table->index(['name'], 'idx_product_tags_name');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->index(['parent_id', 'name'], 'idx_categories_parent_name');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->index(['name'], 'idx_vendors_name');
        });

        Schema::table('product_tag', function (Blueprint $table) {
            $table->index(['product_tag_id', 'product_id'], 'idx_product_tag_reverse');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('search_score', 8, 6)->nullable()->after('low_stock_threshold');
            $table->timestamp('last_indexed_at')->nullable()->after('search_score');
            $table->json('search_keywords')->nullable()->after('last_indexed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX idx_products_fulltext ON products');

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_status_price');
            $table->dropIndex('idx_products_category_status');
            $table->dropIndex('idx_products_vendor_status');
            $table->dropIndex('idx_products_quantity_status');
            $table->dropIndex('idx_products_created_status');

            $table->dropColumn(['search_score', 'last_indexed_at', 'search_keywords']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('idx_variants_product_quantity');
            $table->dropIndex('idx_variants_value_attribute');
            $table->dropIndex('idx_variants_additional_price');
        });

        Schema::table('product_tags', function (Blueprint $table) {
            $table->dropIndex('idx_product_tags_name');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropIndex('idx_categories_parent_name');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropIndex('idx_vendors_name');
        });

        Schema::table('product_tag', function (Blueprint $table) {
            $table->dropIndex('idx_product_tag_reverse');
        });
    }
};
