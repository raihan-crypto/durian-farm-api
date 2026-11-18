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
        if (! Schema::hasTable('harvest_sessions')) {
            Schema::create('harvest_sessions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('season_id');
                $table->uuid('garden_id');
                $table->dateTime('harvest_date');
                $table->string('session_name', 150)->nullable();
                $table->string('weather_condition', 100)->nullable();
                $table->text('notes')->nullable();
                $table->uuid('recorded_by')->nullable()->index();
                $table->timestamps();

                $table->unique(['id', 'garden_id'], 'uq_harvest_session_garden');
                $table->index(['garden_id', 'harvest_date'], 'idx_harvest_session_date');
                $table->index(['season_id', 'garden_id'], 'season_id');
                $table->foreign(['season_id', 'garden_id'])->references(['id', 'garden_id'])->on('harvest_seasons')->onDelete('cascade');
                $table->foreign('garden_id')->references('id')->on('gardens')->onDelete('cascade');
                $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harvest_sessions');
    }
};
