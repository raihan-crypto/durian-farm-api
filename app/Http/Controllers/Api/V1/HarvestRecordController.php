<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HarvestRecordRequest;
use App\Http\Resources\Api\V1\HarvestRecordResource;
use App\Models\HarvestDetail;
use App\Models\HarvestRecord;
use App\Models\HarvestSession;
use App\Models\Tree;
use App\Services\PhotoStorageService;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HarvestRecordController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TerritoryScopeService $territoryScope,
        protected PhotoStorageService $photoStorage,
    ) {}

    /**
     * Display a listing of per-tree harvest records scoped to user's accessible gardens.
     */
    public function index(Request $request): JsonResponse
    {
        $query = HarvestRecord::query()
            ->with(['session', 'garden', 'tree.variety', 'details', 'photos']);

        $accessibleGardenIds = $this->territoryScope->getAccessibleGardenIds($request->user());
        if ($accessibleGardenIds !== null) {
            $query->whereIn('harvest_records.garden_id', $accessibleGardenIds);
        }

        if ($request->filled('session_id')) {
            $query->where('harvest_records.session_id', $request->string('session_id')->toString());
        }

        if ($request->filled('season_id')) {
            $seasonId = $request->string('season_id')->toString();
            $query->whereHas('session', fn (Builder $q) => $q->where('season_id', $seasonId));
        }

        if ($request->filled('garden_id')) {
            $query->where('harvest_records.garden_id', $request->string('garden_id')->toString());
        }

        if ($request->filled('tree_id')) {
            $query->where('harvest_records.tree_id', $request->string('tree_id')->toString());
        }

        if ($request->filled('variety_id')) {
            $varietyId = $request->string('variety_id')->toString();
            $query->whereHas('tree', fn (Builder $q) => $q->where('variety_id', $varietyId));
        }

        if ($request->filled('from_date')) {
            $fromDate = $request->string('from_date')->toString();
            $query->whereHas('session', fn (Builder $q) => $q->whereDate('harvest_date', '>=', $fromDate));
        }

        if ($request->filled('to_date')) {
            $toDate = $request->string('to_date')->toString();
            $query->whereHas('session', fn (Builder $q) => $q->whereDate('harvest_date', '<=', $toDate));
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $records = $query->orderByDesc('harvest_records.created_at')->paginate($perPage);

        return $this->successResponse([
            'items' => HarvestRecordResource::collection($records->items()),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ], 'Daftar hasil panen per pohon berhasil dimuat');
    }

    /**
     * Store a new per-tree harvest record with strict composite FK validation (SRS 6.3 & 15.4).
     */
    public function store(HarvestRecordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $session = HarvestSession::findOrFail($data['session_id']);
        $tree = Tree::findOrFail($data['tree_id']);

        if ($tree->garden_id !== $session->garden_id) {
            return $this->errorResponse(
                'Pohon tidak berada dalam kebun yang sama dengan sesi panen',
                422
            );
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $session->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk mencatat hasil panen pada kebun ini', 403);
        }

        $exists = HarvestRecord::query()
            ->where('session_id', $session->id)
            ->where('tree_id', $tree->id)
            ->exists();

        if ($exists) {
            return $this->errorResponse(
                'Hasil panen untuk pohon ini pada sesi panen tersebut sudah tercatat',
                422
            );
        }

        $details = $data['details'] ?? [];
        $totals = $this->resolveAndValidateTotals($data, $details);
        if (isset($totals['error'])) {
            return $this->errorResponse($totals['error'], 422);
        }

        $record = DB::transaction(function () use ($session, $tree, $data, $details, $totals, $request) {
            $record = HarvestRecord::create([
                'session_id' => $session->id,
                'garden_id' => $session->garden_id,
                'tree_id' => $tree->id,
                'fruit_count' => $totals['fruit_count'],
                'total_weight_kg' => $totals['total_weight_kg'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($details as $detail) {
                HarvestDetail::create([
                    'harvest_record_id' => $record->id,
                    'fruit_grade' => $detail['fruit_grade'] ?? null,
                    'fruit_count' => (int) $detail['fruit_count'],
                    'weight_kg' => (float) $detail['weight_kg'],
                    'notes' => $detail['notes'] ?? null,
                ]);
            }

            foreach ($request->file('photos', []) as $file) {
                $this->photoStorage->storeActivityPhoto(
                    $file,
                    'harvest',
                    $record->id,
                    $request->user()->id
                );
            }

            return $record->load(['session', 'garden', 'tree.variety', 'details', 'photos']);
        });

        return $this->successResponse(
            new HarvestRecordResource($record),
            'Hasil panen pohon berhasil dicatat',
            201
        );
    }

    /**
     * Display the specified harvest record.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $record = HarvestRecord::query()
            ->with(['session', 'garden', 'tree.variety', 'details', 'photos'])
            ->find($id);

        if (! $record) {
            return $this->errorResponse('Catatan hasil panen tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $record->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke catatan hasil panen ini', 403);
        }

        return $this->successResponse(
            new HarvestRecordResource($record),
            'Detail hasil panen berhasil dimuat'
        );
    }

    /**
     * Update an existing harvest record and optionally replace its grade details.
     */
    public function update(HarvestRecordRequest $request, string $id): JsonResponse
    {
        $record = HarvestRecord::find($id);

        if (! $record) {
            return $this->errorResponse('Catatan hasil panen tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $record->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk mengubah catatan hasil panen ini', 403);
        }

        $data = $request->validated();
        $session = HarvestSession::findOrFail($data['session_id']);
        $tree = Tree::findOrFail($data['tree_id']);

        if ($tree->garden_id !== $session->garden_id) {
            return $this->errorResponse('Pohon tidak berada dalam kebun yang sama dengan sesi panen', 422);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $session->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke kebun tujuan', 403);
        }

        $duplicate = HarvestRecord::query()
            ->where('session_id', $session->id)
            ->where('tree_id', $tree->id)
            ->where('id', '!=', $record->id)
            ->exists();

        if ($duplicate) {
            return $this->errorResponse(
                'Hasil panen untuk pohon ini pada sesi panen tersebut sudah tercatat',
                422
            );
        }

        $hasDetailsPayload = array_key_exists('details', $data);
        $details = $data['details'] ?? [];
        $totals = $this->resolveAndValidateTotals($data, $hasDetailsPayload ? $details : []);
        if (isset($totals['error'])) {
            return $this->errorResponse($totals['error'], 422);
        }

        $updated = DB::transaction(function () use ($record, $session, $tree, $data, $totals, $hasDetailsPayload, $details) {
            $record->update([
                'session_id' => $session->id,
                'garden_id' => $session->garden_id,
                'tree_id' => $tree->id,
                'fruit_count' => $totals['fruit_count'],
                'total_weight_kg' => $totals['total_weight_kg'],
                'notes' => $data['notes'] ?? $record->notes,
            ]);

            if ($hasDetailsPayload) {
                $record->details()->delete();

                foreach ($details as $detail) {
                    HarvestDetail::create([
                        'harvest_record_id' => $record->id,
                        'fruit_grade' => $detail['fruit_grade'] ?? null,
                        'fruit_count' => (int) $detail['fruit_count'],
                        'weight_kg' => (float) $detail['weight_kg'],
                        'notes' => $detail['notes'] ?? null,
                    ]);
                }
            }

            return $record->fresh(['session', 'garden', 'tree.variety', 'details', 'photos']);
        });

        return $this->successResponse(
            new HarvestRecordResource($updated),
            'Catatan hasil panen berhasil diperbarui'
        );
    }

    /**
     * Resolve total fruit_count and total_weight_kg from header or details and ensure consistency.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $details
     * @return array{fruit_count?: int, total_weight_kg?: float, error?: string}
     */
    protected function resolveAndValidateTotals(array $data, array $details): array
    {
        $detailsFruitSum = (int) collect($details)->sum('fruit_count');
        $detailsWeightSum = round((float) collect($details)->sum('weight_kg'), 3);

        $fruitCount = isset($data['fruit_count']) ? (int) $data['fruit_count'] : $detailsFruitSum;
        $totalWeight = isset($data['total_weight_kg']) ? round((float) $data['total_weight_kg'], 3) : $detailsWeightSum;

        if (! empty($details)) {
            if ($detailsFruitSum > $fruitCount) {
                return ['error' => 'Total jumlah buah pada detail grade tidak boleh melebihi jumlah buah utama'];
            }

            if ($detailsWeightSum > ($totalWeight + 0.001)) {
                return ['error' => 'Total berat pada detail grade tidak boleh melebihi total berat panen utama'];
            }
        }

        return [
            'fruit_count' => $fruitCount,
            'total_weight_kg' => $totalWeight,
        ];
    }
}
