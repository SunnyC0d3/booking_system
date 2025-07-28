<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('product_type', ['physical', 'digital', 'hybrid'])->default('physical')->after('is_virtual');
            $table->boolean('requires_license')->default(false)->after('product_type');
            $table->boolean('auto_deliver')->default(true)->after('requires_license');
            $table->integer('download_limit')->default(5)->after('auto_deliver');
            $table->integer('download_expiry_days')->default(30)->after('download_limit');
            $table->json('supported_platforms')->nullable()->after('download_expiry_days');
            $table->json('system_requirements')->nullable()->after('supported_platforms');
            $table->string('latest_version', 50)->nullable()->after('system_requirements');
            $table->boolean('version_control_enabled')->default(false)->after('latest_version');

            $table->index(['product_type']);
            $table->index(['requires_license']);
            $table->index(['auto_deliver']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['product_type']);
            $table->dropIndex(['requires_license']);
            $table->dropIndex(['auto_deliver']);

            $table->dropColumn([
                'product_type',
                'requires_license',
                'auto_deliver',
                'download_limit',
                'download_expiry_days',
                'supported_platforms',
                'system_requirements',
                'latest_version',
                'version_control_enabled'
            ]);
        });
    }
};
