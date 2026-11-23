<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('gardens')) {
            Schema::table('gardens', function (Blueprint $table) {
                if (! Schema::hasColumn('gardens', 'ownership_type')) {
                    $table->string('ownership_type', 30)->default('individual')->after('name');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('gardens')) {
            Schema::table('gardens', function (Blueprint $table) {
                if (Schema::hasColumn('gardens', 'ownership_type')) {
                    $table->dropColumn('ownership_type');
                }
            });
        }
    }
};
