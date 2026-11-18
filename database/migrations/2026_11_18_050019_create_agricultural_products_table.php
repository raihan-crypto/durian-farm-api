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
        if (! Schema::hasTable('agricultural_products')) {
            Schema::create('agricultural_products', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('category_id')->index('idx_product_category');
                $table->string('name', 150);
                $table->string('brand', 120)->nullable();
                $table->string('active_ingredient', 150)->nullable();
                $table->string('unit', 50)->nullable();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->foreign('category_id')->references('id')->on('product_categories')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agricultural_products');
    }
};
