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
        if (! Schema::hasTable('tree_qr_codes')) {
            Schema::create('tree_qr_codes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tree_id')->unique('uq_qr_tree');
                $table->string('qr_code', 255)->unique();
                $table->string('public_token', 100)->unique();
                $table->dateTime('generated_at')->useCurrent();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->foreign('tree_id')->references('id')->on('trees')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tree_qr_codes');
    }
};
