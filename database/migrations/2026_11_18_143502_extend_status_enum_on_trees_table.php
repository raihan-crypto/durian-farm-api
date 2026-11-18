<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive migration (SRS 4.4): extends the trees.status enum with
 * "unproductive", "sick" and "monitoring". Existing values are preserved.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trees', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'unproductive', 'sick', 'monitoring', 'dead', 'removed'])
                ->default('active')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('trees')->whereIn('status', ['sick', 'monitoring'])->update(['status' => 'active']);
        DB::table('trees')->where('status', 'unproductive')->update(['status' => 'inactive']);

        Schema::table('trees', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'dead', 'removed'])
                ->default('active')
                ->change();
        });
    }
};
