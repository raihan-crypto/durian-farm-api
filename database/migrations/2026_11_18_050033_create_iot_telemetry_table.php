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
        if (! Schema::hasTable('iot_telemetry')) {
            Schema::create('iot_telemetry', function (Blueprint $table) {
                $table->id();
                $table->uuid('device_id');
                $table->dateTime('recorded_at');
                $table->decimal('temperature_c', 6, 2)->nullable();
                $table->decimal('humidity_percent', 6, 2)->nullable();
                $table->decimal('soil_moisture_percent', 6, 2)->nullable();
                $table->decimal('rainfall_mm', 10, 2)->nullable();
                $table->decimal('battery_percent', 6, 2)->nullable();
                $table->longText('additional_data')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['device_id', 'recorded_at'], 'idx_telemetry_device_time');
                $table->foreign('device_id')->references('id')->on('iot_devices')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iot_telemetry');
    }
};
