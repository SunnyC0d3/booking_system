<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'converted_to_pennies')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->boolean('converted_to_pennies')->default(false);
            });
        }

        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('total_amount')->nullable()->change();
            });

            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('price')->nullable()->change();
            });

            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('amount')->nullable()->change();
            });

            Schema::table('order_refunds', function (Blueprint $table) {
                $table->unsignedBigInteger('amount')->nullable()->change();
            });

            Schema::table('products', function (Blueprint $table) {
                $table->unsignedBigInteger('price')->nullable()->change();
            });

            DB::transaction(function () {
                DB::statement('
                    UPDATE orders
                    SET total_amount = CAST(ROUND(total_amount * 100) AS UNSIGNED),
                        converted_to_pennies = 1
                    WHERE total_amount IS NOT NULL
                    AND converted_to_pennies = 0
                ');

                DB::statement('
                    UPDATE order_items
                    SET price = CAST(ROUND(price * 100) AS UNSIGNED)
                    WHERE price IS NOT NULL
                ');

                DB::statement('
                    UPDATE payments
                    SET amount = CAST(ROUND(amount * 100) AS UNSIGNED)
                    WHERE amount IS NOT NULL
                ');

                DB::statement('
                    UPDATE order_refunds
                    SET amount = CAST(ROUND(amount * 100) AS UNSIGNED)
                    WHERE amount IS NOT NULL
                ');

                DB::statement('
                    UPDATE products
                    SET price = CAST(ROUND(price * 100) AS UNSIGNED)
                    WHERE price IS NOT NULL
                ');
            });

            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('converted_to_pennies');
            });

        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function down(): void
    {
        try {
            DB::transaction(function () {
                $sampleOrder = DB::table('orders')->where('total_amount', '>', 10000)->first();

                if ($sampleOrder) {
                    echo "⚠️  Converting penny values back to pounds...\n";

                    DB::statement('
                        UPDATE orders
                        SET total_amount = ROUND(total_amount / 100, 2)
                        WHERE total_amount IS NOT NULL
                    ');

                    DB::statement('
                        UPDATE order_items
                        SET price = ROUND(price / 100, 2)
                        WHERE price IS NOT NULL
                    ');

                    DB::statement('
                        UPDATE payments
                        SET amount = ROUND(amount / 100, 2)
                        WHERE amount IS NOT NULL
                    ');

                    DB::statement('
                        UPDATE order_refunds
                        SET amount = ROUND(amount / 100, 2)
                        WHERE amount IS NOT NULL
                    ');

                    DB::statement('
                        UPDATE products
                        SET price = ROUND(price / 100, 2)
                        WHERE price IS NOT NULL
                    ');
                } else {
                    echo "ℹ️  Data appears to already be in pounds format\n";
                }
            });

            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('total_amount', 12, 2)->nullable()->change();
            });

            Schema::table('order_items', function (Blueprint $table) {
                $table->decimal('price', 10, 2)->nullable()->change();
            });

            Schema::table('payments', function (Blueprint $table) {
                $table->decimal('amount', 12, 2)->nullable()->change();
            });

            Schema::table('order_refunds', function (Blueprint $table) {
                $table->decimal('amount', 10, 2)->nullable()->change();
            });

            Schema::table('products', function (Blueprint $table) {
                $table->decimal('price', 10, 2)->nullable()->change();
            });

            echo "✅ Successfully reverted to decimal pound values\n";

        } catch (\Exception $e) {
            echo "❌ Rollback failed: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
};
