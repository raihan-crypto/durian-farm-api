<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TreeMeasurementRequest;
use App\Http\Resources\Api\V1\TreeMeasurementResource;
use App\Models\Tree;
use App\Models\TreeMeasurement;
use App\Services\PhotoStorageService;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TreeMeasurementController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TerritoryScopeService $territoryScope,
        protected PhotoStorageService $photoStorage,
    ) {}

    /**
     * Display chronological measurement history for a tree (SRS 4.3 & 16.3).
     */
    public function indexByTree(Request $request, string $id): JsonResponse
    {
        $tree = Tree::find($id);

        if (! $tree) {
            return $this->errorResponse('Pohon tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke riwayat pengukuran pohon ini', 403);
        }

        $query = TreeMeasurement::query()
            ->where('tree_id', $tree->id)
            ->with(['recorder', 'photos']);

        if ($request->filled('from_date')) {
            $query->whereDate('measured_at', '>=', $request->string('from_date')->toString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('measured_at', '<=', $request->string('to_date')->toString());
        }

        $sortDir = strtolower($request->string('sort_dir', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        $measurements = $query->orderBy('measured_at', $sortDir)->paginate($perPage);

        return $this->successResponse([
            'items' => TreeMeasurementResource::collection($measurements->items()),
            'pagination' => [
                'current_page' => $measurements->currentPage(),
                'last_page' => $measurements->lastPage(),
                'per_page' => $measurements->perPage(),
                'total' => $measurements->total(),
            ],
        ], 'Riwayat pengukuran pohon berhasil dimuat');
    }

    /**
     * Record a new historical measurement for a tree (never overwrites previous measurements).
     */
    public function store(TreeMeasurementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tree = Tree::findOrFail($data['tree_id']);

        if (! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk mencatat pengukuran pada pohon ini', 403);
        }

        $measurement = DB::transaction(function () use ($data, $request) {
            $measurement = TreeMeasurement::create([
                'tree_id' => $data['tree_id'],
                'measured_at' => $data['measured_at'] ?? now(),
                'diameter_cm' => $data['diameter_cm'] ?? null,
                'height_m' => $data['height_m'] ?? null,
                'canopy_width_m' => $data['canopy_width_m'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            foreach ($request->file('photos', []) as $file) {
                $this->photoStorage->storeActivityPhoto(
                    $file,
                    'tree_measurement',
                    $measurement->id,
                    $request->user()->id
                );
            }

            return $measurement->load(['recorder', 'photos']);
        });

        return $this->successResponse(
            new TreeMeasurementResource($measurement),
            'Pengukuran pohon berhasil dicatat',
            201
        );
    }

    /**
     * Display a single measurement record.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $measurement = TreeMeasurement::query()
            ->with(['tree', 'recorder', 'photos'])
            ->find($id);

        if (! $measurement) {
            return $this->errorResponse('Data pengukuran pohon tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $measurement->tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke data pengukuran ini', 403);
        }

        return $this->successResponse(
            new TreeMeasurementResource($measurement),
            'Detail pengukuran pohon berhasil dimuat'
        );
    }
}
