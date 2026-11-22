<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive migration: Global soft deletes for all entities with deletion capabilities.
 */
return new class extends Migration
{
    /**
     * Tables that require soft delete support.
     *
     * @var array<int, string>
     */
    protected array $tables = [
        'users',
        'organizations',
        'owners',
        'farmers',
        'villages',
        'blocks',
        'gardens',
        'durian_varieties',
        'trees',
        'product_categories',
        'agricultural_products',
        'fertilization_records',
        'fertilization_items',
        'spraying_records',
        'spraying_items',
        'flowering_records',
        'fruit_development_records',
        'harvest_seasons',
        'harvest_sessions',
        'harvest_records',
        'harvest_details',
        'tree_measurements',
        'activity_photos',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    if (! Schema::hasColumn($table, 'deleted_at')) {
                        $blueprint->softDeletes();
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    if (Schema::hasColumn($table, 'deleted_at')) {
                        $blueprint->dropSoftDeletes();
                    }
                });
            }
        }
    }
};
