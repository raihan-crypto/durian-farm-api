<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TreeRequest;
use App\Http\Resources\Api\V1\TreeMeasurementResource;
use App\Http\Resources\Api\V1\TreeResource;
use App\Models\FertilizationRecord;
use App\Models\FloweringRecord;
use App\Models\FruitDevelopmentRecord;
use App\Models\HarvestRecord;
use App\Models\SprayingRecord;
use App\Models\Tree;
use App\Services\PhotoStorageService;
use App\Services\TerritoryScopeService;
use App\Services\TreeQrCodeService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TreeController extends Controller
{
    use ApiResponse;

    /**
     * Allowed columns for sorting trees.
     *
     * @var array<int, string>
     */
    private const ALLOWED_SORT_COLUMNS = [
        'tree_code',
        'tree_name',
        'planted_at',
        'status',
        'created_at',
    ];

    public function __construct(
        protected TerritoryScopeService $territoryScope,
        protected TreeQrCodeService $qrCodeService,
        protected PhotoStorageService $photoStorage,
    ) {}

    /**
     * Display a listing of durian trees scoped to the user's territory access.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tree::query()
            ->with(['garden.block.village', 'variety', 'latestMeasurement', 'qrCode'])
            ->withCount('measurements');

        $accessibleGardenIds = $this->territoryScope->getAccessibleGardenIds($request->user());
        if ($accessibleGardenIds !== null) {
            $query->whereIn('trees.garden_id', $accessibleGardenIds);
        }

        if ($request->filled('garden_id')) {
            $query->where('trees.garden_id', $request->string('garden_id')->toString());
        }

        if ($request->filled('block_id')) {
            $blockId = $request->string('block_id')->toString();
            $query->whereHas('garden', fn (Builder $q) => $q->where('block_id', $blockId));
        }

        if ($request->filled('village_id')) {
            $villageId = $request->string('village_id')->toString();
            $query->whereHas('garden.block', fn (Builder $q) => $q->where('village_id', $villageId));
        }

        if ($request->filled('variety_id')) {
            $query->where('trees.variety_id', $request->string('variety_id')->toString());
        }

        if ($request->filled('status')) {
            $query->where('trees.status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $q) use ($search) {
                $q->where('trees.tree_code', 'like', "%{$search}%")
                    ->orWhere('trees.tree_name', 'like', "%{$search}%")
                    ->orWhere('trees.notes', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->string('sort_by', 'tree_code')->toString();
        if (! in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true)) {
            $sortBy = 'tree_code';
        }
        $sortDir = strtolower($request->string('sort_dir', 'asc')->toString()) === 'desc' ? 'desc' : 'asc';

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $trees = $query->orderBy("trees.{$sortBy}", $sortDir)->paginate($perPage);

        return $this->successResponse([
            'items' => TreeResource::collection($trees->items()),
            'pagination' => [
                'current_page' => $trees->currentPage(),
                'last_page' => $trees->lastPage(),
                'per_page' => $trees->perPage(),
                'total' => $trees->total(),
            ],
        ], 'Daftar pohon durian berhasil dimuat');
    }

    /**
     * Store a newly created tree and automatically generate its unique QR Code.
     */
    public function store(TreeRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! $this->territoryScope->canAccessGarden($request->user(), $data['garden_id'])) {
            return $this->errorResponse('Anda tidak memiliki akses untuk menambah pohon pada kebun ini', 403);
        }

        $data['status'] = $data['status'] ?? 'active';

        $tree = DB::transaction(function () use ($data) {
            $tree = Tree::create($data);
            $this->qrCodeService->ensureForTree($tree);

            return $tree->load(['garden.block.village', 'variety', 'latestMeasurement', 'qrCode', 'photos'])
                ->loadCount('measurements');
        });

        return $this->successResponse(
            new TreeResource($tree),
            'Pohon durian berhasil dibuat',
            201
        );
    }

    /**
     * Display the specified tree with location hierarchy, variety, latest measurement, photos, and QR Code.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tree = Tree::query()
            ->with([
                'garden.block.village',
                'variety',
                'latestMeasurement.recorder',
                'qrCode',
                'photos',
            ])
            ->withCount('measurements')
            ->find($id);

        if (! $tree) {
            return $this->errorResponse('Pohon tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke pohon ini', 403);
        }

        return $this->successResponse(new TreeResource($tree), 'Detail pohon durian berhasil dimuat');
    }

    /**
     * Update the specified tree.
     */
    public function update(TreeRequest $request, string $id): JsonResponse
    {
        $tree = Tree::find($id);

        if (! $tree) {
            return $this->errorResponse('Pohon tidak ditemukan', 404);
        }

        $data = $request->validated();

        if (
            ! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id) ||
            ! $this->territoryScope->canAccessGarden($request->user(), $data['garden_id'])
        ) {
            return $this->errorResponse('Anda tidak memiliki akses untuk mengubah pohon pada kebun ini', 403);
        }

        $tree->update($data);
        $tree->load(['garden.block.village', 'variety', 'latestMeasurement', 'qrCode', 'photos'])
            ->loadCount('measurements');

        return $this->successResponse(new TreeResource($tree), 'Pohon durian berhasil diperbarui');
    }

    /**
     * Remove the specified tree only if it has no historical records.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tree = Tree::find($id);

        if (! $tree) {
            return $this->errorResponse('Pohon tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk menghapus pohon ini', 403);
        }

        $hasHistory = $tree->measurements()->exists()
            || $tree->fertilizationRecords()->exists()
            || $tree->sprayingRecords()->exists()
            || $tree->floweringRecords()->exists()
            || $tree->fruitDevelopmentRecords()->exists()
            || $tree->harvestRecords()->exists();

        if ($hasHistory) {
            return $this->errorResponse(
                'Pohon tidak dapat dihapus karena sudah memiliki riwayat pengukuran, perawatan, atau panen. Ubah status pohon sebagai gantinya.',
                422
            );
        }

        DB::transaction(function () use ($tree) {
            foreach ($tree->photos()->get() as $photo) {
                $this->photoStorage->deleteActivityPhoto($photo);
            }

            $tree->delete();
        });

        return $this->successResponse(null, 'Pohon durian berhasil dihapus');
    }

    /**
     * Return full chronological history for a tree (SRS 4.5 & 16.3).
     */
    public function history(Request $request, string $id): JsonResponse
    {
        $tree = Tree::query()->with(['garden.block.village', 'variety'])->find($id);

        if (! $tree) {
            return $this->errorResponse('Pohon tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke riwayat pohon ini', 403);
        }

        $sortDir = strtolower($request->string('sort_dir', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';
        $limit = min(max((int) $request->input('limit', 20), 1), 100);
        $type = $request->filled('type') ? $request->string('type')->toString() : null;

        $include = fn (string $section): bool => $type === null || $type === $section;

        $measurements = $include('measurements')
            ? $tree->measurements()->reorder('measured_at', $sortDir)->with(['recorder', 'photos'])->limit($limit)->get()
            : collect();

        $fertilizations = $include('fertilizations')
            ? $tree->fertilizationRecords()->reorder('activity_date', $sortDir)->with(['recorder', 'items.product', 'photos'])->limit($limit)->get()
            : collect();

        $sprayings = $include('sprayings')
            ? $tree->sprayingRecords()->reorder('activity_date', $sortDir)->with(['recorder', 'items.product', 'photos'])->limit($limit)->get()
            : collect();

        $flowerings = $include('flowerings')
            ? $tree->floweringRecords()->reorder('flowering_date', $sortDir)->with(['recorder', 'photos'])->limit($limit)->get()
            : collect();

        $fruitDevelopments = $include('fruit_developments')
            ? $tree->fruitDevelopmentRecords()->reorder('observation_date', $sortDir)->with(['recorder', 'photos'])->limit($limit)->get()
            : collect();

        $harvests = $include('harvests')
            ? HarvestRecord::query()
                ->select('harvest_records.*')
                ->join('harvest_sessions', 'harvest_records.session_id', '=', 'harvest_sessions.id')
                ->where('harvest_records.tree_id', $tree->id)
                ->with(['session', 'details'])
                ->orderBy('harvest_sessions.harvest_date', $sortDir)
                ->limit($limit)
                ->get()
            : collect();

        $fertilizationItems = $fertilizations->map(fn (FertilizationRecord $record) => [
            'id' => $record->id,
            'activity_date' => $record->activity_date?->toISOString(),
            'method' => $record->method,
            'dosage_notes' => $record->dosage_notes,
            'notes' => $record->notes,
            'recorder' => $record->recorder ? ['id' => $record->recorder->id, 'name' => $record->recorder->name] : null,
            'items' => $record->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'quantity' => $item->quantity !== null ? (float) $item->quantity : null,
                'unit' => $item->unit,
            ])->values(),
            'photos_count' => $record->photos->count(),
        ])->values();

        $sprayingItems = $sprayings->map(fn (SprayingRecord $record) => [
            'id' => $record->id,
            'activity_date' => $record->activity_date?->toISOString(),
            'spraying_method' => $record->spraying_method,
            'dosage_notes' => $record->dosage_notes,
            'notes' => $record->notes,
            'recorder' => $record->recorder ? ['id' => $record->recorder->id, 'name' => $record->recorder->name] : null,
            'items' => $record->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'quantity' => $item->quantity !== null ? (float) $item->quantity : null,
                'unit' => $item->unit,
            ])->values(),
            'photos_count' => $record->photos->count(),
        ])->values();

        $floweringItems = $flowerings->map(fn (FloweringRecord $record) => [
            'id' => $record->id,
            'flowering_date' => $record->flowering_date?->toDateString(),
            'flowering_status' => $record->flowering_status,
            'flower_count' => $record->flower_count,
            'notes' => $record->notes,
            'recorder' => $record->recorder ? ['id' => $record->recorder->id, 'name' => $record->recorder->name] : null,
            'photos_count' => $record->photos->count(),
        ])->values();

        $fruitItems = $fruitDevelopments->map(fn (FruitDevelopmentRecord $record) => [
            'id' => $record->id,
            'observation_date' => $record->observation_date?->toDateString(),
            'fruit_count' => $record->fruit_count,
            'development_stage' => $record->development_stage,
            'notes' => $record->notes,
            'recorder' => $record->recorder ? ['id' => $record->recorder->id, 'name' => $record->recorder->name] : null,
            'photos_count' => $record->photos->count(),
        ])->values();

        $harvestItems = $harvests->map(fn (HarvestRecord $record) => [
            'id' => $record->id,
            'session_id' => $record->session_id,
            'harvest_date' => $record->session?->harvest_date?->toISOString(),
            'session_name' => $record->session?->session_name,
            'fruit_count' => (int) $record->fruit_count,
            'total_weight_kg' => (float) $record->total_weight_kg,
            'notes' => $record->notes,
            'details_count' => $record->details->count(),
        ])->values();

        $timeline = collect()
            ->merge($measurements->map(fn ($m) => [
                'type' => 'measurement',
                'id' => $m->id,
                'occurred_at' => $m->measured_at?->toISOString(),
                'summary' => 'Pengukuran pohon',
            ]))
            ->merge($fertilizations->map(fn ($f) => [
                'type' => 'fertilization',
                'id' => $f->id,
                'occurred_at' => $f->activity_date?->toISOString(),
                'summary' => "Pemupukan ({$f->method})",
            ]))
            ->merge($sprayings->map(fn ($s) => [
                'type' => 'spraying',
                'id' => $s->id,
                'occurred_at' => $s->activity_date?->toISOString(),
                'summary' => 'Penyemprotan'.($s->spraying_method ? " ({$s->spraying_method})" : ''),
            ]))
            ->merge($flowerings->map(fn ($fl) => [
                'type' => 'flowering',
                'id' => $fl->id,
                'occurred_at' => $fl->flowering_date?->startOfDay()->toISOString(),
                'summary' => "Pembungaan ({$fl->flowering_status})",
            ]))
            ->merge($fruitDevelopments->map(fn ($fr) => [
                'type' => 'fruit_development',
                'id' => $fr->id,
                'occurred_at' => $fr->observation_date?->startOfDay()->toISOString(),
                'summary' => 'Perkembangan buah'.($fr->development_stage ? " ({$fr->development_stage})" : ''),
            ]))
            ->merge($harvests->map(fn ($h) => [
                'type' => 'harvest',
                'id' => $h->id,
                'occurred_at' => $h->session?->harvest_date?->toISOString() ?? $h->created_at?->toISOString(),
                'summary' => "Panen ({$h->fruit_count} buah, {$h->total_weight_kg} kg)",
            ]));

        $timeline = $sortDir === 'asc'
            ? $timeline->sortBy('occurred_at')->values()
            : $timeline->sortByDesc('occurred_at')->values();

        return $this->successResponse([
            'tree' => [
                'id' => $tree->id,
                'tree_code' => $tree->tree_code,
                'tree_name' => $tree->tree_name,
                'status' => $tree->status,
                'status_label' => $tree->statusLabel(),
                'variety' => $tree->variety?->name,
                'garden' => $tree->garden?->name,
            ],
            'measurements' => TreeMeasurementResource::collection($measurements),
            'fertilizations' => $fertilizationItems,
            'sprayings' => $sprayingItems,
            'flowerings' => $floweringItems,
            'fruit_developments' => $fruitItems,
            'harvests' => $harvestItems,
            'timeline' => $timeline,
        ], 'Riwayat pohon berhasil dimuat');
    }

    /**
     * Return available tree status options and labels (SRS 4.4).
     */
    public function statuses(): JsonResponse
    {
        $items = collect(Tree::STATUS_LABELS)->map(fn (string $label, string $value) => [
            'value' => $value,
            'label' => $label,
        ])->values();

        return $this->successResponse($items, 'Daftar status pohon berhasil dimuat');
    }
}
