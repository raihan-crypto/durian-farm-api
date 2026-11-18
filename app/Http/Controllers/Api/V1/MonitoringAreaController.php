<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MonitoringArea;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitoringAreaController extends Controller
{
    use ApiResponse;

    public function __construct(protected TerritoryScopeService $territoryScope) {}

    public function index(Request $request): JsonResponse
    {
        $query = MonitoringArea::query()
            ->with('garden:id,name')
            ->withCount('iotDevices');

        $accessibleGardenIds = $this->territoryScope->getAccessibleGardenIds($request->user());
        if ($accessibleGardenIds !== null) {
            $query->whereIn('garden_id', $accessibleGardenIds);
        }

        if ($request->filled('garden_id')) {
            $query->where('garden_id', $request->string('garden_id')->toString());
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $areas = $query->orderBy('name')->paginate($perPage);

        return $this->successResponse([
            'items' => $areas->items(),
            'pagination' => [
                'current_page' => $areas->currentPage(),
                'last_page' => $areas->lastPage(),
                'per_page' => $areas->perPage(),
                'total' => $areas->total(),
            ],
        ], 'Daftar area pemantauan berhasil dimuat');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'garden_id' => ['required', 'uuid', 'exists:gardens,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'area_hectares' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
        ]);

        if (! $this->territoryScope->canAccessGarden($request->user(), $validated['garden_id'])) {
            return $this->errorResponse('Anda tidak memiliki akses ke kebun ini', 403);
        }

        $area = MonitoringArea::create($validated);
        $area->load('garden:id,name')->loadCount('iotDevices');

        return $this->successResponse($area, 'Area pemantauan berhasil dibuat', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $area = MonitoringArea::query()
            ->with(['garden:id,name', 'iotDevices'])
            ->withCount('iotDevices')
            ->find($id);

        if (! $area) {
            return $this->errorResponse('Area pemantauan tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $area->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke area pemantauan ini', 403);
        }

        return $this->successResponse($area, 'Detail area pemantauan berhasil dimuat');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $area = MonitoringArea::find($id);

        if (! $area) {
            return $this->errorResponse('Area pemantauan tidak ditemukan', 404);
        }

        $validated = $request->validate([
            'garden_id' => ['required', 'uuid', 'exists:gardens,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'area_hectares' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
        ]);

        $area->update($validated);
        $area->load('garden:id,name')->loadCount('iotDevices');

        return $this->successResponse($area, 'Area pemantauan berhasil diperbarui');
    }

    public function destroy(string $id): JsonResponse
    {
        $area = MonitoringArea::find($id);

        if (! $area) {
            return $this->errorResponse('Area pemantauan tidak ditemukan', 404);
        }

        if ($area->iotDevices()->exists()) {
            return $this->errorResponse('Area pemantauan tidak dapat dihapus karena masih memiliki perangkat IoT terhubung', 422);
        }

        $area->delete();

        return $this->successResponse(null, 'Area pemantauan berhasil dihapus');
    }
}
