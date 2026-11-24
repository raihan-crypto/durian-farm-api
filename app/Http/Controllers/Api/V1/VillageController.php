<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VillageRequest;
use App\Http\Resources\Api\V1\VillageResource;
use App\Models\Tree;
use App\Models\Village;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VillageController extends Controller
{
    use ApiResponse;

    public function __construct(protected TerritoryScopeService $territoryScope) {}

    /**
     * Display a listing of villages with block, garden, and tree counts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->baseVillageQuery();

        $accessibleVillageIds = $this->territoryScope->getAccessibleVillageIds($request->user());
        if ($accessibleVillageIds !== null) {
            $query->whereIn('villages.id', $accessibleVillageIds);
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $q) use ($search) {
                $q->where('villages.name', 'like', "%{$search}%")
                    ->orWhere('villages.district', 'like', "%{$search}%")
                    ->orWhere('villages.regency', 'like', "%{$search}%")
                    ->orWhere('villages.province', 'like', "%{$search}%");
            });
        }

        if ($request->filled('district')) {
            $query->where('villages.district', 'like', '%'.$request->string('district')->toString().'%');
        }

        if ($request->filled('regency')) {
            $query->where('villages.regency', 'like', '%'.$request->string('regency')->toString().'%');
        }

        if ($request->filled('province')) {
            $query->where('villages.province', 'like', '%'.$request->string('province')->toString().'%');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $villages = $query->orderBy('villages.name')->paginate($perPage);

        return $this->successResponse([
            'items' => VillageResource::collection($villages->items()),
            'pagination' => [
                'current_page' => $villages->currentPage(),
                'last_page' => $villages->lastPage(),
                'per_page' => $villages->perPage(),
                'total' => $villages->total(),
            ],
        ], 'Daftar desa berhasil dimuat');
    }

    /**
     * Store a newly created village.
     */
    public function store(VillageRequest $request): JsonResponse
    {
        $village = Village::create($request->validated());
        $village = $this->baseVillageQuery()->find($village->id);

        return $this->successResponse(
            new VillageResource($village),
            'Desa berhasil dibuat',
            201
        );
    }

    /**
     * Display the specified village with its blocks and counts.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $accessibleBlockIds = $this->territoryScope->getAccessibleBlockIds($request->user());

        $village = $this->baseVillageQuery()
            ->with([
                'blocks' => function ($query) use ($accessibleBlockIds) {
                    if ($accessibleBlockIds !== null) {
                        $query->whereIn('blocks.id', $accessibleBlockIds);
                    }
                    $query->withCount(['gardens', 'trees'])->orderBy('name');
                },
            ])
            ->find($id);

        if (! $village) {
            return $this->errorResponse('Desa tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessVillage($request->user(), $village->id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke desa ini', 403);
        }

        return $this->successResponse(new VillageResource($village), 'Detail desa berhasil dimuat');
    }

    /**
     * Update the specified village.
     */
    public function update(VillageRequest $request, string $id): JsonResponse
    {
        $village = Village::find($id);

        if (! $village) {
            return $this->errorResponse('Desa tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessVillage($request->user(), $village->id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk mengubah desa ini', 403);
        }

        $village->update($request->validated());
        $village = $this->baseVillageQuery()->find($village->id);

        return $this->successResponse(new VillageResource($village), 'Desa berhasil diperbarui');
    }

    /**
     * Remove the specified village if it has no blocks.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $village = Village::find($id);

        if (! $village) {
            return $this->errorResponse('Desa tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessVillage($request->user(), $village->id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk menghapus desa ini', 403);
        }

        if ($village->blocks()->exists()) {
            return $this->errorResponse('Desa tidak dapat dihapus karena masih memiliki blok terdaftar', 422);
        }

        $village->delete();

        return $this->successResponse(null, 'Desa berhasil dihapus');
    }

    /**
     * Base query for Village including blocks, gardens, and trees counts without N+1 queries.
     *
     * @return Builder<Village>
     */
    protected function baseVillageQuery(): Builder
    {
        return Village::query()
            ->select('villages.*')
            ->withCount(['blocks', 'gardens'])
            ->addSelect([
                'trees_count' => Tree::query()
                    ->selectRaw('COUNT(*)')
                    ->join('gardens', 'trees.garden_id', '=', 'gardens.id')
                    ->join('blocks', 'gardens.block_id', '=', 'blocks.id')
                    ->whereColumn('blocks.village_id', 'villages.id'),
            ]);
    }
}
