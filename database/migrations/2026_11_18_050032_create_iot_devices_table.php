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
        if (! Schema::hasTable('iot_devices')) {
            Schema::create('iot_devices', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('monitoring_area_id')->index('idx_iot_monitoring_area');
                $table->string('device_code', 100)->unique();
                $table->string('device_name', 150);
                $table->string('device_type', 100);
                $table->string('manufacturer', 100)->nullable();
                $table->date('installation_date')->nullable();
                $table->dateTime('last_seen_at')->nullable();
                $table->enum('status', ['active', 'inactive', 'maintenance', 'offline'])->default('active');
                $table->text('description')->nullable();
                $table->timestamps();

                $table->foreign('monitoring_area_id')->references('id')->on('monitoring_areas')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iot_devices');
    }
};
