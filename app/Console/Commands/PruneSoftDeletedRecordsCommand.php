<?php

namespace App\Console\Commands;

use App\Models\ActivityPhoto;
use App\Models\AgriculturalProduct;
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
use Illuminate\Support\Carbon;

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
    protected $description = 'Permanently delete soft-deleted records older than the specified retention threshold (default 30 days)';

    /**
     * Models eligible for automatic hard-deletion pruning.
     *
     * @var array<int, class-string>
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

        $this->info("Pruning records soft-deleted on or before {$cutoffDate->toDateTimeString()} ({$days} days)...");

        $totalPruned = 0;

        foreach ($this->models as $modelClass) {
            $query = $modelClass::onlyTrashed()->where('deleted_at', '<=', $cutoffDate);
            $count = $query->count();

            if ($count > 0) {
                // If ActivityPhoto, delete storage files before force-deleting
                if ($modelClass === ActivityPhoto::class) {
                    $photos = $query->get();
                    foreach ($photos as $photo) {
                        $photoStorage->deleteFile($photo->file_path);
                        if ($photo->thumbnail_path) {
                            $photoStorage->deleteFile($photo->thumbnail_path);
                        }
                        $photo->forceDelete();
                    }
                } else {
                    $query->forceDelete();
                }

                $this->line(" - {$modelClass}: {$count} records permanently deleted.");
                $totalPruned += $count;
            }
        }

        $this->info("Pruning completed. Total records permanently deleted: {$totalPruned}");

        return Command::SUCCESS;
    }
}
