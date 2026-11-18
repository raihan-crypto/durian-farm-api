<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HarvestSeasonRequest;
use App\Http\Resources\Api\V1\HarvestSeasonResource;
use App\Models\HarvestSeason;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HarvestSeasonController extends Controller
{
    use ApiResponse;

    public function __construct(protected TerritoryScopeService $territoryScope) {}

    /**
     * Display a listing of harvest seasons scoped to user's accessible gardens.
     */
    public function index(Request $request): JsonResponse
    {
        $query = HarvestSeason::query()
            ->with(['garden.block.village'])
            ->withCount('sessions');

        $accessibleGardenIds = $this->territoryScope->getAccessibleGardenIds($request->user());
        if ($accessibleGardenIds !== null) {
            $query->whereIn('garden_id', $accessibleGardenIds);
        }

        if ($request->filled('garden_id')) {
            $query->where('garden_id', $request->string('garden_id')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('year')) {
            $query->whereYear('start_date', (int) $request->input('year'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $seasons = $query->orderByDesc('start_date')->paginate($perPage);

        return $this->successResponse([
            'items' => HarvestSeasonResource::collection($seasons->items()),
            'pagination' => [
                'current_page' => $seasons->currentPage(),
                'last_page' => $seasons->lastPage(),
                'per_page' => $seasons->perPage(),
                'total' => $seasons->total(),
            ],
        ], 'Daftar musim panen berhasil dimuat');
    }

    /**
     * Store a newly created harvest season.
     */
    public function store(HarvestSeasonRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! $this->territoryScope->canAccessGarden($request->user(), $data['garden_id'])) {
            return $this->errorResponse('Anda tidak memiliki akses untuk membuat musim panen pada kebun ini', 403);
        }

        $data['status'] = $data['status'] ?? 'active';

        $season = HarvestSeason::create($data);
        $season->load('garden.block.village')->loadCount('sessions');

        return $this->successResponse(
            new HarvestSeasonResource($season),
            'Musim panen berhasil dibuat',
            201
        );
    }

    /**
     * Display the specified harvest season with its sessions.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $season = HarvestSeason::query()
            ->with([
                'garden.block.village',
                'sessions' => fn ($q) => $q->withCount('records')
                    ->withSum('records', 'fruit_count')
                    ->withSum('records', 'total_weight_kg')
                    ->orderByDesc('harvest_date'),
            ])
            ->withCount('sessions')
            ->find($id);

        if (! $season) {
            return $this->errorResponse('Musim panen tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $season->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke musim panen ini', 403);
        }

        return $this->successResponse(
            new HarvestSeasonResource($season),
            'Detail musim panen berhasil dimuat'
        );
    }

    /**
     * Update the specified harvest season.
     */
    public function update(HarvestSeasonRequest $request, string $id): JsonResponse
    {
        $season = HarvestSeason::find($id);

        if (! $season) {
            return $this->errorResponse('Musim panen tidak ditemukan', 404);
        }

        $data = $request->validated();

        if (
            ! $this->territoryScope->canAccessGarden($request->user(), $season->garden_id) ||
            ! $this->territoryScope->canAccessGarden($request->user(), $data['garden_id'])
        ) {
            return $this->errorResponse('Anda tidak memiliki akses untuk mengubah musim panen ini', 403);
        }

        if ($season->garden_id !== $data['garden_id'] && $season->sessions()->exists()) {
            return $this->errorResponse(
                'Kebun pada musim panen tidak dapat diubah karena sudah memiliki sesi panen terdaftar',
                422
            );
        }

        $season->update($data);
        $season->load('garden.block.village')->loadCount('sessions');

        return $this->successResponse(
            new HarvestSeasonResource($season),
            'Musim panen berhasil diperbarui'
        );
    }

    /**
     * Remove the specified harvest season if it has no sessions.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $season = HarvestSeason::find($id);

        if (! $season) {
            return $this->errorResponse('Musim panen tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $season->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk menghapus musim panen ini', 403);
        }

        if ($season->sessions()->exists()) {
            return $this->errorResponse('Musim panen tidak dapat dihapus karena masih memiliki sesi panen terdaftar', 422);
        }

        $season->delete();

        return $this->successResponse(null, 'Musim panen berhasil dihapus');
    }
}
