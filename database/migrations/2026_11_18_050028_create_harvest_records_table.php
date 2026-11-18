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
        if (! Schema::hasTable('harvest_records')) {
            Schema::create('harvest_records', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('session_id');
                $table->uuid('garden_id');
                $table->uuid('tree_id');
                $table->unsignedInteger('fruit_count')->default(0);
                $table->decimal('total_weight_kg', 12, 3)->default(0.000);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['session_id', 'tree_id'], 'uq_harvest_tree_session');
                $table->index('tree_id', 'idx_harvest_tree');
                $table->index('garden_id', 'idx_harvest_garden');
                $table->index(['session_id', 'garden_id'], 'session_id');
                $table->index(['tree_id', 'garden_id'], 'tree_id');
                $table->foreign(['session_id', 'garden_id'])->references(['id', 'garden_id'])->on('harvest_sessions')->onDelete('cascade');
                $table->foreign(['tree_id', 'garden_id'])->references(['id', 'garden_id'])->on('trees')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harvest_records');
    }
};
