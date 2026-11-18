<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FarmerAssignmentRequest;
use App\Http\Requests\Api\V1\GardenOwnerRequest;
use App\Http\Requests\Api\V1\GardenRequest;
use App\Http\Resources\Api\V1\GardenFarmerResource;
use App\Http\Resources\Api\V1\GardenOwnerResource;
use App\Http\Resources\Api\V1\GardenResource;
use App\Models\Garden;
use App\Models\GardenFarmer;
use App\Models\GardenOwner;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GardenController extends Controller
{
    use ApiResponse;

    public function __construct(protected TerritoryScopeService $territoryScope) {}

    /**
     * Display a listing of gardens.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Garden::query()
            ->with([
                'block.village',
                'gardenOwners.owner',
                'gardenFarmers.farmer',
            ])
            ->withCount('trees');

        $accessibleGardenIds = $this->territoryScope->getAccessibleGardenIds($request->user());
        if ($accessibleGardenIds !== null) {
            $query->whereIn('gardens.id', $accessibleGardenIds);
        }

        if ($request->filled('block_id')) {
            $query->where('block_id', $request->string('block_id')->toString());
        }

        if ($request->filled('village_id')) {
            $villageId = $request->string('village_id')->toString();
            $query->whereHas('block', function (Builder $q) use ($villageId) {
                $q->where('village_id', $villageId);
            });
        }

        if ($request->filled('owner_id')) {
            $ownerId = $request->string('owner_id')->toString();
            $query->whereHas('gardenOwners', function (Builder $q) use ($ownerId) {
                $q->where('owner_id', $ownerId);
            });
        }

        if ($request->filled('farmer_id')) {
            $farmerId = $request->string('farmer_id')->toString();
            $query->whereHas('gardenFarmers', function (Builder $q) use ($farmerId) {
                $q->where('farmer_id', $farmerId);
            });
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $q) use ($search) {
                $q->where('gardens.name', 'like', "%{$search}%")
                    ->orWhere('gardens.address', 'like', "%{$search}%")
                    ->orWhere('gardens.description', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $gardens = $query->orderBy('gardens.name')->paginate($perPage);

        return $this->successResponse([
            'items' => GardenResource::collection($gardens->items()),
            'pagination' => [
                'current_page' => $gardens->currentPage(),
                'last_page' => $gardens->lastPage(),
                'per_page' => $gardens->perPage(),
                'total' => $gardens->total(),
            ],
        ], 'Daftar kebun berhasil dimuat');
    }

    /**
     * Store a newly created garden with optional owners and farmer assignments.
     */
    public function store(GardenRequest $request): JsonResponse
    {
        $data = $request->validated();
        $owners = $data['owners'] ?? [];
        $farmers = $data['farmers'] ?? [];
        $today = Carbon::today();

        // Validate total active ownership percentage does not exceed 100%
        $activePercentageSum = 0.0;
        $ownerPeriodKeys = [];
        foreach ($owners as $ownerData) {
            $startDate = $ownerData['start_date'] ?? $today->toDateString();
            $key = $ownerData['owner_id'].'|'.$startDate;
            if (in_array($key, $ownerPeriodKeys, true)) {
                return $this->errorResponse('Terdapat duplikasi pemilik kebun dengan tanggal mulai yang sama', 422);
            }
            $ownerPeriodKeys[] = $key;

            $endDate = $ownerData['end_date'] ?? null;
            $isActive = $endDate === null || Carbon::parse($endDate)->gte($today);
            if ($isActive && isset($ownerData['ownership_percentage'])) {
                $activePercentageSum += (float) $ownerData['ownership_percentage'];
            }
        }

        if ($activePercentageSum > 100.0001) {
            return $this->errorResponse('Total persentase kepemilikan aktif kebun tidak boleh melebihi 100%', 422);
        }

        $farmerPeriodKeys = [];
        foreach ($farmers as $farmerData) {
            $assignmentDate = $farmerData['assignment_date'] ?? $today->toDateString();
            $key = $farmerData['farmer_id'].'|'.$assignmentDate;
            if (in_array($key, $farmerPeriodKeys, true)) {
                return $this->errorResponse('Terdapat duplikasi penugasan petani dengan tanggal mulai yang sama', 422);
            }
            $farmerPeriodKeys[] = $key;
        }

        $garden = DB::transaction(function () use ($data, $owners, $farmers, $today) {
            $gardenData = collect($data)->only([
                'block_id',
                'name',
                'area_hectares',
                'address',
                'latitude',
                'longitude',
                'description',
            ])->all();

            $garden = Garden::create($gardenData);

            foreach ($owners as $ownerData) {
                GardenOwner::create([
                    'garden_id' => $garden->id,
                    'owner_id' => $ownerData['owner_id'],
                    'ownership_percentage' => $ownerData['ownership_percentage'] ?? null,
                    'start_date' => $ownerData['start_date'] ?? $today->toDateString(),
                    'end_date' => $ownerData['end_date'] ?? null,
                ]);
            }

            foreach ($farmers as $farmerData) {
                GardenFarmer::create([
                    'garden_id' => $garden->id,
                    'farmer_id' => $farmerData['farmer_id'],
                    'assignment_date' => $farmerData['assignment_date'] ?? $today->toDateString(),
                    'end_date' => $farmerData['end_date'] ?? null,
                ]);
            }

            return $garden->load(['block.village', 'gardenOwners.owner', 'gardenFarmers.farmer'])
                ->loadCount('trees');
        });

        return $this->successResponse(
            new GardenResource($garden),
            'Kebun berhasil dibuat',
            201
        );
    }

    /**
     * Display the specified garden.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $garden = Garden::query()
            ->with([
                'block.village',
                'gardenOwners' => fn ($q) => $q->with('owner')->orderByDesc('start_date'),
                'gardenFarmers' => fn ($q) => $q->with('farmer')->orderByDesc('assignment_date'),
            ])
            ->withCount(['trees', 'harvestSeasons', 'harvestSessions'])
            ->find($id);

        if (! $garden) {
            return $this->errorResponse('Kebun tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $garden->id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke kebun ini', 403);
        }

        return $this->successResponse(new GardenResource($garden), 'Detail kebun berhasil dimuat');
    }

    /**
     * Update the specified garden.
     */
    public function update(GardenRequest $request, string $id): JsonResponse
    {
        $garden = Garden::find($id);

        if (! $garden) {
            return $this->errorResponse('Kebun tidak ditemukan', 404);
        }

        $gardenData = collect($request->validated())->only([
            'block_id',
            'name',
            'area_hectares',
            'address',
            'latitude',
            'longitude',
            'description',
        ])->all();

        $garden->update($gardenData);
        $garden->load(['block.village', 'gardenOwners.owner', 'gardenFarmers.farmer'])
            ->loadCount('trees');

        return $this->successResponse(new GardenResource($garden), 'Kebun berhasil diperbarui');
    }

    /**
     * Remove the specified garden if it has no registered trees.
     */
    public function destroy(string $id): JsonResponse
    {
        $garden = Garden::find($id);

        if (! $garden) {
            return $this->errorResponse('Kebun tidak ditemukan', 404);
        }

        if ($garden->trees()->exists()) {
            return $this->errorResponse('Kebun tidak dapat dihapus karena masih memiliki pohon terdaftar', 422);
        }

        $garden->delete();

        return $this->successResponse(null, 'Kebun berhasil dihapus');
    }

    /**
     * List ownership records for a garden.
     */
    public function owners(Request $request, string $id): JsonResponse
    {
        $garden = Garden::find($id);

        if (! $garden) {
            return $this->errorResponse('Kebun tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $garden->id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke kebun ini', 403);
        }

        $query = GardenOwner::query()
            ->with('owner')
            ->where('garden_id', $garden->id);

        if ($request->boolean('active_only')) {
            $today = Carbon::today()->toDateString();
            $query->where(function (Builder $q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            });
        }

        $ownerships = $query->orderByDesc('start_date')->get();

        return $this->successResponse(
            GardenOwnerResource::collection($ownerships),
            'Daftar pemilik kebun berhasil dimuat'
        );
    }

    /**
     * Attach an owner to a garden.
     */
    public function assignOwner(GardenOwnerRequest $request, string $id): JsonResponse
    {
        $garden = Garden::find($id);

        if (! $garden) {
            return $this->errorResponse('Kebun tidak ditemukan', 404);
        }

        $data = $request->validated();
        $today = Carbon::today();
        $startDate = $data['start_date'] ?? $today->toDateString();
        $endDate = $data['end_date'] ?? null;
        $percentage = isset($data['ownership_percentage']) ? (float) $data['ownership_percentage'] : null;

        $duplicate = GardenOwner::query()
            ->where('garden_id', $garden->id)
            ->where('owner_id', $data['owner_id'])
            ->whereDate('start_date', $startDate)
            ->exists();

        if ($duplicate) {
            return $this->errorResponse('Pemilik sudah terdaftar pada kebun ini dengan tanggal mulai yang sama', 422);
        }

        if ($this->exceedsActiveOwnershipLimit($garden->id, $percentage, $endDate)) {
            return $this->errorResponse('Total persentase kepemilikan aktif kebun tidak boleh melebihi 100%', 422);
        }

        $ownership = GardenOwner::create([
            'garden_id' => $garden->id,
            'owner_id' => $data['owner_id'],
            'ownership_percentage' => $percentage,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $ownership->load('owner');

        return $this->successResponse(
            new GardenOwnerResource($ownership),
            'Pemilik berhasil dihubungkan ke kebun',
            201
        );
    }

    /**
     * Update or end a garden ownership record.
     */
    public function updateOwner(GardenOwnerRequest $request, string $id, string $ownershipId): JsonResponse
    {
        $ownership = GardenOwner::query()
            ->where('garden_id', $id)
            ->find($ownershipId);

        if (! $ownership) {
            return $this->errorResponse('Data kepemilikan kebun tidak ditemukan', 404);
        }

        $data = $request->validated();
        $effectiveStartDate = array_key_exists('start_date', $data)
            ? $data['start_date']
            : $ownership->start_date?->toDateString();
        $effectiveEndDate = array_key_exists('end_date', $data)
            ? $data['end_date']
            : $ownership->end_date?->toDateString();
        $effectivePercentage = array_key_exists('ownership_percentage', $data)
            ? ($data['ownership_percentage'] !== null ? (float) $data['ownership_percentage'] : null)
            : ($ownership->ownership_percentage !== null ? (float) $ownership->ownership_percentage : null);

        if ($effectiveStartDate && $effectiveEndDate && Carbon::parse($effectiveEndDate)->lt(Carbon::parse($effectiveStartDate))) {
            return $this->errorResponse('Tanggal selesai kepemilikan tidak boleh lebih awal dari tanggal mulai', 422);
        }

        if ($effectiveStartDate) {
            $duplicate = GardenOwner::query()
                ->where('garden_id', $id)
                ->where('owner_id', $ownership->owner_id)
                ->whereDate('start_date', $effectiveStartDate)
                ->where('id', '!=', $ownership->id)
                ->exists();

            if ($duplicate) {
                return $this->errorResponse('Pemilik sudah terdaftar pada kebun ini dengan tanggal mulai yang sama', 422);
            }
        }

        if ($this->exceedsActiveOwnershipLimit($id, $effectivePercentage, $effectiveEndDate, $ownership->id)) {
            return $this->errorResponse('Total persentase kepemilikan aktif kebun tidak boleh melebihi 100%', 422);
        }

        $ownership->update([
            'ownership_percentage' => $effectivePercentage,
            'start_date' => $effectiveStartDate,
            'end_date' => $effectiveEndDate,
        ]);

        $ownership->load('owner');

        return $this->successResponse(
            new GardenOwnerResource($ownership),
            'Data kepemilikan kebun berhasil diperbarui'
        );
    }

    /**
     * Remove an ownership record from a garden.
     */
    public function removeOwner(string $id, string $ownershipId): JsonResponse
    {
        $ownership = GardenOwner::query()
            ->where('garden_id', $id)
            ->find($ownershipId);

        if (! $ownership) {
            return $this->errorResponse('Data kepemilikan kebun tidak ditemukan', 404);
        }

        $ownership->delete();

        return $this->successResponse(null, 'Data kepemilikan kebun berhasil dihapus');
    }

    /**
     * List farmer assignments for a garden.
     */
    public function farmers(Request $request, string $id): JsonResponse
    {
        $garden = Garden::find($id);

        if (! $garden) {
            return $this->errorResponse('Kebun tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $garden->id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke kebun ini', 403);
        }

        $query = GardenFarmer::query()
            ->with('farmer')
            ->where('garden_id', $garden->id);

        if ($request->boolean('active_only')) {
            $today = Carbon::today()->toDateString();
            $query->where(function (Builder $q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            });
        }

        $assignments = $query->orderByDesc('assignment_date')->get();

        return $this->successResponse(
            GardenFarmerResource::collection($assignments),
            'Daftar penugasan petani pada kebun berhasil dimuat'
        );
    }

    /**
     * Assign a farmer directly to a garden.
     */
    public function assignFarmer(FarmerAssignmentRequest $request, string $id): JsonResponse
    {
        $garden = Garden::find($id);

        if (! $garden) {
            return $this->errorResponse('Kebun tidak ditemukan', 404);
        }

        $data = $request->validated();
        $assignmentDate = $data['assignment_date'] ?? Carbon::today()->toDateString();

        $duplicate = GardenFarmer::query()
            ->where('garden_id', $garden->id)
            ->where('farmer_id', $data['farmer_id'])
            ->whereDate('assignment_date', $assignmentDate)
            ->exists();

        if ($duplicate) {
            return $this->errorResponse('Petani sudah memiliki penugasan pada kebun ini dengan tanggal mulai yang sama', 422);
        }

        $assignment = GardenFarmer::create([
            'garden_id' => $garden->id,
            'farmer_id' => $data['farmer_id'],
            'assignment_date' => $assignmentDate,
            'end_date' => $data['end_date'] ?? null,
        ]);

        $assignment->load('farmer');

        return $this->successResponse(
            new GardenFarmerResource($assignment),
            'Petani berhasil ditugaskan ke kebun',
            201
        );
    }

    /**
     * Update or end a farmer assignment on a garden.
     */
    public function updateFarmerAssignment(FarmerAssignmentRequest $request, string $id, string $assignmentId): JsonResponse
    {
        $assignment = GardenFarmer::query()
            ->where('garden_id', $id)
            ->find($assignmentId);

        if (! $assignment) {
            return $this->errorResponse('Penugasan petani pada kebun tidak ditemukan', 404);
        }

        $data = $request->validated();
        $effectiveAssignmentDate = array_key_exists('assignment_date', $data)
            ? $data['assignment_date']
            : $assignment->assignment_date?->toDateString();
        $effectiveEndDate = array_key_exists('end_date', $data)
            ? $data['end_date']
            : $assignment->end_date?->toDateString();

        if ($effectiveAssignmentDate && $effectiveEndDate && Carbon::parse($effectiveEndDate)->lt(Carbon::parse($effectiveAssignmentDate))) {
            return $this->errorResponse('Tanggal selesai penugasan tidak boleh lebih awal dari tanggal mulai', 422);
        }

        if ($effectiveAssignmentDate) {
            $duplicate = GardenFarmer::query()
                ->where('garden_id', $id)
                ->where('farmer_id', $assignment->farmer_id)
                ->whereDate('assignment_date', $effectiveAssignmentDate)
                ->where('id', '!=', $assignment->id)
                ->exists();

            if ($duplicate) {
                return $this->errorResponse('Petani sudah memiliki penugasan pada kebun ini dengan tanggal mulai yang sama', 422);
            }
        }

        $assignment->update([
            'assignment_date' => $effectiveAssignmentDate,
            'end_date' => $effectiveEndDate,
        ]);

        $assignment->load('farmer');

        return $this->successResponse(
            new GardenFarmerResource($assignment),
            'Penugasan petani pada kebun berhasil diperbarui'
        );
    }

    /**
     * Remove a farmer assignment from a garden.
     */
    public function removeFarmerAssignment(string $id, string $assignmentId): JsonResponse
    {
        $assignment = GardenFarmer::query()
            ->where('garden_id', $id)
            ->find($assignmentId);

        if (! $assignment) {
            return $this->errorResponse('Penugasan petani pada kebun tidak ditemukan', 404);
        }

        $assignment->delete();

        return $this->successResponse(null, 'Penugasan petani pada kebun berhasil dihapus');
    }

    /**
     * Check if adding/updating an active ownership percentage exceeds 100%.
     */
    protected function exceedsActiveOwnershipLimit(
        string $gardenId,
        ?float $percentage,
        ?string $endDate,
        ?string $ignoreOwnershipId = null
    ): bool {
        if ($percentage === null) {
            return false;
        }

        $today = Carbon::today();
        $isActive = $endDate === null || Carbon::parse($endDate)->gte($today);

        if (! $isActive) {
            return false;
        }

        $todayString = $today->toDateString();
        $existingSum = (float) GardenOwner::query()
            ->where('garden_id', $gardenId)
            ->when($ignoreOwnershipId, fn (Builder $q) => $q->where('id', '!=', $ignoreOwnershipId))
            ->where(function (Builder $q) use ($todayString) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $todayString);
            })
            ->sum('ownership_percentage');

        return ($existingSum + $percentage) > 100.0001;
    }
}
