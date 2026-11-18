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
        if (! Schema::hasTable('garden_farmers')) {
            Schema::create('garden_farmers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('garden_id');
                $table->uuid('farmer_id')->index();
                $table->date('assignment_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();

                $table->unique(['garden_id', 'farmer_id', 'assignment_date'], 'uq_garden_farmer');
                $table->foreign('garden_id')->references('id')->on('gardens')->onDelete('cascade');
                $table->foreign('farmer_id')->references('id')->on('farmers')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garden_farmers');
    }
};
