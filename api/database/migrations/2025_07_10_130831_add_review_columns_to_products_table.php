<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('average_rating', 3, 2)->default(0)->after('search_keywords'); // 0.00 to 5.00
            $table->integer('total_reviews')->default(0)->after('average_rating');
            $table->json('rating_breakdown')->nullable()->after('total_reviews'); // [1=>0, 2=>0, 3=>1, 4=>2, 5=>3]
            $table->timestamp('last_reviewed_at')->nullable()->after('rating_breakdown');

            $table->index(['average_rating', 'total_reviews']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'average_rating',
                'total_reviews',
                'rating_breakdown',
                'last_reviewed_at'
            ]);
        });
    }
};
