<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FarmerRequest;
use App\Http\Resources\Api\V1\BlockFarmerResource;
use App\Http\Resources\Api\V1\FarmerResource;
use App\Http\Resources\Api\V1\GardenFarmerResource;
use App\Http\Resources\Api\V1\GardenResource;
use App\Models\BlockFarmer;
use App\Models\Farmer;
use App\Models\Garden;
use App\Models\GardenFarmer;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FarmerController extends Controller
{
    use ApiResponse;

    public function __construct(protected TerritoryScopeService $territoryScope) {}

    /**
     * Display a listing of farmers.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Farmer::query()
            ->with('user')
            ->withCount(['blockFarmers', 'gardenFarmers']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->has('has_user_account')) {
            if (filter_var($request->input('has_user_account'), FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('user_id');
            } else {
                $query->whereNull('user_id');
            }
        }

        if ($request->filled('block_id')) {
            $blockId = $request->string('block_id')->toString();
            $query->whereHas('blockFarmers', function (Builder $q) use ($blockId) {
                $q->where('block_id', $blockId);
            });
        }

        if ($request->filled('garden_id')) {
            $gardenId = $request->string('garden_id')->toString();
            $query->whereHas('gardenFarmers', function (Builder $q) use ($gardenId) {
                $q->where('garden_id', $gardenId);
            });
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $farmers = $query->orderBy('name')->paginate($perPage);

        return $this->successResponse([
            'items' => FarmerResource::collection($farmers->items()),
            'pagination' => [
                'current_page' => $farmers->currentPage(),
                'last_page' => $farmers->lastPage(),
                'per_page' => $farmers->perPage(),
                'total' => $farmers->total(),
            ],
        ], 'Daftar petani berhasil dimuat');
    }

    /**
     * Store a newly created farmer.
     */
    public function store(FarmerRequest $request): JsonResponse
    {
        $farmer = Farmer::create($request->validated());
        $farmer->load('user')->loadCount(['blockFarmers', 'gardenFarmers']);

        return $this->successResponse(
            new FarmerResource($farmer),
            'Petani berhasil dibuat',
            201
        );
    }

    /**
     * Display the specified farmer with assignments.
     */
    public function show(string $id): JsonResponse
    {
        $farmer = Farmer::query()
            ->with([
                'user',
                'blockFarmers' => fn ($q) => $q->with('block.village')->orderByDesc('assignment_date'),
                'gardenFarmers' => fn ($q) => $q->with('garden.block.village')->orderByDesc('assignment_date'),
            ])
            ->withCount(['blockFarmers', 'gardenFarmers'])
            ->find($id);

        if (! $farmer) {
            return $this->errorResponse('Petani tidak ditemukan', 404);
        }

        return $this->successResponse(new FarmerResource($farmer), 'Detail petani berhasil dimuat');
    }

    /**
     * Update the specified farmer.
     */
    public function update(FarmerRequest $request, string $id): JsonResponse
    {
        $farmer = Farmer::find($id);

        if (! $farmer) {
            return $this->errorResponse('Petani tidak ditemukan', 404);
        }

        $farmer->update($request->validated());
        $farmer->load('user')->loadCount(['blockFarmers', 'gardenFarmers']);

        return $this->successResponse(new FarmerResource($farmer), 'Petani berhasil diperbarui');
    }

    /**
     * Remove the specified farmer if no active assignments exist.
     */
    public function destroy(string $id): JsonResponse
    {
        $farmer = Farmer::find($id);

        if (! $farmer) {
            return $this->errorResponse('Petani tidak ditemukan', 404);
        }

        $today = Carbon::today()->toDateString();

        $hasActiveBlockAssignment = $farmer->blockFarmers()
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->exists();

        $hasActiveGardenAssignment = $farmer->gardenFarmers()
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->exists();

        if ($hasActiveBlockAssignment || $hasActiveGardenAssignment) {
            return $this->errorResponse('Petani tidak dapat dihapus karena masih memiliki penugasan aktif', 422);
        }

        $farmer->delete();

        return $this->successResponse(null, 'Petani berhasil dihapus');
    }

    /**
     * Return active block and garden assignments for the authenticated farmer user.
     */
    public function myAssignments(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->is_active) {
            return $this->errorResponse('Akun Anda dinonaktifkan', 403);
        }

        $farmer = $user->farmerProfile;

        if (! $farmer) {
            return $this->successResponse([
                'farmer' => null,
                'assigned_blocks' => [],
                'assigned_gardens' => [],
                'accessible_gardens' => [],
                'summary' => [
                    'total_assigned_blocks' => 0,
                    'total_direct_gardens' => 0,
                    'total_accessible_gardens' => 0,
                    'total_accessible_trees' => 0,
                ],
            ], 'Akun Anda belum terhubung dengan profil petani');
        }

        $today = Carbon::today()->toDateString();

        $assignedBlocks = BlockFarmer::query()
            ->with([
                'block' => fn ($q) => $q->with('village')->withCount(['gardens', 'trees']),
            ])
            ->where('farmer_id', $farmer->id)
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->orderByDesc('assignment_date')
            ->get();

        $assignedGardens = GardenFarmer::query()
            ->with([
                'garden' => fn ($q) => $q->with('block.village')->withCount('trees'),
            ])
            ->where('farmer_id', $farmer->id)
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->orderByDesc('assignment_date')
            ->get();

        $activeBlockIds = $assignedBlocks->pluck('block_id')->unique()->values()->all();
        $directGardenIds = $assignedGardens->pluck('garden_id')->unique()->values()->all();

        $accessibleGardens = Garden::query()
            ->with(['block.village', 'gardenOwners.owner'])
            ->withCount('trees')
            ->where(function (Builder $q) use ($directGardenIds, $activeBlockIds) {
                if (! empty($directGardenIds)) {
                    $q->whereIn('id', $directGardenIds);
                }
                if (! empty($activeBlockIds)) {
                    $q->orWhereIn('block_id', $activeBlockIds);
                }
                if (empty($directGardenIds) && empty($activeBlockIds)) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->orderBy('name')
            ->get();

        $accessibleGardenItems = $accessibleGardens->map(function (Garden $garden) use ($directGardenIds, $activeBlockIds, $request) {
            $sources = [];
            if (in_array($garden->id, $directGardenIds, true)) {
                $sources[] = 'direct_garden';
            }
            if (in_array($garden->block_id, $activeBlockIds, true)) {
                $sources[] = 'block';
            }

            return array_merge(
                (new GardenResource($garden))->resolve($request),
                ['assignment_sources' => $sources]
            );
        })->values();

        return $this->successResponse([
            'farmer' => new FarmerResource($farmer),
            'assigned_blocks' => BlockFarmerResource::collection($assignedBlocks),
            'assigned_gardens' => GardenFarmerResource::collection($assignedGardens),
            'accessible_gardens' => $accessibleGardenItems,
            'summary' => [
                'total_assigned_blocks' => $assignedBlocks->count(),
                'total_direct_gardens' => $assignedGardens->count(),
                'total_accessible_gardens' => $accessibleGardens->count(),
                'total_accessible_trees' => (int) $accessibleGardens->sum('trees_count'),
            ],
        ], 'Data penugasan Anda berhasil dimuat');
    }
}
