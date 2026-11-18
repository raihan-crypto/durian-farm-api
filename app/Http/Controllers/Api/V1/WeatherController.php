<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\FetchGardenWeatherJob;
use App\Models\Garden;
use App\Models\MonitoringArea;
use App\Models\WeatherRecord;
use App\Services\TerritoryScopeService;
use App\Services\WeatherService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class WeatherController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected WeatherService $weatherService,
        protected TerritoryScopeService $territoryScope,
    ) {}

    /**
     * List stored historical weather observations scoped to user's accessible gardens (SRS 13 & 16.3).
     */
    public function index(Request $request): JsonResponse
    {
        $query = WeatherRecord::query()->with(['garden:id,name', 'monitoringArea:id,name']);

        $accessibleGardenIds = $this->territoryScope->getAccessibleGardenIds($request->user());
        if ($accessibleGardenIds !== null) {
            if (empty($accessibleGardenIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('garden_id', $accessibleGardenIds);
            }
        }

        if ($request->filled('garden_id')) {
            $query->where('garden_id', $request->string('garden_id')->toString());
        }

        if ($request->filled('monitoring_area_id')) {
            $query->where('monitoring_area_id', $request->string('monitoring_area_id')->toString());
        }

        if ($request->filled('from_date')) {
            $query->whereDate('recorded_at', '>=', $request->string('from_date')->toString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('recorded_at', '<=', $request->string('to_date')->toString());
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $records = $query->orderByDesc('recorded_at')->paginate($perPage);

        return $this->successResponse([
            'items' => collect($records->items())->map(fn (WeatherRecord $r) => $this->formatRecord($r))->values(),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ], 'Riwayat data cuaca berhasil dimuat');
    }

    /**
     * Return latest cached/stored weather observation for a garden or monitoring area.
     */
    public function latest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'garden_id' => ['required_without:monitoring_area_id', 'nullable', 'uuid', 'exists:gardens,id'],
            'monitoring_area_id' => ['nullable', 'uuid', 'exists:monitoring_areas,id'],
        ]);

        $area = ! empty($validated['monitoring_area_id'])
            ? MonitoringArea::with('garden')->find($validated['monitoring_area_id'])
            : null;
        $garden = $area?->garden ?? Garden::find($validated['garden_id']);

        if (! $garden) {
            return $this->errorResponse('Kebun tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $garden->id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke data cuaca kebun ini', 403);
        }

        $cacheKey = $this->weatherService->latestCacheKey($garden->id, $area?->id);
        $cachedId = Cache::get($cacheKey);

        $record = $cachedId
            ? WeatherRecord::with(['garden:id,name', 'monitoringArea:id,name'])->find($cachedId)
            : null;

        if (! $record) {
            $record = WeatherRecord::query()
                ->with(['garden:id,name', 'monitoringArea:id,name'])
                ->where('garden_id', $garden->id)
                ->when($area, fn ($q) => $q->where('monitoring_area_id', $area->id))
                ->orderByDesc('recorded_at')
                ->first();
        }

        if (! $record) {
            return $this->errorResponse('Belum ada data cuaca tersimpan untuk lokasi ini', 404);
        }

        return $this->successResponse($this->formatRecord($record), 'Data cuaca terbaru berhasil dimuat');
    }

    /**
     * Fetch cached daily weather forecast for a garden or monitoring area (SRS 16.3).
     */
    public function forecast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'garden_id' => ['required_without:monitoring_area_id', 'nullable', 'uuid', 'exists:gardens,id'],
            'monitoring_area_id' => ['nullable', 'uuid', 'exists:monitoring_areas,id'],
            'days' => ['nullable', 'integer', 'min:1', 'max:14'],
        ]);

        $area = ! empty($validated['monitoring_area_id'])
            ? MonitoringArea::with('garden')->find($validated['monitoring_area_id'])
            : null;
        $garden = $area?->garden ?? Garden::find($validated['garden_id']);

        if (! $garden) {
            return $this->errorResponse('Kebun tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $garden->id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke prakiraan cuaca kebun ini', 403);
        }

        $latitude = $area?->latitude ?? $garden->latitude;
        $longitude = $area?->longitude ?? $garden->longitude;

        if ($latitude === null || $longitude === null) {
            return $this->errorResponse('Koordinat latitude dan longitude belum diatur untuk lokasi ini', 422);
        }

        try {
            $forecast = $this->weatherService->getForecast(
                (float) $latitude,
                (float) $longitude,
                (int) ($validated['days'] ?? 5)
            );
        } catch (Throwable $e) {
            return $this->errorResponse('Gagal memuat prakiraan cuaca: '.$e->getMessage(), 502);
        }

        return $this->successResponse(array_merge([
            'garden' => ['id' => $garden->id, 'name' => $garden->name],
            'monitoring_area' => $area ? ['id' => $area->id, 'name' => $area->name] : null,
        ], $forecast), 'Prakiraan cuaca berhasil dimuat');
    }

    /**
     * Trigger immediate fetch for one location or dispatch background job for all gardens (SRS 13.4).
     */
    public function fetch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'garden_id' => ['nullable', 'uuid', 'exists:gardens,id'],
            'monitoring_area_id' => ['nullable', 'uuid', 'exists:monitoring_areas,id'],
            'queue' => ['sometimes', 'boolean'],
        ]);

        if (! empty($validated['queue']) || (empty($validated['garden_id']) && empty($validated['monitoring_area_id']))) {
            FetchGardenWeatherJob::dispatch(
                $validated['garden_id'] ?? null,
                $validated['monitoring_area_id'] ?? null
            );

            return $this->successResponse(null, 'Job sinkronisasi cuaca telah dijadwalkan', 202);
        }

        $area = ! empty($validated['monitoring_area_id'])
            ? MonitoringArea::with('garden')->find($validated['monitoring_area_id'])
            : null;
        $garden = $area?->garden ?? Garden::findOrFail($validated['garden_id']);

        try {
            $record = $this->weatherService->fetchAndStoreForGarden($garden, $area);
            $record->load(['garden:id,name', 'monitoringArea:id,name']);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($this->formatRecord($record), 'Data cuaca berhasil diambil dan disimpan', 201);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatRecord(WeatherRecord $record): array
    {
        return [
            'id' => $record->id,
            'garden_id' => $record->garden_id,
            'monitoring_area_id' => $record->monitoring_area_id,
            'garden' => $record->relationLoaded('garden') && $record->garden ? [
                'id' => $record->garden->id,
                'name' => $record->garden->name,
            ] : null,
            'monitoring_area' => $record->relationLoaded('monitoringArea') && $record->monitoringArea ? [
                'id' => $record->monitoringArea->id,
                'name' => $record->monitoringArea->name,
            ] : null,
            'recorded_at' => $record->recorded_at?->toISOString(),
            'temperature_c' => $record->temperature_c !== null ? (float) $record->temperature_c : null,
            'humidity_percent' => $record->humidity_percent !== null ? (float) $record->humidity_percent : null,
            'rainfall_mm' => $record->rainfall_mm !== null ? (float) $record->rainfall_mm : null,
            'wind_speed' => $record->wind_speed !== null ? (float) $record->wind_speed : null,
            'weather_condition' => $record->weather_condition,
            'source' => $record->source,
            'created_at' => $record->created_at?->toISOString(),
        ];
    }
}
