<?php

namespace App\Console\Commands;

use App\Models\ActivityPhoto;
use App\Models\AgriculturalProduct;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Farmer;
use App\Models\FertilizationItem;
use App\Models\FertilizationRecord;
use App\Models\FloweringRecord;
use App\Models\FruitDevelopmentRecord;
use App\Models\Garden;
use App\Models\HarvestDetail;
use App\Models\HarvestRecord;
use App\Models\HarvestSeason;
use App\Models\HarvestSession;
use App\Models\Organization;
use App\Models\Owner;
use App\Models\ProductCategory;
use App\Models\SprayingItem;
use App\Models\SprayingRecord;
use App\Models\Tree;
use App\Models\TreeMeasurement;
use App\Models\User;
use App\Models\Village;
use App\Services\PhotoStorageService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

class PruneSoftDeletedRecordsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'durian:prune-soft-deleted {--days=30 : Number of days retention for soft deleted records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete soft-deleted records older than the specified retention threshold (default 30 days) with audit logging and referential integrity safety';

    /**
     * Models eligible for automatic hard-deletion pruning.
     * Ordered from child/leaf entities to parent entities to respect foreign keys.
     *
     * @var array<int, class-string<Model>>
     */
    protected array $models = [
        ActivityPhoto::class,
        TreeMeasurement::class,
        HarvestDetail::class,
        HarvestRecord::class,
        HarvestSession::class,
        HarvestSeason::class,
        FruitDevelopmentRecord::class,
        FloweringRecord::class,
        SprayingItem::class,
        SprayingRecord::class,
        FertilizationItem::class,
        FertilizationRecord::class,
        AgriculturalProduct::class,
        ProductCategory::class,
        Tree::class,
        Garden::class,
        Block::class,
        Village::class,
        Farmer::class,
        Owner::class,
        User::class,
        Organization::class,
        DurianVariety::class,
    ];

    /**
     * Execute the console command.
     */
    public function handle(PhotoStorageService $photoStorage): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Pruning records soft-deleted on or before {$cutoffDate->toDateTimeString()} ({$days} days retention)...");

        $totalPruned = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($this->models as $modelClass) {
            $query = $modelClass::onlyTrashed()->where('deleted_at', '<=', $cutoffDate);
            $count = $query->count();

            if ($count === 0) {
                continue;
            }

            $prunedForModel = 0;
            $skippedForModel = 0;

            // Process records in chunks to prevent memory spikes on large tables
            $query->chunkById(100, function ($records) use ($modelClass, $photoStorage, $days, &$prunedForModel, &$skippedForModel, &$totalErrors) {
                foreach ($records as $record) {
                    // 1. Prevent deleting parent entities when active child dependencies remain
                    if ($this->hasActiveDependencies($record)) {
                        $skippedForModel++;

                        continue;
                    }

                    try {
                        // 2. Snapshot record in AuditLog before permanent force-delete
                        AuditLog::create([
                            'user_id' => null, // Pruned by automated scheduled system console
                            'action' => 'pruned',
                            'table_name' => $record->getTable(),
                            'record_id' => (string) $record->getKey(),
                            'old_values' => $record->getAttributes(),
                            'new_values' => [
                                'pruned_by' => 'durian:prune-soft-deleted',
                                'retention_days' => $days,
                                'deleted_at' => (string) $record->deleted_at,
                            ],
                            'ip_address' => '127.0.0.1',
                            'user_agent' => 'Console/Artisan',
                        ]);

                        // 3. Clean up physical media if ActivityPhoto
                        if ($record instanceof ActivityPhoto) {
                            if ($record->file_path) {
                                $photoStorage->deleteFile($record->file_path);
                            }
                            if ($record->thumbnail_path) {
                                $photoStorage->deleteFile($record->thumbnail_path);
                            }
                        }

                        // 4. Force delete individual model instance (fires model events)
                        $record->forceDelete();
                        $prunedForModel++;
                    } catch (Throwable $e) {
                        $totalErrors++;
                        $this->warn("Failed to prune {$modelClass} [{$record->getKey()}]: {$e->getMessage()}");
                    }
                }
            });

            $this->line(" - {$modelClass}: {$prunedForModel} pruned, {$skippedForModel} skipped (has dependencies).");
            $totalPruned += $prunedForModel;
            $totalSkipped += $skippedForModel;
        }

        $this->info("Pruning completed. Total pruned: {$totalPruned}, Total skipped: {$totalSkipped}, Errors: {$totalErrors}");

        return Command::SUCCESS;
    }

    /**
     * Check whether a record has dependent records that prevent safe permanent deletion.
     */
    protected function hasActiveDependencies(Model $record): bool
    {
        if ($record instanceof Village) {
            return $record->blocks()->withTrashed()->exists();
        }

        if ($record instanceof Block) {
            return $record->gardens()->withTrashed()->exists();
        }

        if ($record instanceof Garden) {
            return $record->trees()->withTrashed()->exists() || $record->harvestSessions()->withTrashed()->exists();
        }

        if ($record instanceof Tree) {
            return $record->measurements()->exists() || $record->harvestRecords()->exists() || $record->photos()->exists();
        }

        if ($record instanceof ProductCategory) {
            return $record->products()->withTrashed()->exists();
        }

        if ($record instanceof AgriculturalProduct) {
            return $record->fertilizationItems()->exists() || $record->sprayingItems()->exists();
        }

        if ($record instanceof HarvestSeason) {
            return $record->sessions()->withTrashed()->exists();
        }

        if ($record instanceof HarvestSession) {
            return $record->records()->withTrashed()->exists();
        }

        if ($record instanceof FertilizationRecord) {
            return $record->items()->exists();
        }

        if ($record instanceof SprayingRecord) {
            return $record->items()->exists();
        }

        return false;
    }
}
