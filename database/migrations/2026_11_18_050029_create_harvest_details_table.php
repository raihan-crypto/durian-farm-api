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
        if (! Schema::hasTable('harvest_details')) {
            Schema::create('harvest_details', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('harvest_record_id')->index('idx_harvest_detail_record');
                $table->string('fruit_grade', 50)->nullable();
                $table->unsignedInteger('fruit_count')->default(0);
                $table->decimal('weight_kg', 12, 3)->default(0.000);
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->foreign('harvest_record_id')->references('id')->on('harvest_records')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harvest_details');
    }
};
