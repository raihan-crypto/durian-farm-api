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
        if (! Schema::hasTable('spraying_items')) {
            Schema::create('spraying_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('spraying_record_id')->index();
                $table->uuid('product_id')->index('idx_spraying_item_product');
                $table->decimal('quantity', 12, 3)->nullable();
                $table->string('unit', 50)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->foreign('spraying_record_id')->references('id')->on('spraying_records')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('agricultural_products')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spraying_items');
    }
};
