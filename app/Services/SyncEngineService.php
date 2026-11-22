<?php

namespace App\Services;

use App\Models\AgriculturalProduct;
use App\Models\Block;
use App\Models\DurianVariety;
use App\Models\FertilizationItem;
use App\Models\FertilizationRecord;
use App\Models\FloweringRecord;
use App\Models\FruitDevelopmentRecord;
use App\Models\Garden;
use App\Models\HarvestDetail;
use App\Models\HarvestRecord;
use App\Models\HarvestSeason;
use App\Models\HarvestSession;
use App\Models\ProductCategory;
use App\Models\SprayingItem;
use App\Models\SprayingRecord;
use App\Models\SyncLog;
use App\Models\Tree;
use App\Models\TreeMeasurement;
use App\Models\User;
use App\Models\Village;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SyncEngineService
{
    /**
     * Supported entity types for offline push from mobile clients.
     *
     * @var array<int, string>
     */
    public const PUSHABLE_ENTITIES = [
        'tree_measurement',
        'fertilization',
        'spraying',
        'flowering',
        'fruit_development',
        'harvest_session',
        'harvest_record',
    ];

    /**
     * Supported entity collections for delta pull / changes.
     *
     * @var array<int, string>
     */
    public const PULLABLE_ENTITIES = [
        'villages',
        'blocks',
        'gardens',
        'durian_varieties',
        'product_categories',
        'agricultural_products',
        'trees',
        'harvest_seasons',
        'harvest_sessions',
        'tree_measurements',
        'fertilization_records',
        'spraying_records',
        'flowering_records',
        'fruit_development_records',
        'harvest_records',
    ];

    /**
     * Required permission per pushed entity type.
     *
     * @var array<string, string>
     */
    private const ENTITY_PERMISSIONS = [
        'tree_measurement' => 'trees.measure',
        'fertilization' => 'activities.record',
        'spraying' => 'activities.record',
        'flowering' => 'activities.record',
        'fruit_development' => 'activities.record',
        'harvest_session' => 'harvest.record',
        'harvest_record' => 'harvest.record',
    ];

    public function __construct(protected TerritoryScopeService $territoryScope) {}

    /**
     * Build initial offline-first snapshot for mobile SQLite storage (SRS 12.2).
     *
     * @return array<string, mixed>
     */
    public function bootstrap(User $user): array
    {
        $serverTime = now()->toISOString();
        $accessibleVillageIds = $this->territoryScope->getAccessibleVillageIds($user);
        $accessibleBlockIds = $this->territoryScope->getAccessibleBlockIds($user);
        $accessibleGardenIds = $this->territoryScope->getAccessibleGardenIds($user);

        $villages = Village::query()
            ->when($accessibleVillageIds !== null, fn ($q) => $q->whereIn('id', $accessibleVillageIds))
            ->orderBy('name')
            ->get(['id', 'name', 'district', 'regency', 'province', 'updated_at']);

        $blocks = Block::query()
            ->when($accessibleBlockIds !== null, fn ($q) => $q->whereIn('id', $accessibleBlockIds))
            ->orderBy('name')
            ->get(['id', 'village_id', 'name', 'area_hectares', 'description', 'updated_at']);

        $gardens = Garden::query()
            ->when($accessibleGardenIds !== null, fn ($q) => $q->whereIn('id', $accessibleGardenIds))
            ->orderBy('name')
            ->get(['id', 'block_id', 'name', 'area_hectares', 'address', 'latitude', 'longitude', 'description', 'updated_at']);

        $varieties = Cache::remember('durian:master:varieties:active', 3600, fn () => DurianVariety::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'origin', 'description', 'characteristics', 'is_active', 'updated_at']));

        $categories = Cache::remember('durian:master:categories:active', 3600, fn () => ProductCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'is_active', 'updated_at']));

        $products = Cache::remember('durian:master:products:active', 3600, fn () => AgriculturalProduct::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'category_id', 'name', 'brand', 'active_ingredient', 'unit', 'description', 'is_active', 'updated_at']));

        $trees = Tree::query()
            ->with('qrCode:id,tree_id,public_token,qr_code,is_active')
            ->when($accessibleGardenIds !== null, fn ($q) => $q->whereIn('garden_id', $accessibleGardenIds))
            ->orderBy('tree_code')
            ->get([
                'id',
                'garden_id',
                'variety_id',
                'tree_code',
                'tree_name',
                'planted_at',
                'planting_age_months',
                'latitude',
                'longitude',
                'status',
                'notes',
                'updated_at',
            ])
            ->map(fn (Tree $tree) => [
                'id' => $tree->id,
                'garden_id' => $tree->garden_id,
                'variety_id' => $tree->variety_id,
                'tree_code' => $tree->tree_code,
                'tree_name' => $tree->tree_name,
                'planted_at' => $tree->planted_at?->toDateString(),
                'planting_age_months' => $tree->planting_age_months,
                'latitude' => $tree->latitude !== null ? (float) $tree->latitude : null,
                'longitude' => $tree->longitude !== null ? (float) $tree->longitude : null,
                'status' => $tree->status,
                'notes' => $tree->notes,
                'qr_token' => $tree->qrCode?->is_active ? $tree->qrCode->public_token : null,
                'updated_at' => $tree->updated_at?->toISOString(),
            ])
            ->values();

        $seasons = HarvestSeason::query()
            ->when($accessibleGardenIds !== null, fn ($q) => $q->whereIn('garden_id', $accessibleGardenIds))
            ->whereIn('status', ['planned', 'active'])
            ->orderByDesc('start_date')
            ->get(['id', 'garden_id', 'name', 'start_date', 'end_date', 'status', 'notes', 'updated_at']);

        $sessions = HarvestSession::query()
            ->when($accessibleGardenIds !== null, fn ($q) => $q->whereIn('garden_id', $accessibleGardenIds))
            ->orderByDesc('harvest_date')
            ->limit(200)
            ->get(['id', 'season_id', 'garden_id', 'harvest_date', 'session_name', 'weather_condition', 'notes', 'updated_at']);

        $user->loadMissing(['roles', 'farmerProfile']);

        return [
            'server_time' => $serverTime,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->values()->all(),
                'farmer' => $user->farmerProfile ? [
                    'id' => $user->farmerProfile->id,
                    'name' => $user->farmerProfile->name,
                    'phone' => $user->farmerProfile->phone,
                ] : null,
            ],
            'villages' => $villages,
            'blocks' => $blocks,
            'gardens' => $gardens,
            'durian_varieties' => $varieties,
            'product_categories' => $categories,
            'agricultural_products' => $products,
            'trees' => $trees,
            'harvest_seasons' => $seasons,
            'harvest_sessions' => $sessions,
            'reference_options' => [
                'tree_statuses' => Tree::STATUS_LABELS,
                'fertilization_methods' => config('durian.activities.fertilization_methods', []),
                'flowering_statuses' => config('durian.activities.flowering_statuses', []),
                'fruit_development_stages' => config('durian.activities.fruit_development_stages', []),
            ],
        ];
    }

    /**
     * Return incremental server changes updated/created after $since.
     *
     * @param  array<int, string>|null  $entities
     * @return array<string, mixed>
     */
    public function pullChanges(User $user, ?string $since = null, ?array $entities = null, int $limit = 200): array
    {
        $serverTime = now()->toISOString();
        $sinceCarbon = $since ? Carbon::parse($since) : null;
        $requested = empty($entities) ? self::PULLABLE_ENTITIES : array_values(array_intersect(self::PULLABLE_ENTITIES, $entities));

        $accessibleVillageIds = $this->territoryScope->getAccessibleVillageIds($user);
        $accessibleBlockIds = $this->territoryScope->getAccessibleBlockIds($user);
        $accessibleGardenIds = $this->territoryScope->getAccessibleGardenIds($user);

        $changes = [];

        foreach ($requested as $entity) {
            $changes[$entity] = match ($entity) {
                'villages' => Village::query()
                    ->when($accessibleVillageIds !== null, fn ($q) => $q->whereIn('id', $accessibleVillageIds))
                    ->when($sinceCarbon, fn ($q) => $q->where('updated_at', '>', $sinceCarbon))
                    ->orderBy('updated_at')
                    ->limit($limit)
                    ->get(),
                'blocks' => Block::query()
                    ->when($accessibleBlockIds !== null, fn ($q) => $q->whereIn('id', $accessibleBlockIds))
                    ->when($sinceCarbon, fn ($q) => $q->where('updated_at', '>', $sinceCarbon))
                    ->orderBy('updated_at')
                    ->limit($limit)
                    ->get(),
                'gardens' => Garden::query()
                    ->when($accessibleGardenIds !== null, fn ($q) => $q->whereIn('id', $accessibleGardenIds))
                    ->when($sinceCarbon, fn ($q) => $q->where('updated_at', '>', $sinceCarbon))
                    ->orderBy('updated_at')
                    ->limit($limit)
                    ->get(),
                'durian_varieties' => DurianVariety::query()
                    ->when($sinceCarbon, fn ($q) => $q->where('updated_at', '>', $sinceCarbon))
                    ->orderBy('updated_at')
                    ->limit($limit)
                    ->get(),
                'product_categories' => ProductCategory::query()
                    ->when($sinceCarbon, fn ($q) => $q->where('updated_at', '>', $sinceCarbon))
                    ->orderBy('updated_at')
                    ->limit($limit)
                    ->get(),
                'agricultural_products' => AgriculturalProduct::query()
                    ->when($sinceCarbon, fn ($q) => $q->where('updated_at', '>', $sinceCarbon))
                    ->orderBy('updated_at')
                    ->limit($limit)
                    ->get(),
                'trees' => Tree::query()
                    ->with('qrCode:id,tree_id,public_token,is_active')
                    ->when($accessibleGardenIds !== null, fn ($q) => $q->whereIn('garden_id', $accessibleGardenIds))
                    ->when($sinceCarbon, fn ($q) => $q->where('updated_at', '>', $sinceCarbon))
                    ->orderBy('updated_at')
                    ->limit($limit)
                    ->get(),
                'harvest_seasons' => HarvestSeason::query()
                    ->when($accessibleGardenIds !== null, fn ($q) => $q->whereIn('garden_id', $accessibleGardenIds))
                    ->when($sinceCarbon, fn ($q) => $q->where('updated_at', '>', $sinceCarbon))
                    ->orderBy('updated_at')
                    ->limit($limit)
                    ->get(),
                'harvest_sessions' => HarvestSession::query()
                    ->when($accessibleGardenIds !== null, fn ($q) => $q->whereIn('garden_id', $accessibleGardenIds))
                    ->when($sinceCarbon, fn ($q) => $q->where('updated_at', '>', $sinceCarbon))
                    ->orderBy('updated_at')
                    ->limit($limit)
                    ->get(),
                'tree_measurements' => TreeMeasurement::query()
                    ->when($accessibleGardenIds !== null, fn ($q) => $q->whereHas('tree', fn ($t) => $t->whereIn('garden_id', $accessibleGardenIds)))
                    ->when($sinceCarbon, fn ($q) => $q->where('created_at', '>', $sinceCarbon))
                    ->orderBy('created_at')
                    ->limit($limit)
                    ->get(),
                'fertilization_records' => FertilizationRecord::query()
                    ->with('items')
                    ->when($accessibleGardenIds !== null, fn ($q) => $q->whereHas('tree', fn ($t) => $t->whereIn('garden_id', $accessibleGardenIds)))
                    ->when($sinceCarbon, fn ($q) => $q->where('updated_at', '>', $sinceCarbon))
                    ->orderBy('updated_at')
                    ->limit($limit)
                    ->get(),
                'spraying_records' => SprayingRecord::query()
                    ->with('items')
                    ->when($accessibleGardenIds !== null, fn ($q) => $q->whereHas('tree', fn ($t) => $t->whereIn('garden_id', $accessibleGardenIds)))
                    ->when($sinceCarbon, fn ($q) => $q->where('updated_at', '>', $sinceCarbon))
                    ->orderBy('updated_at')
                    ->limit($limit)
                    ->get(),
                'flowering_records' => FloweringRecord::query()
                    ->when($accessibleGardenIds !== null, fn ($q) => $q->whereHas('tree', fn ($t) => $t->whereIn('garden_id', $accessibleGardenIds)))
                    ->when($sinceCarbon, fn ($q) => $q->where('created_at', '>', $sinceCarbon))
                    ->orderBy('created_at')
                    ->limit($limit)
                    ->get(),
                'fruit_development_records' => FruitDevelopmentRecord::query()
                    ->when($accessibleGardenIds !== null, fn ($q) => $q->whereHas('tree', fn ($t) => $t->whereIn('garden_id', $accessibleGardenIds)))
                    ->when($sinceCarbon, fn ($q) => $q->where('created_at', '>', $sinceCarbon))
                    ->orderBy('created_at')
                    ->limit($limit)
                    ->get(),
                'harvest_records' => HarvestRecord::query()
                    ->with('details')
                    ->when($accessibleGardenIds !== null, fn ($q) => $q->whereIn('garden_id', $accessibleGardenIds))
                    ->when($sinceCarbon, fn ($q) => $q->where('updated_at', '>', $sinceCarbon))
                    ->orderBy('updated_at')
                    ->limit($limit)
                    ->get(),
                default => collect(),
            };
        }

        return [
            'server_time' => $serverTime,
            'since' => $sinceCarbon?->toISOString(),
            'changes' => $changes,
        ];
    }

    /**
     * Process a batch of offline client operations with idempotency and conflict detection (SRS 12.4-12.6).
     *
     * @param  array<int, array{client_transaction_id: string, entity_type: string, entity_id: string, payload: array<string, mixed>}>  $operations
     * @return array{server_time: string, results: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function pushBatch(User $user, array $operations, ?string $deviceIdentifier = null): array
    {
        $user->loadMissing('roles.permissions');

        $results = [];
        $summary = [
            'total' => count($operations),
            'synced' => 0,
            'duplicates' => 0,
            'conflicts' => 0,
            'failed' => 0,
        ];

        foreach ($operations as $operation) {
            $result = $this->processOperation($user, $operation, $deviceIdentifier);
            $results[] = $result;

            if ($result['status'] === 'synced') {
                if (! empty($result['duplicate'])) {
                    $summary['duplicates']++;
                } else {
                    $summary['synced']++;
                }
            } elseif ($result['status'] === 'conflict') {
                $summary['conflicts']++;
            } else {
                $summary['failed']++;
            }
        }

        return [
            'server_time' => now()->toISOString(),
            'results' => $results,
            'summary' => $summary,
        ];
    }

    /**
     * Process a single offline operation safely inside its own DB transaction.
     *
     * @param  array{client_transaction_id: string, entity_type: string, entity_id: string, payload: array<string, mixed>}  $operation
     * @return array<string, mixed>
     */
    protected function processOperation(User $user, array $operation, ?string $deviceIdentifier): array
    {
        $txId = $operation['client_transaction_id'];
        $entityType = $operation['entity_type'];
        $entityId = $operation['entity_id'];
        $payload = $operation['payload'];

        // 1. Idempotency check on client_transaction_id (SRS 12.5)
        $existingLog = SyncLog::query()->where('client_transaction_id', $txId)->first();
        if ($existingLog && $existingLog->sync_status === 'success') {
            return [
                'client_transaction_id' => $txId,
                'entity_type' => $entityType,
                'entity_id' => $existingLog->entity_id,
                'status' => 'synced',
                'duplicate' => true,
                'message' => 'Transaksi sudah pernah disinkronkan sebelumnya',
            ];
        }

        // 2. Permission check per entity type
        $requiredPermission = self::ENTITY_PERMISSIONS[$entityType] ?? null;
        if ($requiredPermission && ! $user->hasRole('super_admin') && ! $user->hasPermission($requiredPermission)) {
            $message = "Pengguna tidak memiliki izin ({$requiredPermission}) untuk entitas {$entityType}";
            $this->recordLog($existingLog, $user->id, $deviceIdentifier, $txId, $entityType, $entityId, 'failed', $message);

            return [
                'client_transaction_id' => $txId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'status' => 'failed',
                'message' => $message,
            ];
        }

        // 3. Execute entity-specific validation, conflict detection, and persistence
        $outcome = match ($entityType) {
            'tree_measurement' => $this->syncTreeMeasurement($user, $entityId, $payload),
            'fertilization' => $this->syncFertilization($user, $entityId, $payload),
            'spraying' => $this->syncSpraying($user, $entityId, $payload),
            'flowering' => $this->syncFlowering($user, $entityId, $payload),
            'fruit_development' => $this->syncFruitDevelopment($user, $entityId, $payload),
            'harvest_session' => $this->syncHarvestSession($user, $entityId, $payload),
            'harvest_record' => $this->syncHarvestRecord($user, $entityId, $payload),
            default => ['status' => 'failed', 'message' => 'Jenis entitas tidak didukung'],
        };

        $logStatus = match ($outcome['status']) {
            'synced' => 'success',
            'conflict' => 'conflict',
            default => 'failed',
        };

        $this->recordLog(
            $existingLog,
            $user->id,
            $deviceIdentifier,
            $txId,
            $entityType,
            $entityId,
            $logStatus,
            $outcome['status'] === 'synced' ? null : ($outcome['message'] ?? null)
        );

        return array_merge([
            'client_transaction_id' => $txId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ], $outcome);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function syncTreeMeasurement(User $user, string $entityId, array $payload): array
    {
        $existing = TreeMeasurement::find($entityId);
        if ($existing) {
            return $this->conflictResponse('Data pengukuran dengan ID ini sudah tersimpan di server', $existing->toArray());
        }

        $validator = Validator::make($payload, [
            'tree_id' => ['required', 'uuid', 'exists:trees,id'],
            'measured_at' => ['nullable', 'date'],
            'diameter_cm' => ['nullable', 'numeric', 'gt:0', 'required_without_all:height_m,canopy_width_m'],
            'height_m' => ['nullable', 'numeric', 'gt:0', 'required_without_all:diameter_cm,canopy_width_m'],
            'canopy_width_m' => ['nullable', 'numeric', 'gt:0', 'required_without_all:diameter_cm,height_m'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return ['status' => 'failed', 'message' => $validator->errors()->first(), 'errors' => $validator->errors()->toArray()];
        }

        $data = $validator->validated();
        $tree = Tree::findOrFail($data['tree_id']);

        if (! $this->territoryScope->canAccessGarden($user, $tree->garden_id)) {
            return ['status' => 'failed', 'message' => 'Anda tidak memiliki akses ke kebun pohon ini'];
        }

        DB::transaction(function () use ($entityId, $data, $user) {
            $measurement = new TreeMeasurement([
                'tree_id' => $data['tree_id'],
                'measured_at' => $data['measured_at'] ?? now(),
                'diameter_cm' => $data['diameter_cm'] ?? null,
                'height_m' => $data['height_m'] ?? null,
                'canopy_width_m' => $data['canopy_width_m'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $user->id,
            ]);
            $measurement->id = $entityId;
            $measurement->save();
        });

        return ['status' => 'synced', 'duplicate' => false, 'message' => 'Pengukuran pohon berhasil disinkronkan'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function syncFertilization(User $user, string $entityId, array $payload): array
    {
        $existing = FertilizationRecord::find($entityId);
        if ($existing) {
            return $this->conflictResponse('Data pemupukan dengan ID ini sudah tersimpan di server', $existing->toArray());
        }

        $methods = array_keys((array) config('durian.activities.fertilization_methods', []));
        $validator = Validator::make($payload, [
            'tree_id' => ['required', 'uuid', 'exists:trees,id'],
            'activity_date' => ['nullable', 'date'],
            'method' => ['sometimes', 'required', Rule::in($methods)],
            'dosage_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:agricultural_products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return ['status' => 'failed', 'message' => $validator->errors()->first(), 'errors' => $validator->errors()->toArray()];
        }

        $data = $validator->validated();
        $tree = Tree::findOrFail($data['tree_id']);

        if (! $this->territoryScope->canAccessGarden($user, $tree->garden_id)) {
            return ['status' => 'failed', 'message' => 'Anda tidak memiliki akses ke kebun pohon ini'];
        }

        $productIds = collect($data['items'])->pluck('product_id')->unique()->values()->all();
        $products = AgriculturalProduct::query()->whereIn('id', $productIds)->get()->keyBy('id');

        DB::transaction(function () use ($entityId, $data, $products, $user) {
            $record = new FertilizationRecord([
                'tree_id' => $data['tree_id'],
                'activity_date' => $data['activity_date'] ?? now(),
                'method' => $data['method'] ?? 'tabur',
                'dosage_notes' => $data['dosage_notes'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $user->id,
            ]);
            $record->id = $entityId;
            $record->save();

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
        });

        return ['status' => 'synced', 'duplicate' => false, 'message' => 'Pemupukan berhasil disinkronkan'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function syncSpraying(User $user, string $entityId, array $payload): array
    {
        $existing = SprayingRecord::find($entityId);
        if ($existing) {
            return $this->conflictResponse('Data penyemprotan dengan ID ini sudah tersimpan di server', $existing->toArray());
        }

        $validator = Validator::make($payload, [
            'tree_id' => ['required', 'uuid', 'exists:trees,id'],
            'activity_date' => ['nullable', 'date'],
            'spraying_method' => ['nullable', 'string', 'max:100'],
            'dosage_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:agricultural_products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return ['status' => 'failed', 'message' => $validator->errors()->first(), 'errors' => $validator->errors()->toArray()];
        }

        $data = $validator->validated();
        $tree = Tree::findOrFail($data['tree_id']);

        if (! $this->territoryScope->canAccessGarden($user, $tree->garden_id)) {
            return ['status' => 'failed', 'message' => 'Anda tidak memiliki akses ke kebun pohon ini'];
        }

        $productIds = collect($data['items'])->pluck('product_id')->unique()->values()->all();
        $products = AgriculturalProduct::query()->whereIn('id', $productIds)->get()->keyBy('id');

        DB::transaction(function () use ($entityId, $data, $products, $user) {
            $record = new SprayingRecord([
                'tree_id' => $data['tree_id'],
                'activity_date' => $data['activity_date'] ?? now(),
                'spraying_method' => $data['spraying_method'] ?? null,
                'dosage_notes' => $data['dosage_notes'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $user->id,
            ]);
            $record->id = $entityId;
            $record->save();

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                SprayingItem::create([
                    'spraying_record_id' => $record->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'] ?? $product?->unit,
                    'notes' => $item['notes'] ?? null,
                ]);
            }
        });

        return ['status' => 'synced', 'duplicate' => false, 'message' => 'Penyemprotan berhasil disinkronkan'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function syncFlowering(User $user, string $entityId, array $payload): array
    {
        $existing = FloweringRecord::find($entityId);
        if ($existing) {
            return $this->conflictResponse('Data pembungaan dengan ID ini sudah tersimpan di server', $existing->toArray());
        }

        $statuses = array_keys((array) config('durian.activities.flowering_statuses', []));
        $validator = Validator::make($payload, [
            'tree_id' => ['required', 'uuid', 'exists:trees,id'],
            'flowering_date' => ['nullable', 'date'],
            'flowering_status' => ['required', Rule::in($statuses)],
            'flower_count' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return ['status' => 'failed', 'message' => $validator->errors()->first(), 'errors' => $validator->errors()->toArray()];
        }

        $data = $validator->validated();
        $tree = Tree::findOrFail($data['tree_id']);

        if (! $this->territoryScope->canAccessGarden($user, $tree->garden_id)) {
            return ['status' => 'failed', 'message' => 'Anda tidak memiliki akses ke kebun pohon ini'];
        }

        DB::transaction(function () use ($entityId, $data, $user) {
            $record = new FloweringRecord([
                'tree_id' => $data['tree_id'],
                'flowering_date' => $data['flowering_date'] ?? Carbon::today()->toDateString(),
                'flowering_status' => $data['flowering_status'],
                'flower_count' => $data['flower_count'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $user->id,
            ]);
            $record->id = $entityId;
            $record->save();
        });

        return ['status' => 'synced', 'duplicate' => false, 'message' => 'Pembungaan berhasil disinkronkan'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function syncFruitDevelopment(User $user, string $entityId, array $payload): array
    {
        $existing = FruitDevelopmentRecord::find($entityId);
        if ($existing) {
            return $this->conflictResponse('Data perkembangan buah dengan ID ini sudah tersimpan di server', $existing->toArray());
        }

        $stages = array_keys((array) config('durian.activities.fruit_development_stages', []));
        $validator = Validator::make($payload, [
            'tree_id' => ['required', 'uuid', 'exists:trees,id'],
            'observation_date' => ['nullable', 'date'],
            'fruit_count' => ['nullable', 'integer', 'min:0'],
            'development_stage' => ['required', Rule::in($stages)],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return ['status' => 'failed', 'message' => $validator->errors()->first(), 'errors' => $validator->errors()->toArray()];
        }

        $data = $validator->validated();
        $tree = Tree::findOrFail($data['tree_id']);

        if (! $this->territoryScope->canAccessGarden($user, $tree->garden_id)) {
            return ['status' => 'failed', 'message' => 'Anda tidak memiliki akses ke kebun pohon ini'];
        }

        DB::transaction(function () use ($entityId, $data, $user) {
            $record = new FruitDevelopmentRecord([
                'tree_id' => $data['tree_id'],
                'observation_date' => $data['observation_date'] ?? Carbon::today()->toDateString(),
                'fruit_count' => $data['fruit_count'] ?? null,
                'development_stage' => $data['development_stage'],
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $user->id,
            ]);
            $record->id = $entityId;
            $record->save();
        });

        return ['status' => 'synced', 'duplicate' => false, 'message' => 'Perkembangan buah berhasil disinkronkan'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function syncHarvestSession(User $user, string $entityId, array $payload): array
    {
        $existing = HarvestSession::find($entityId);
        if ($existing) {
            return $this->conflictResponse('Sesi panen dengan ID ini sudah tersimpan di server', $existing->toArray());
        }

        $validator = Validator::make($payload, [
            'season_id' => ['required', 'uuid', 'exists:harvest_seasons,id'],
            'garden_id' => ['nullable', 'uuid', 'exists:gardens,id'],
            'harvest_date' => ['required', 'date'],
            'session_name' => ['nullable', 'string', 'max:150'],
            'weather_condition' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return ['status' => 'failed', 'message' => $validator->errors()->first(), 'errors' => $validator->errors()->toArray()];
        }

        $data = $validator->validated();
        $season = HarvestSeason::findOrFail($data['season_id']);

        if (! empty($data['garden_id']) && $data['garden_id'] !== $season->garden_id) {
            return ['status' => 'failed', 'message' => 'Kebun sesi panen tidak sesuai dengan musim panen'];
        }

        if (! $this->territoryScope->canAccessGarden($user, $season->garden_id)) {
            return ['status' => 'failed', 'message' => 'Anda tidak memiliki akses ke kebun ini'];
        }

        DB::transaction(function () use ($entityId, $season, $data, $user) {
            $session = new HarvestSession([
                'season_id' => $season->id,
                'garden_id' => $season->garden_id,
                'harvest_date' => $data['harvest_date'],
                'session_name' => $data['session_name'] ?? null,
                'weather_condition' => $data['weather_condition'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $user->id,
            ]);
            $session->id = $entityId;
            $session->save();
        });

        return ['status' => 'synced', 'duplicate' => false, 'message' => 'Sesi panen berhasil disinkronkan'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function syncHarvestRecord(User $user, string $entityId, array $payload): array
    {
        $existing = HarvestRecord::find($entityId);
        if ($existing) {
            return $this->conflictResponse('Hasil panen dengan ID ini sudah tersimpan di server', $existing->toArray());
        }

        $validator = Validator::make($payload, [
            'session_id' => ['required', 'uuid', 'exists:harvest_sessions,id'],
            'tree_id' => ['required', 'uuid', 'exists:trees,id'],
            'fruit_count' => ['nullable', 'integer', 'min:0', 'required_without:details'],
            'total_weight_kg' => ['nullable', 'numeric', 'min:0', 'required_without:details'],
            'notes' => ['nullable', 'string'],
            'details' => ['nullable', 'array'],
            'details.*.fruit_grade' => ['nullable', 'string', 'max:50'],
            'details.*.fruit_count' => ['required_with:details', 'integer', 'min:0'],
            'details.*.weight_kg' => ['required_with:details', 'numeric', 'min:0'],
            'details.*.notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return ['status' => 'failed', 'message' => $validator->errors()->first(), 'errors' => $validator->errors()->toArray()];
        }

        $data = $validator->validated();
        $session = HarvestSession::findOrFail($data['session_id']);
        $tree = Tree::findOrFail($data['tree_id']);

        if ($tree->garden_id !== $session->garden_id) {
            return ['status' => 'failed', 'message' => 'Pohon tidak berada dalam kebun yang sama dengan sesi panen'];
        }

        if (! $this->territoryScope->canAccessGarden($user, $session->garden_id)) {
            return ['status' => 'failed', 'message' => 'Anda tidak memiliki akses ke kebun ini'];
        }

        // Conflict when another record already exists for the same (session_id, tree_id)
        $conflicting = HarvestRecord::query()
            ->where('session_id', $session->id)
            ->where('tree_id', $tree->id)
            ->first();

        if ($conflicting) {
            return $this->conflictResponse(
                'Konflik: hasil panen untuk pohon dan sesi panen ini sudah dicatat sebelumnya',
                $conflicting->toArray()
            );
        }

        $details = $data['details'] ?? [];
        $fruitCount = isset($data['fruit_count']) ? (int) $data['fruit_count'] : (int) collect($details)->sum('fruit_count');
        $totalWeight = isset($data['total_weight_kg']) ? round((float) $data['total_weight_kg'], 3) : round((float) collect($details)->sum('weight_kg'), 3);

        DB::transaction(function () use ($entityId, $session, $tree, $fruitCount, $totalWeight, $data, $details) {
            $record = new HarvestRecord([
                'session_id' => $session->id,
                'garden_id' => $session->garden_id,
                'tree_id' => $tree->id,
                'fruit_count' => $fruitCount,
                'total_weight_kg' => $totalWeight,
                'notes' => $data['notes'] ?? null,
            ]);
            $record->id = $entityId;
            $record->save();

            foreach ($details as $detail) {
                HarvestDetail::create([
                    'harvest_record_id' => $record->id,
                    'fruit_grade' => $detail['fruit_grade'] ?? null,
                    'fruit_count' => (int) $detail['fruit_count'],
                    'weight_kg' => (float) $detail['weight_kg'],
                    'notes' => $detail['notes'] ?? null,
                ]);
            }
        });

        return ['status' => 'synced', 'duplicate' => false, 'message' => 'Hasil panen berhasil disinkronkan'];
    }

    /**
     * Record or update the SyncLog entry for auditability and idempotency.
     */
    protected function recordLog(
        ?SyncLog $existingLog,
        string $userId,
        ?string $deviceIdentifier,
        string $txId,
        string $entityType,
        string $entityId,
        string $status,
        ?string $errorMessage
    ): void {
        $attributes = [
            'user_id' => $userId,
            'device_identifier' => $deviceIdentifier,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'sync_status' => $status,
            'error_message' => $errorMessage,
            'synced_at' => $status === 'success' ? now() : null,
        ];

        if ($existingLog) {
            $existingLog->update($attributes);
        } else {
            SyncLog::create(array_merge(['client_transaction_id' => $txId], $attributes));
        }
    }

    /**
     * @param  array<string, mixed>  $serverRecord
     * @return array<string, mixed>
     */
    protected function conflictResponse(string $message, array $serverRecord): array
    {
        return [
            'status' => 'conflict',
            'message' => $message,
            'server_record' => $serverRecord,
        ];
    }
}
