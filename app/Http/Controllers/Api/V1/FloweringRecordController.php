<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FloweringRecordRequest;
use App\Http\Resources\Api\V1\FloweringRecordResource;
use App\Models\FloweringRecord;
use App\Models\Tree;
use App\Services\PhotoStorageService;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FloweringRecordController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TerritoryScopeService $territoryScope,
        protected PhotoStorageService $photoStorage,
    ) {}

    /**
     * Display historical flowering records scoped to user's territory access.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FloweringRecord::query()
            ->with(['tree.garden', 'recorder', 'photos']);

        $accessibleGardenIds = $this->territoryScope->getAccessibleGardenIds($request->user());
        if ($accessibleGardenIds !== null) {
            $query->whereHas('tree', fn (Builder $q) => $q->whereIn('garden_id', $accessibleGardenIds));
        }

        if ($request->filled('tree_id')) {
            $query->where('tree_id', $request->string('tree_id')->toString());
        }

        if ($request->filled('garden_id')) {
            $gardenId = $request->string('garden_id')->toString();
            $query->whereHas('tree', fn (Builder $q) => $q->where('garden_id', $gardenId));
        }

        if ($request->filled('block_id')) {
            $blockId = $request->string('block_id')->toString();
            $query->whereHas('tree.garden', fn (Builder $q) => $q->where('block_id', $blockId));
        }

        if ($request->filled('flowering_status')) {
            $query->where('flowering_status', $request->string('flowering_status')->toString());
        }

        if ($request->filled('from_date')) {
            $query->whereDate('flowering_date', '>=', $request->string('from_date')->toString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('flowering_date', '<=', $request->string('to_date')->toString());
        }

        $sortDir = strtolower($request->string('sort_dir', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $records = $query->orderBy('flowering_date', $sortDir)->orderBy('created_at', $sortDir)->paginate($perPage);

        return $this->successResponse([
            'items' => FloweringRecordResource::collection($records->items()),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ], 'Riwayat pembungaan berhasil dimuat');
    }

    /**
     * Store a new historical flowering observation (never overwrites previous records).
     */
    public function store(FloweringRecordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tree = Tree::findOrFail($data['tree_id']);

        if (! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk mencatat pembungaan pada pohon ini', 403);
        }

        $record = DB::transaction(function () use ($data, $request) {
            $record = FloweringRecord::create([
                'tree_id' => $data['tree_id'],
                'flowering_date' => $data['flowering_date'] ?? Carbon::today()->toDateString(),
                'flowering_status' => $data['flowering_status'],
                'flower_count' => $data['flower_count'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            foreach ($request->file('photos', []) as $file) {
                $this->photoStorage->storeActivityPhoto(
                    $file,
                    'flowering',
                    $record->id,
                    $request->user()->id
                );
            }

            return $record->load(['tree.garden', 'recorder', 'photos']);
        });

        return $this->successResponse(
            new FloweringRecordResource($record),
            'Data pembungaan berhasil dicatat',
            201
        );
    }

    /**
     * Display the specified flowering record.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $record = FloweringRecord::query()
            ->with(['tree.garden', 'recorder', 'photos'])
            ->find($id);

        if (! $record) {
            return $this->errorResponse('Data pembungaan tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $record->tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke data pembungaan ini', 403);
        }

        return $this->successResponse(
            new FloweringRecordResource($record),
            'Detail pembungaan berhasil dimuat'
        );
    }

    /**
     * Return available flowering statuses.
     */
    public function statuses(): JsonResponse
    {
        $items = collect((array) config('durian.activities.flowering_statuses', []))
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values();

        return $this->successResponse($items, 'Daftar status pembungaan berhasil dimuat');
    }
}
