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
        if (! Schema::hasTable('flowering_records')) {
            Schema::create('flowering_records', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tree_id');
                $table->date('flowering_date');
                $table->enum('flowering_status', ['mulai', 'berbunga', 'selesai'])->default('mulai');
                $table->unsignedInteger('flower_count')->nullable();
                $table->text('notes')->nullable();
                $table->uuid('recorded_by')->nullable()->index();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['tree_id', 'flowering_date'], 'idx_flowering_tree_date');
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
        Schema::dropIfExists('flowering_records');
    }
};
