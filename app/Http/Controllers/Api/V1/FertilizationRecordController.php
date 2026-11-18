<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FertilizationRecordRequest;
use App\Http\Resources\Api\V1\FertilizationRecordResource;
use App\Models\AgriculturalProduct;
use App\Models\FertilizationItem;
use App\Models\FertilizationRecord;
use App\Models\Tree;
use App\Services\PhotoStorageService;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FertilizationRecordController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TerritoryScopeService $territoryScope,
        protected PhotoStorageService $photoStorage,
    ) {}

    /**
     * Display a listing of fertilization records scoped to user's territory access.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FertilizationRecord::query()
            ->with(['tree.garden', 'recorder', 'items.product.category', 'photos']);

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

        if ($request->filled('method')) {
            $query->where('method', $request->string('method')->toString());
        }

        if ($request->filled('product_id')) {
            $productId = $request->string('product_id')->toString();
            $query->whereHas('items', fn (Builder $q) => $q->where('product_id', $productId));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('activity_date', '>=', $request->string('from_date')->toString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('activity_date', '<=', $request->string('to_date')->toString());
        }

        $sortDir = strtolower($request->string('sort_dir', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $records = $query->orderBy('activity_date', $sortDir)->paginate($perPage);

        return $this->successResponse([
            'items' => FertilizationRecordResource::collection($records->items()),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ], 'Riwayat pemupukan berhasil dimuat');
    }

    /**
     * Store a new fertilization record with multiple product items and optional photos.
     */
    public function store(FertilizationRecordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tree = Tree::findOrFail($data['tree_id']);

        if (! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk mencatat pemupukan pada pohon ini', 403);
        }

        $productIds = collect($data['items'])->pluck('product_id')->unique()->values()->all();
        $products = AgriculturalProduct::query()->whereIn('id', $productIds)->get()->keyBy('id');

        $inactiveProduct = $products->first(fn (AgriculturalProduct $product) => ! $product->is_active);
        if ($inactiveProduct) {
            return $this->errorResponse(
                "Produk \"{$inactiveProduct->name}\" tidak aktif dan tidak dapat digunakan untuk pencatatan baru",
                422
            );
        }

        $record = DB::transaction(function () use ($data, $products, $request) {
            $record = FertilizationRecord::create([
                'tree_id' => $data['tree_id'],
                'activity_date' => $data['activity_date'] ?? now(),
                'method' => $data['method'] ?? 'tabur',
                'dosage_notes' => $data['dosage_notes'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                FertilizationItem::create([
                    'fertilization_record_id' => $record->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'] ?? $product?->unit,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            foreach ($request->file('photos', []) as $file) {
                $this->photoStorage->storeActivityPhoto(
                    $file,
                    'fertilization',
                    $record->id,
                    $request->user()->id
                );
            }

            return $record->load(['tree.garden', 'recorder', 'items.product.category', 'photos']);
        });

        return $this->successResponse(
            new FertilizationRecordResource($record),
            'Aktivitas pemupukan berhasil dicatat',
            201
        );
    }

    /**
     * Display the specified fertilization record.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $record = FertilizationRecord::query()
            ->with(['tree.garden', 'recorder', 'items.product.category', 'photos'])
            ->find($id);

        if (! $record) {
            return $this->errorResponse('Data pemupukan tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $record->tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke data pemupukan ini', 403);
        }

        return $this->successResponse(
            new FertilizationRecordResource($record),
            'Detail pemupukan berhasil dimuat'
        );
    }

    /**
     * Return available fertilization methods.
     */
    public function methods(): JsonResponse
    {
        $items = collect((array) config('durian.activities.fertilization_methods', []))
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values();

        return $this->successResponse($items, 'Daftar metode pemupukan berhasil dimuat');
    }
}
