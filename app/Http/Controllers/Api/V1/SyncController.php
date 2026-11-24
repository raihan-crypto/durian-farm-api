<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncPullRequest;
use App\Http\Requests\Api\V1\SyncPushRequest;
use App\Models\SyncLog;
use App\Services\SyncEngineService;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SyncEngineService $syncEngine,
        protected TerritoryScopeService $territoryScope,
    ) {}

    /**
     * Initial offline-first dataset snapshot for mobile app (SRS 12.2 & 16.3).
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $snapshot = $this->syncEngine->bootstrap($request->user());

        return $this->successResponse($snapshot, 'Data bootstrap sinkronisasi berhasil dimuat');
    }

    /**
     * Incremental server changes since timestamp (GET /sync/changes & POST /sync/pull).
     */
    public function changes(SyncPullRequest $request): JsonResponse
    {
        $data = $request->validated();

        $delta = $this->syncEngine->pullChanges(
            $request->user(),
            $data['since'] ?? null,
            $data['entities'] ?? null,
            (int) ($data['limit'] ?? 200),
            $data['cursor'] ?? null
        );

        return $this->successResponse($delta, 'Perubahan data sinkronisasi berhasil dimuat');
    }

    /**
     * Push offline queued operations from mobile client with idempotency & conflict reporting.
     */
    public function push(SyncPushRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->syncEngine->pushBatch(
            $request->user(),
            $data['operations'],
            $data['device_identifier'] ?? null
        );

        return $this->successResponse($result, 'Proses sinkronisasi batch selesai');
    }

    /**
     * Inspect sync log history (scoped to self for field workers; global for admins).
     */
    public function logs(Request $request): JsonResponse
    {
        $query = SyncLog::query()->with('user:id,name,email');

        if ($this->territoryScope->isScoped($request->user())) {
            $query->where('user_id', $request->user()->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->string('user_id')->toString());
        }

        if ($request->filled('sync_status')) {
            $query->where('sync_status', $request->string('sync_status')->toString());
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->string('entity_type')->toString());
        }

        if ($request->filled('device_identifier')) {
            $query->where('device_identifier', $request->string('device_identifier')->toString());
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $logs = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->successResponse([
            'items' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ], 'Riwayat log sinkronisasi berhasil dimuat');
    }
}
