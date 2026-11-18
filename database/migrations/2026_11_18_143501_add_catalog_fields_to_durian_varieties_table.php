<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive migration (SRS 4.2): adds variety code, characteristics, photo and active status
 * to the existing durian_varieties table without touching existing data.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('durian_varieties', function (Blueprint $table) {
            if (! Schema::hasColumn('durian_varieties', 'code')) {
                $table->string('code', 50)->nullable()->unique('uq_durian_variety_code')->after('id');
            }

            if (! Schema::hasColumn('durian_varieties', 'characteristics')) {
                $table->text('characteristics')->nullable()->after('description');
            }

            if (! Schema::hasColumn('durian_varieties', 'photo_path')) {
                $table->string('photo_path', 500)->nullable()->after('characteristics');
            }

            if (! Schema::hasColumn('durian_varieties', 'is_active')) {
                $table->boolean('is_active')->default(true)->index('idx_durian_variety_active')->after('photo_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('durian_varieties', function (Blueprint $table) {
            if (Schema::hasColumn('durian_varieties', 'is_active')) {
                $table->dropIndex('idx_durian_variety_active');
                $table->dropColumn('is_active');
            }

            if (Schema::hasColumn('durian_varieties', 'photo_path')) {
                $table->dropColumn('photo_path');
            }

            if (Schema::hasColumn('durian_varieties', 'characteristics')) {
                $table->dropColumn('characteristics');
            }

            if (Schema::hasColumn('durian_varieties', 'code')) {
                $table->dropUnique('uq_durian_variety_code');
                $table->dropColumn('code');
            }
        });
    }
};
