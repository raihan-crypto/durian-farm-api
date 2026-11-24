<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IotDevice;
use App\Models\IotTelemetry;
use App\Models\MonitoringArea;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IotDeviceController extends Controller
{
    use ApiResponse;

    public function __construct(protected TerritoryScopeService $territoryScope) {}

    public function index(Request $request): JsonResponse
    {
        $query = IotDevice::query()->with('monitoringArea.garden:id,name');

        $accessibleGardenIds = $this->territoryScope->getAccessibleGardenIds($request->user());
        if ($accessibleGardenIds !== null) {
            $query->whereHas('monitoringArea', fn (Builder $q) => $q->whereIn('garden_id', $accessibleGardenIds));
        }

        if ($request->filled('monitoring_area_id')) {
            $query->where('monitoring_area_id', $request->string('monitoring_area_id')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('device_type')) {
            $query->where('device_type', $request->string('device_type')->toString());
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $devices = $query->orderBy('device_code')->paginate($perPage);

        return $this->successResponse([
            'items' => $devices->items(),
            'pagination' => [
                'current_page' => $devices->currentPage(),
                'last_page' => $devices->lastPage(),
                'per_page' => $devices->perPage(),
                'total' => $devices->total(),
            ],
        ], 'Daftar perangkat IoT berhasil dimuat');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'monitoring_area_id' => ['required', 'uuid', 'exists:monitoring_areas,id'],
            'device_code' => ['required', 'string', 'max:100', 'unique:iot_devices,device_code'],
            'device_name' => ['required', 'string', 'max:150'],
            'device_type' => ['required', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'installation_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'maintenance', 'offline'])],
            'description' => ['nullable', 'string'],
        ]);

        $monitoringArea = MonitoringArea::find($validated['monitoring_area_id']);
        if (! $monitoringArea || ! $this->territoryScope->canAccessGarden($request->user(), $monitoringArea->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke area pemantauan ini', 403);
        }

        $validated['status'] = $validated['status'] ?? 'active';
        $device = IotDevice::create($validated);
        $device->load('monitoringArea.garden:id,name');

        return $this->successResponse($device, 'Perangkat IoT berhasil didaftarkan', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $device = IotDevice::query()->with('monitoringArea.garden:id,name')->find($id);

        if (! $device) {
            return $this->errorResponse('Perangkat IoT tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $device->monitoringArea->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke perangkat IoT ini', 403);
        }

        return $this->successResponse($device, 'Detail perangkat IoT berhasil dimuat');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $device = IotDevice::with('monitoringArea')->find($id);

        if (! $device) {
            return $this->errorResponse('Perangkat IoT tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $device->monitoringArea->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke perangkat IoT ini', 403);
        }

        $validated = $request->validate([
            'monitoring_area_id' => ['required', 'uuid', 'exists:monitoring_areas,id'],
            'device_code' => ['required', 'string', 'max:100', Rule::unique('iot_devices', 'device_code')->ignore($device->id)],
            'device_name' => ['required', 'string', 'max:150'],
            'device_type' => ['required', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'installation_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'maintenance', 'offline'])],
            'description' => ['nullable', 'string'],
        ]);

        if ($validated['monitoring_area_id'] !== $device->monitoring_area_id) {
            $targetArea = MonitoringArea::find($validated['monitoring_area_id']);
            if (! $targetArea || ! $this->territoryScope->canAccessGarden($request->user(), $targetArea->garden_id)) {
                return $this->errorResponse('Anda tidak memiliki akses ke area pemantauan tujuan', 403);
            }
        }

        $device->update($validated);
        $device->load('monitoringArea.garden:id,name');

        return $this->successResponse($device, 'Perangkat IoT berhasil diperbarui');
    }

    public function telemetry(Request $request, string $id): JsonResponse
    {
        $device = IotDevice::query()->with('monitoringArea')->find($id);

        if (! $device) {
            return $this->errorResponse('Perangkat IoT tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $device->monitoringArea->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke telemetri perangkat ini', 403);
        }

        $query = IotTelemetry::query()->where('device_id', $device->id);

        if ($request->filled('from_date')) {
            $query->whereDate('recorded_at', '>=', $request->string('from_date')->toString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('recorded_at', '<=', $request->string('to_date')->toString());
        }

        $perPage = min(max((int) $request->input('per_page', 30), 1), 100);
        $telemetry = $query->orderByDesc('recorded_at')->paginate($perPage);

        return $this->successResponse([
            'items' => $telemetry->items(),
            'pagination' => [
                'current_page' => $telemetry->currentPage(),
                'last_page' => $telemetry->lastPage(),
                'per_page' => $telemetry->perPage(),
                'total' => $telemetry->total(),
            ],
        ], 'Riwayat telemetri IoT berhasil dimuat');
    }

    /**
     * Foundation ingest endpoint for future IoT devices / MQTT bridge (SRS 14.3 & 14.4).
     */
    public function ingestTelemetry(Request $request): JsonResponse
    {
        $configuredKey = (string) config('durian.iot.ingest_key', '');
        $providedKey = (string) $request->header('X-IoT-Ingest-Key', '');

        if ($configuredKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return $this->errorResponse('Kunci autentikasi perangkat IoT tidak valid', 401);
        }

        $validated = $request->validate([
            'device_code' => ['required', 'string', 'max:100'],
            'recorded_at' => ['nullable', 'date'],
            'temperature_c' => ['nullable', 'numeric', 'between:-50,100'],
            'humidity_percent' => ['nullable', 'numeric', 'between:0,100'],
            'soil_moisture_percent' => ['nullable', 'numeric', 'between:0,100'],
            'rainfall_mm' => ['nullable', 'numeric', 'min:0'],
            'battery_percent' => ['nullable', 'numeric', 'between:0,100'],
            'additional_data' => ['nullable', 'array'],
        ]);

        $device = IotDevice::query()->where('device_code', $validated['device_code'])->first();

        if (! $device) {
            return $this->errorResponse('Perangkat IoT tidak terdaftar', 404);
        }

        if ($device->status !== 'active') {
            return $this->errorResponse('Perangkat IoT sedang tidak aktif', 422);
        }

        $recordedAt = $validated['recorded_at'] ?? now();

        $telemetry = IotTelemetry::create([
            'device_id' => $device->id,
            'recorded_at' => $recordedAt,
            'temperature_c' => $validated['temperature_c'] ?? null,
            'humidity_percent' => $validated['humidity_percent'] ?? null,
            'soil_moisture_percent' => $validated['soil_moisture_percent'] ?? null,
            'rainfall_mm' => $validated['rainfall_mm'] ?? null,
            'battery_percent' => $validated['battery_percent'] ?? null,
            'additional_data' => $validated['additional_data'] ?? null,
        ]);

        $device->update(['last_seen_at' => now()]);

        return $this->successResponse($telemetry, 'Data telemetri IoT berhasil diterima', 201);
    }
}
