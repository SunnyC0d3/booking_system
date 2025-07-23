<?php

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE products ADD CONSTRAINT price_positive CHECK (price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT quantity_non_negative CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT price_positive CHECK (price > 0)');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT rating_range CHECK (rating >= 1 AND rating <= 5)');
        DB::statement('ALTER TABLE supplier_products ADD CONSTRAINT stock_non_negative CHECK (stock_quantity >= 0)');
        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT quantity_positive CHECK (quantity > 0)');

        // Add indexes for commonly queried boolean fields
        DB::statement('CREATE INDEX IF NOT EXISTS idx_products_is_dropship ON products (is_dropship) WHERE is_dropship = true');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_suppliers_active ON suppliers (id) WHERE status = \'active\'');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_reviews_approved ON reviews (product_id, created_at) WHERE is_approved = true');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS price_positive');
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS quantity_non_negative');
        DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS quantity_positive');
        DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS price_positive');
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS rating_range');
        DB::statement('ALTER TABLE supplier_products DROP CONSTRAINT IF EXISTS stock_non_negative');
        DB::statement('ALTER TABLE cart_items DROP CONSTRAINT IF EXISTS quantity_positive');

        DB::statement('DROP INDEX IF EXISTS idx_products_is_dropship');
        DB::statement('DROP INDEX IF EXISTS idx_suppliers_active');
        DB::statement('DROP INDEX IF EXISTS idx_reviews_approved');
    }
};
