<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive migration (SRS 10.3): stores per-tree public information settings.
 * NULL means the default public fields from config('durian.public_tree.default_fields') are used.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tree_qr_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('tree_qr_codes', 'public_fields')) {
                $table->json('public_fields')->nullable()->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tree_qr_codes', function (Blueprint $table) {
            if (Schema::hasColumn('tree_qr_codes', 'public_fields')) {
                $table->dropColumn('public_fields');
            }
        });
    }
};
