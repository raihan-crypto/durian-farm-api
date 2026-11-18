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
        if (! Schema::hasTable('durian_varieties')) {
            Schema::create('durian_varieties', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 150)->unique('uq_durian_variety_name');
                $table->string('origin', 150)->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('durian_varieties');
    }
};
