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
        if (! Schema::hasTable('tree_measurements')) {
            Schema::create('tree_measurements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tree_id');
                $table->dateTime('measured_at');
                $table->decimal('diameter_cm', 8, 2)->nullable();
                $table->decimal('height_m', 8, 2)->nullable();
                $table->decimal('canopy_width_m', 8, 2)->nullable();
                $table->text('notes')->nullable();
                $table->uuid('recorded_by')->nullable()->index();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['tree_id', 'measured_at'], 'idx_measurement_tree_date');
                $table->foreign('tree_id')->references('id')->on('trees')->onDelete('cascade');
                $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tree_measurements');
    }
};
