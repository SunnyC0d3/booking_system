<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_dropship')->default(false)->after('is_virtual');
            $table->foreignId('primary_supplier_id')->nullable()->after('is_dropship')->constrained('suppliers')->nullOnDelete();
            $table->string('dropship_sync_status')->default('synced')->after('primary_supplier_id');
            $table->timestamp('last_supplier_sync')->nullable()->after('dropship_sync_status');
            $table->unsignedBigInteger('supplier_cost')->nullable()->after('last_supplier_sync');
            $table->decimal('profit_margin_percentage', 5, 2)->nullable()->after('supplier_cost');
            $table->integer('supplier_processing_days')->default(1)->after('profit_margin_percentage');
            $table->boolean('auto_fulfill_dropship')->default(true)->after('supplier_processing_days');
            $table->json('supplier_data')->nullable()->after('auto_fulfill_dropship');

            $table->index(['is_dropship']);
            $table->index(['primary_supplier_id']);
            $table->index(['dropship_sync_status']);
            $table->index(['last_supplier_sync']);
            $table->index(['auto_fulfill_dropship']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['primary_supplier_id']);
            $table->dropIndex(['is_dropship']);
            $table->dropIndex(['primary_supplier_id']);
            $table->dropIndex(['dropship_sync_status']);
            $table->dropIndex(['last_supplier_sync']);
            $table->dropIndex(['auto_fulfill_dropship']);

            $table->dropColumn([
                'is_dropship',
                'primary_supplier_id',
                'dropship_sync_status',
                'last_supplier_sync',
                'supplier_cost',
                'profit_margin_percentage',
                'supplier_processing_days',
                'auto_fulfill_dropship',
                'supplier_data',
            ]);
        });
    }
};
