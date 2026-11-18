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
        if (! Schema::hasTable('activity_photos')) {
            Schema::create('activity_photos', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('activity_type', 50);
                $table->uuid('activity_id');
                $table->string('file_path', 500);
                $table->string('file_name', 255)->nullable();
                $table->text('caption')->nullable();
                $table->uuid('uploaded_by')->nullable()->index('idx_activity_photo_user');
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['activity_type', 'activity_id'], 'idx_activity_photo');
                $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_photos');
    }
};
