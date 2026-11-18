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
        if (! Schema::hasTable('trees')) {
            Schema::create('trees', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('garden_id');
                $table->uuid('variety_id')->nullable()->index('idx_tree_variety');
                $table->string('tree_code', 100);
                $table->string('tree_name', 150)->nullable();
                $table->date('planted_at')->nullable();
                $table->unsignedInteger('planting_age_months')->nullable();
                $table->decimal('initial_diameter_cm', 8, 2)->nullable();
                $table->decimal('initial_height_m', 8, 2)->nullable();
                $table->decimal('initial_canopy_width_m', 8, 2)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->enum('status', ['active', 'inactive', 'dead', 'removed'])->default('active')->index('idx_tree_status');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['garden_id', 'tree_code'], 'uq_tree_code_per_garden');
                $table->unique(['id', 'garden_id'], 'uq_tree_id_garden');
                $table->foreign('garden_id')->references('id')->on('gardens')->onDelete('cascade');
                $table->foreign('variety_id')->references('id')->on('durian_varieties')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trees');
    }
};
