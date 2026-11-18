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

class PlantationStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalTrees = Tree::count();
        $activeTrees = Tree::where('status', 'active')->count();
        $totalHarvestWeight = round((float) HarvestRecord::sum('total_weight_kg'), 2);
        $totalHarvestFruits = (int) HarvestRecord::sum('fruit_count');
        $syncIssues = SyncLog::whereIn('sync_status', ['failed', 'conflict'])->count();

        return [
            Stat::make('Wilayah Perkebunan', Village::count().' Desa / '.Block::count().' Blok')
                ->description(Garden::count().' Kebun terdaftar')
                ->color('primary'),

            Stat::make('Total Pohon Durian', number_format($totalTrees))
                ->description("{$activeTrees} pohon aktif • ".DurianVariety::where('is_active', true)->count().' varietas aktif')
                ->color('success'),

            Stat::make('Pemilik & Petani', Owner::count().' Pemilik / '.Farmer::count().' Petani')
                ->description('Pengelola & pekerja lapangan terdaftar')
                ->color('info'),

            Stat::make('Total Hasil Panen', number_format($totalHarvestWeight, 2).' kg')
                ->description(number_format($totalHarvestFruits).' buah tercatat')
                ->color('warning'),

            Stat::make('Status Sinkronisasi Mobile', $syncIssues === 0 ? 'Normal' : "{$syncIssues} Perlu Tinjauan")
                ->description($syncIssues === 0 ? 'Tidak ada konflik atau kegagalan' : 'Log failed / conflict di server')
                ->color($syncIssues === 0 ? 'success' : 'danger'),
        ];
    }
}
