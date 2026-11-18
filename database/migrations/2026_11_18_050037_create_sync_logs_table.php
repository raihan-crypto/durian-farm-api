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
        if (! Schema::hasTable('sync_logs')) {
            Schema::create('sync_logs', function (Blueprint $table) {
                $table->id();
                $table->uuid('user_id')->nullable()->index('idx_sync_user');
                $table->string('device_identifier', 150)->nullable();
                $table->string('client_transaction_id', 100)->unique('uq_sync_transaction');
                $table->string('entity_type', 100);
                $table->string('entity_id', 100);
                $table->enum('sync_status', ['pending', 'success', 'failed', 'conflict'])->default('pending')->index('idx_sync_status');
                $table->text('error_message')->nullable();
                $table->dateTime('synced_at')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['entity_type', 'entity_id'], 'idx_sync_entity');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
