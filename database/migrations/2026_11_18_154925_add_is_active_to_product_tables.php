<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive migration (SRS 7.1 & 7.2): adds is_active flag to product_categories
 * and agricultural_products so administrators can deactivate items without losing history.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('product_categories', 'is_active')) {
                $table->boolean('is_active')->default(true)->index('idx_product_category_active')->after('description');
            }
        });

        Schema::table('agricultural_products', function (Blueprint $table) {
            if (! Schema::hasColumn('agricultural_products', 'is_active')) {
                $table->boolean('is_active')->default(true)->index('idx_agricultural_product_active')->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agricultural_products', function (Blueprint $table) {
            if (Schema::hasColumn('agricultural_products', 'is_active')) {
                $table->dropIndex('idx_agricultural_product_active');
                $table->dropColumn('is_active');
            }
        });

        Schema::table('product_categories', function (Blueprint $table) {
            if (Schema::hasColumn('product_categories', 'is_active')) {
                $table->dropIndex('idx_product_category_active');
                $table->dropColumn('is_active');
            }
        });
    }
};
