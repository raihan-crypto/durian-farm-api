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
        if (! Schema::hasTable('weather_records')) {
            Schema::create('weather_records', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('monitoring_area_id')->nullable();
                $table->uuid('garden_id')->nullable();
                $table->dateTime('recorded_at');
                $table->decimal('temperature_c', 6, 2)->nullable();
                $table->decimal('humidity_percent', 6, 2)->nullable();
                $table->decimal('rainfall_mm', 10, 2)->nullable();
                $table->decimal('wind_speed', 8, 2)->nullable();
                $table->string('weather_condition', 100)->nullable();
                $table->string('source', 100)->nullable();
                $table->longText('raw_data')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['monitoring_area_id', 'recorded_at'], 'idx_weather_area_time');
                $table->index(['garden_id', 'recorded_at'], 'idx_weather_garden_time');
                $table->foreign('monitoring_area_id')->references('id')->on('monitoring_areas')->onDelete('set null');
                $table->foreign('garden_id')->references('id')->on('gardens')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_records');
    }
};
