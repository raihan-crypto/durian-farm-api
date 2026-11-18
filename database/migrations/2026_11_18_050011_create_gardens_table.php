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
        if (! Schema::hasTable('gardens')) {
            Schema::create('gardens', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('block_id')->index();
                $table->string('name', 150);
                $table->decimal('area_hectares', 12, 4)->nullable();
                $table->text('address')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->foreign('block_id')->references('id')->on('blocks')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gardens');
    }
};
