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
        if (! Schema::hasTable('garden_owners')) {
            Schema::create('garden_owners', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('garden_id');
                $table->uuid('owner_id')->index();
                $table->decimal('ownership_percentage', 5, 2)->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();

                $table->unique(['garden_id', 'owner_id', 'start_date'], 'uq_garden_owner_period');
                $table->foreign('garden_id')->references('id')->on('gardens')->onDelete('cascade');
                $table->foreign('owner_id')->references('id')->on('owners')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garden_owners');
    }
};
