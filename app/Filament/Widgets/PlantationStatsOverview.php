<?php

namespace App\Filament\Widgets;

use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\HarvestRecord;
use App\Models\Owner;
use App\Models\SyncLog;
use App\Models\Tree;
use App\Models\Village;
use App\Services\TerritoryScopeService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class PlantationStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * Cache duration for overview stats in seconds (2 minutes).
     */
    protected static int $cacheTtlSeconds = 120;

    protected function getStats(): array
    {
        $user = auth()->user();
        $cacheKey = 'filament:plantation:stats_overview:'.($user ? $user->id : 'guest');

        $metrics = Cache::remember($cacheKey, static::$cacheTtlSeconds, function () use ($user) {
            $territoryScope = app(TerritoryScopeService::class);
            $accessibleGardenIds = $user ? $territoryScope->getAccessibleGardenIds($user) : null;
            $accessibleVillageIds = $user ? $territoryScope->getAccessibleVillageIds($user) : null;
            $accessibleBlockIds = $user ? $territoryScope->getAccessibleBlockIds($user) : null;

            $treesQuery = Tree::query();
            $harvestQuery = HarvestRecord::query();
            $villagesQuery = Village::query();
            $blocksQuery = Block::query();
            $gardensQuery = Garden::query();

            if ($accessibleGardenIds !== null) {
                $treesQuery->whereIn('garden_id', $accessibleGardenIds);
                $harvestQuery->whereIn('garden_id', $accessibleGardenIds);
                $gardensQuery->whereIn('id', $accessibleGardenIds);
            }

            if ($accessibleVillageIds !== null) {
                $villagesQuery->whereIn('id', $accessibleVillageIds);
            }

            if ($accessibleBlockIds !== null) {
                $blocksQuery->whereIn('id', $accessibleBlockIds);
            }

            $isAdmin = $user && ($user->hasRole('super_admin') || $user->hasRole('admin'));

            return [
                'total_trees' => (clone $treesQuery)->count(),
                'active_trees' => (clone $treesQuery)->where('status', 'active')->count(),
                'total_harvest_weight' => round((float) (clone $harvestQuery)->sum('total_weight_kg'), 2),
                'total_harvest_fruits' => (int) (clone $harvestQuery)->sum('fruit_count'),
                'sync_issues' => $isAdmin ? SyncLog::whereIn('sync_status', ['failed', 'conflict'])->count() : 0,
                'villages_count' => $villagesQuery->count(),
                'blocks_count' => $blocksQuery->count(),
                'gardens_count' => $gardensQuery->count(),
                'active_varieties_count' => DurianVariety::where('is_active', true)->count(),
                'owners_count' => $isAdmin ? Owner::count() : ($user?->ownerProfile ? 1 : 0),
                'farmers_count' => $isAdmin ? Farmer::count() : ($accessibleGardenIds !== null ? Farmer::whereHas('gardens', fn ($q) => $q->whereIn('gardens.id', $accessibleGardenIds))->count() : 0),
                'show_sync_issues' => $isAdmin,
            ];
        });

        $stats = [
            Stat::make('Wilayah Perkebunan', "{$metrics['villages_count']} Desa / {$metrics['blocks_count']} Blok")
                ->description("{$metrics['gardens_count']} Kebun terdaftar")
                ->color('primary'),

            Stat::make('Total Pohon Durian', number_format($metrics['total_trees']))
                ->description("{$metrics['active_trees']} pohon aktif • {$metrics['active_varieties_count']} varietas aktif")
                ->color('success'),

            Stat::make('Petani & Tenaga Kerja', "{$metrics['owners_count']} Petani / {$metrics['farmers_count']} Tenaga Kerja")
                ->description('Pengelola kebun & pekerja lapangan terdaftar')
                ->color('info'),

            Stat::make('Total Hasil Panen', number_format($metrics['total_harvest_weight'], 2).' kg')
                ->description(number_format($metrics['total_harvest_fruits']).' buah tercatat')
                ->color('warning'),
        ];

        if ($metrics['show_sync_issues']) {
            $stats[] = Stat::make('Status Sinkronisasi Mobile', $metrics['sync_issues'] === 0 ? 'Normal' : "{$metrics['sync_issues']} Perlu Tinjauan")
                ->description($metrics['sync_issues'] === 0 ? 'Tidak ada konflik atau kegagalan' : 'Log failed / conflict di server')
                ->color($metrics['sync_issues'] === 0 ? 'success' : 'danger');
        }

        return $stats;
    }
}
