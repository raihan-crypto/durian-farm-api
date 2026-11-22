<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive migration (QC Stage 4 / Image Optimization):
 * Adds technical file metadata and thumbnail path to activity_photos so photos
 * are not stored as unoptimized BLOBs and client UIs can display fast thumbnails.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('activity_photos')) {
            Schema::table('activity_photos', function (Blueprint $table) {
                if (! Schema::hasColumn('activity_photos', 'thumbnail_path')) {
                    $table->string('thumbnail_path', 500)->nullable()->after('file_path');
                }
                if (! Schema::hasColumn('activity_photos', 'mime_type')) {
                    $table->string('mime_type', 100)->nullable()->after('file_name');
                }
                if (! Schema::hasColumn('activity_photos', 'file_size_bytes')) {
                    $table->unsignedBigInteger('file_size_bytes')->nullable()->after('mime_type');
                }
                if (! Schema::hasColumn('activity_photos', 'width')) {
                    $table->unsignedInteger('width')->nullable()->after('file_size_bytes');
                }
                if (! Schema::hasColumn('activity_photos', 'height')) {
                    $table->unsignedInteger('height')->nullable()->after('width');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('activity_photos')) {
            Schema::table('activity_photos', function (Blueprint $table) {
                $columns = ['thumbnail_path', 'mime_type', 'file_size_bytes', 'width', 'height'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('activity_photos', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
