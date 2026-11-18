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
        if (! Schema::hasTable('block_farmers')) {
            Schema::create('block_farmers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('block_id');
                $table->uuid('farmer_id')->index();
                $table->date('assignment_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();

                $table->unique(['block_id', 'farmer_id', 'assignment_date'], 'uq_block_farmer');
                $table->foreign('block_id')->references('id')->on('blocks')->onDelete('cascade');
                $table->foreign('farmer_id')->references('id')->on('farmers')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('block_farmers');
    }
};
