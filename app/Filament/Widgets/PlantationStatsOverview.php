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
        $metrics = Cache::remember('filament:plantation:stats_overview', static::$cacheTtlSeconds, function () {
            return [
                'total_trees' => Tree::count(),
                'active_trees' => Tree::where('status', 'active')->count(),
                'total_harvest_weight' => round((float) HarvestRecord::sum('total_weight_kg'), 2),
                'total_harvest_fruits' => (int) HarvestRecord::sum('fruit_count'),
                'sync_issues' => SyncLog::whereIn('sync_status', ['failed', 'conflict'])->count(),
                'villages_count' => Village::count(),
                'blocks_count' => Block::count(),
                'gardens_count' => Garden::count(),
                'active_varieties_count' => DurianVariety::where('is_active', true)->count(),
                'owners_count' => Owner::count(),
                'farmers_count' => Farmer::count(),
            ];
        });

        return [
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

            Stat::make('Status Sinkronisasi Mobile', $metrics['sync_issues'] === 0 ? 'Normal' : "{$metrics['sync_issues']} Perlu Tinjauan")
                ->description($metrics['sync_issues'] === 0 ? 'Tidak ada konflik atau kegagalan' : 'Log failed / conflict di server')
                ->color($metrics['sync_issues'] === 0 ? 'success' : 'danger'),
        ];
    }
}
