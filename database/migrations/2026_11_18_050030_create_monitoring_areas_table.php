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
        if (! Schema::hasTable('monitoring_areas')) {
            Schema::create('monitoring_areas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('garden_id')->index('idx_monitoring_garden');
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('area_hectares', 12, 4)->nullable();
                $table->timestamps();

                $table->foreign('garden_id')->references('id')->on('gardens')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitoring_areas');
    }
};
