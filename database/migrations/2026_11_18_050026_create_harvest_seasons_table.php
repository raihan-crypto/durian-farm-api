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
        if (! Schema::hasTable('harvest_seasons')) {
            Schema::create('harvest_seasons', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('garden_id');
                $table->string('name', 150);
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->enum('status', ['planned', 'active', 'completed'])->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['id', 'garden_id'], 'uq_harvest_season_garden');
                $table->index(['garden_id', 'start_date'], 'idx_season_garden_dates');
                $table->foreign('garden_id')->references('id')->on('gardens')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harvest_seasons');
    }
};
