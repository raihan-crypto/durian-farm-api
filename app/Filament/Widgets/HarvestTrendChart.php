<?php

namespace App\Filament\Widgets;

use App\Models\HarvestSession;
use App\Services\TerritoryScopeService;
use Filament\Widgets\ChartWidget;

class HarvestTrendChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Grafik Hasil Panen (10 Sesi Terakhir)';

    protected function getData(): array
    {
        $user = auth()->user();
        $query = HarvestSession::query();

        if ($user) {
            $accessibleGardenIds = app(TerritoryScopeService::class)->getAccessibleGardenIds($user);
            if ($accessibleGardenIds !== null) {
                $query->whereIn('garden_id', $accessibleGardenIds);
            }
        }

        $sessions = $query
            ->withSum('records', 'total_weight_kg')
            ->withSum('records', 'fruit_count')
            ->orderByDesc('harvest_date')
            ->limit(10)
            ->get()
            ->reverse()
            ->values();

        return [
            'datasets' => [
                [
                    'label' => 'Total Berat (kg)',
                    'data' => $sessions->map(fn ($s) => round((float) ($s->records_sum_total_weight_kg ?? 0), 2))->all(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'fill' => true,
                ],
            ],
            'labels' => $sessions->map(fn ($s) => $s->harvest_date?->format('d M Y') ?? '-')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
