<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive migration: Adds columns for village officer binding, owner user link,
 * block approval workflow, garden code & status, tree identity number (NIK), and photo path.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Users: village_id for pemerintah_desa binding
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'village_id')) {
                    $table->char('village_id', 36)->nullable()->after('organization_id');
                    $table->foreign('village_id')->references('id')->on('villages')->onDelete('set null');
                }
            });
        }

        // 2. Owners: user_id to link petani user account with owner entity
        if (Schema::hasTable('owners')) {
            Schema::table('owners', function (Blueprint $table) {
                if (! Schema::hasColumn('owners', 'user_id')) {
                    $table->char('user_id', 36)->nullable()->after('id');
                    $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                }
            });
        }

        // 3. Villages: village_code (2-digit village code within district)
        if (Schema::hasTable('villages')) {
            Schema::table('villages', function (Blueprint $table) {
                if (! Schema::hasColumn('villages', 'village_code')) {
                    $table->string('village_code', 10)->nullable()->after('name');
                }
            });
        }

        // 4. Blocks: block_code, approval_status, requested_by
        if (Schema::hasTable('blocks')) {
            Schema::table('blocks', function (Blueprint $table) {
                if (! Schema::hasColumn('blocks', 'block_code')) {
                    $table->string('block_code', 10)->nullable()->after('name');
                }
                if (! Schema::hasColumn('blocks', 'approval_status')) {
                    $table->string('approval_status', 30)->default('approved')->after('description');
                }
                if (! Schema::hasColumn('blocks', 'requested_by')) {
                    $table->char('requested_by', 36)->nullable()->after('approval_status');
                    $table->foreign('requested_by')->references('id')->on('users')->onDelete('set null');
                }
            });
        }

        // 5. Gardens: garden_code, status ('active', 'inactive')
        if (Schema::hasTable('gardens')) {
            Schema::table('gardens', function (Blueprint $table) {
                if (! Schema::hasColumn('gardens', 'garden_code')) {
                    $table->string('garden_code', 20)->nullable()->after('name');
                }
                if (! Schema::hasColumn('gardens', 'status')) {
                    $table->string('status', 30)->default('active')->after('description');
                }
            });
        }

        // 6. Trees: tree_identity_number (NIK Pohon), photo_path
        if (Schema::hasTable('trees')) {
            Schema::table('trees', function (Blueprint $table) {
                if (! Schema::hasColumn('trees', 'tree_identity_number')) {
                    $table->string('tree_identity_number', 50)->nullable()->index('idx_tree_identity_number')->after('tree_code');
                }
                if (! Schema::hasColumn('trees', 'photo_path')) {
                    $table->string('photo_path', 500)->nullable()->after('notes');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('trees')) {
            Schema::table('trees', function (Blueprint $table) {
                if (Schema::hasColumn('trees', 'photo_path')) {
                    $table->dropColumn('photo_path');
                }
                if (Schema::hasColumn('trees', 'tree_identity_number')) {
                    $table->dropIndex('idx_tree_identity_number');
                    $table->dropColumn('tree_identity_number');
                }
            });
        }

        if (Schema::hasTable('gardens')) {
            Schema::table('gardens', function (Blueprint $table) {
                if (Schema::hasColumn('gardens', 'status')) {
                    $table->dropColumn('status');
                }
                if (Schema::hasColumn('gardens', 'garden_code')) {
                    $table->dropColumn('garden_code');
                }
            });
        }

        if (Schema::hasTable('blocks')) {
            Schema::table('blocks', function (Blueprint $table) {
                if (Schema::hasColumn('blocks', 'requested_by')) {
                    $table->dropForeign(['requested_by']);
                    $table->dropColumn('requested_by');
                }
                if (Schema::hasColumn('blocks', 'approval_status')) {
                    $table->dropColumn('approval_status');
                }
                if (Schema::hasColumn('blocks', 'block_code')) {
                    $table->dropColumn('block_code');
                }
            });
        }

        if (Schema::hasTable('villages')) {
            Schema::table('villages', function (Blueprint $table) {
                if (Schema::hasColumn('villages', 'village_code')) {
                    $table->dropColumn('village_code');
                }
            });
        }

        if (Schema::hasTable('owners')) {
            Schema::table('owners', function (Blueprint $table) {
                if (Schema::hasColumn('owners', 'user_id')) {
                    $table->dropForeign(['user_id']);
                    $table->dropColumn('user_id');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'village_id')) {
                    $table->dropForeign(['village_id']);
                    $table->dropColumn('village_id');
                }
            });
        }
    }
};
