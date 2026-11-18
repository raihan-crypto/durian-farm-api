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
        if (! Schema::hasTable('fruit_development_records')) {
            Schema::create('fruit_development_records', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tree_id');
                $table->date('observation_date');
                $table->unsignedInteger('fruit_count')->nullable();
                $table->enum('development_stage', ['pentil', 'pembesaran', 'pematangan', 'siap_panen', 'lainnya'])->nullable();
                $table->text('notes')->nullable();
                $table->uuid('recorded_by')->nullable()->index();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['tree_id', 'observation_date'], 'idx_fruit_tree_date');
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
        Schema::dropIfExists('fruit_development_records');
    }
};
