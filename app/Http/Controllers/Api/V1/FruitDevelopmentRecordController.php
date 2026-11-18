<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FruitDevelopmentRecordRequest;
use App\Http\Resources\Api\V1\FruitDevelopmentRecordResource;
use App\Models\FruitDevelopmentRecord;
use App\Models\Tree;
use App\Services\PhotoStorageService;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FruitDevelopmentRecordController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TerritoryScopeService $territoryScope,
        protected PhotoStorageService $photoStorage,
    ) {}

    /**
     * Display historical fruit development records scoped to user's territory access.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FruitDevelopmentRecord::query()
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

        if ($request->filled('development_stage')) {
            $query->where('development_stage', $request->string('development_stage')->toString());
        }

        if ($request->filled('from_date')) {
            $query->whereDate('observation_date', '>=', $request->string('from_date')->toString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('observation_date', '<=', $request->string('to_date')->toString());
        }

        $sortDir = strtolower($request->string('sort_dir', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $records = $query->orderBy('observation_date', $sortDir)->orderBy('created_at', $sortDir)->paginate($perPage);

        return $this->successResponse([
            'items' => FruitDevelopmentRecordResource::collection($records->items()),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ], 'Riwayat perkembangan buah berhasil dimuat');
    }

    /**
     * Store a new historical fruit development observation.
     */
    public function store(FruitDevelopmentRecordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tree = Tree::findOrFail($data['tree_id']);

        if (! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk mencatat perkembangan buah pada pohon ini', 403);
        }

        $record = DB::transaction(function () use ($data, $request) {
            $record = FruitDevelopmentRecord::create([
                'tree_id' => $data['tree_id'],
                'observation_date' => $data['observation_date'] ?? Carbon::today()->toDateString(),
                'fruit_count' => $data['fruit_count'] ?? null,
                'development_stage' => $data['development_stage'],
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            foreach ($request->file('photos', []) as $file) {
                $this->photoStorage->storeActivityPhoto(
                    $file,
                    'fruit_development',
                    $record->id,
                    $request->user()->id
                );
            }

            return $record->load(['tree.garden', 'recorder', 'photos']);
        });

        return $this->successResponse(
            new FruitDevelopmentRecordResource($record),
            'Data perkembangan buah berhasil dicatat',
            201
        );
    }

    /**
     * Display the specified fruit development record.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $record = FruitDevelopmentRecord::query()
            ->with(['tree.garden', 'recorder', 'photos'])
            ->find($id);

        if (! $record) {
            return $this->errorResponse('Data perkembangan buah tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $record->tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke data perkembangan buah ini', 403);
        }

        return $this->successResponse(
            new FruitDevelopmentRecordResource($record),
            'Detail perkembangan buah berhasil dimuat'
        );
    }

    /**
     * Return configurable fruit development stages (SRS 5.4).
     */
    public function stages(): JsonResponse
    {
        $items = collect((array) config('durian.activities.fruit_development_stages', []))
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values();

        return $this->successResponse($items, 'Daftar tahap perkembangan buah berhasil dimuat');
    }
}
