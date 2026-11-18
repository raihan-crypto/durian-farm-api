<?php

namespace App\Jobs;

use App\Models\Garden;
use App\Models\MonitoringArea;
use App\Services\WeatherService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchGardenWeatherJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public ?string $gardenId = null,
        public ?string $monitoringAreaId = null,
    ) {}

    /**
     * Execute the job to fetch and persist weather observations for configured locations (SRS 13.4).
     */
    public function handle(WeatherService $weatherService): void
    {
        if ($this->monitoringAreaId !== null) {
            $area = MonitoringArea::query()->with('garden')->find($this->monitoringAreaId);
            if ($area && $area->garden) {
                $weatherService->fetchAndStoreForGarden($area->garden, $area);
            }

            return;
        }

        $query = Garden::query()->whereNotNull('latitude')->whereNotNull('longitude');
        if ($this->gardenId !== null) {
            $query->where('id', $this->gardenId);
        }

        foreach ($query->cursor() as $garden) {
            try {
                $weatherService->fetchAndStoreForGarden($garden);
            } catch (Throwable $e) {
                Log::warning('Gagal mengambil data cuaca kebun', [
                    'garden_id' => $garden->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
