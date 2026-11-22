<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive migration: performance composite indexes for high-frequency queries
 * identified during the QC performance audit (trees, harvests, activities, photos, users).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('trees')) {
            Schema::table('trees', function (Blueprint $table) {
                $table->index(['garden_id', 'status'], 'idx_trees_garden_status');
                $table->index(['garden_id', 'created_at'], 'idx_trees_garden_created');
            });
        }

        if (Schema::hasTable('harvest_records')) {
            Schema::table('harvest_records', function (Blueprint $table) {
                $table->index(['garden_id', 'created_at'], 'idx_harvest_garden_created');
            });
        }

        if (Schema::hasTable('harvest_seasons')) {
            Schema::table('harvest_seasons', function (Blueprint $table) {
                $table->index(['garden_id', 'status'], 'idx_harvest_season_garden_status');
            });
        }

        if (Schema::hasTable('fertilization_records')) {
            Schema::table('fertilization_records', function (Blueprint $table) {
                $table->index(['activity_date'], 'idx_fertilization_date');
            });
        }

        if (Schema::hasTable('spraying_records')) {
            Schema::table('spraying_records', function (Blueprint $table) {
                $table->index(['activity_date'], 'idx_spraying_date');
            });
        }

        if (Schema::hasTable('flowering_records')) {
            Schema::table('flowering_records', function (Blueprint $table) {
                $table->index(['flowering_date', 'flowering_status'], 'idx_flowering_date_status');
            });
        }

        if (Schema::hasTable('fruit_development_records')) {
            Schema::table('fruit_development_records', function (Blueprint $table) {
                $table->index(['observation_date', 'development_stage'], 'idx_fruit_date_stage');
            });
        }

        if (Schema::hasTable('tree_measurements')) {
            Schema::table('tree_measurements', function (Blueprint $table) {
                $table->index(['measured_at'], 'idx_measurement_measured_at');
            });
        }

        if (Schema::hasTable('activity_photos')) {
            Schema::table('activity_photos', function (Blueprint $table) {
                $table->index(['activity_type', 'created_at'], 'idx_activity_photo_created');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['organization_id', 'is_active'], 'idx_users_org_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('idx_users_org_active');
            });
        }

        if (Schema::hasTable('activity_photos')) {
            Schema::table('activity_photos', function (Blueprint $table) {
                $table->dropIndex('idx_activity_photo_created');
            });
        }

        if (Schema::hasTable('tree_measurements')) {
            Schema::table('tree_measurements', function (Blueprint $table) {
                $table->dropIndex('idx_measurement_measured_at');
            });
        }

        if (Schema::hasTable('fruit_development_records')) {
            Schema::table('fruit_development_records', function (Blueprint $table) {
                $table->dropIndex('idx_fruit_date_stage');
            });
        }

        if (Schema::hasTable('flowering_records')) {
            Schema::table('flowering_records', function (Blueprint $table) {
                $table->dropIndex('idx_flowering_date_status');
            });
        }

        if (Schema::hasTable('spraying_records')) {
            Schema::table('spraying_records', function (Blueprint $table) {
                $table->dropIndex('idx_spraying_date');
            });
        }

        if (Schema::hasTable('fertilization_records')) {
            Schema::table('fertilization_records', function (Blueprint $table) {
                $table->dropIndex('idx_fertilization_date');
            });
        }

        if (Schema::hasTable('harvest_seasons')) {
            Schema::table('harvest_seasons', function (Blueprint $table) {
                $table->dropIndex('idx_harvest_season_garden_status');
            });
        }

        if (Schema::hasTable('harvest_records')) {
            Schema::table('harvest_records', function (Blueprint $table) {
                $table->dropIndex('idx_harvest_garden_created');
            });
        }

        if (Schema::hasTable('trees')) {
            Schema::table('trees', function (Blueprint $table) {
                $table->dropIndex('idx_trees_garden_status');
                $table->dropIndex('idx_trees_garden_created');
            });
        }
    }
};
