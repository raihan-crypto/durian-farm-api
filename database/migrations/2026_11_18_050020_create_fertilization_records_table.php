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
        if (! Schema::hasTable('fertilization_records')) {
            Schema::create('fertilization_records', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tree_id');
                $table->dateTime('activity_date');
                $table->enum('method', ['tabur', 'kocor', 'semprot_daun', 'lainnya'])->default('tabur');
                $table->text('dosage_notes')->nullable();
                $table->text('notes')->nullable();
                $table->uuid('recorded_by')->nullable()->index();
                $table->timestamps();

                $table->index(['tree_id', 'activity_date'], 'idx_fertilization_tree_date');
                $table->foreign('tree_id')->references('id')->on('trees')->onDelete('cascade');
                $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fertilization_records');
    }
};
