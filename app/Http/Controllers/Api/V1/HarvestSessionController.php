<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HarvestSessionRequest;
use App\Http\Resources\Api\V1\HarvestSessionResource;
use App\Models\HarvestSeason;
use App\Models\HarvestSession;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HarvestSessionController extends Controller
{
    use ApiResponse;

    public function __construct(protected TerritoryScopeService $territoryScope) {}

    /**
     * Display a listing of harvest sessions scoped to user's accessible gardens.
     */
    public function index(Request $request): JsonResponse
    {
        $query = HarvestSession::query()
            ->with(['season', 'garden', 'recorder'])
            ->withCount('records')
            ->withSum('records', 'fruit_count')
            ->withSum('records', 'total_weight_kg');

        $accessibleGardenIds = $this->territoryScope->getAccessibleGardenIds($request->user());
        if ($accessibleGardenIds !== null) {
            $query->whereIn('garden_id', $accessibleGardenIds);
        }

        if ($request->filled('season_id')) {
            $query->where('season_id', $request->string('season_id')->toString());
        }

        if ($request->filled('garden_id')) {
            $query->where('garden_id', $request->string('garden_id')->toString());
        }

        if ($request->filled('from_date')) {
            $query->whereDate('harvest_date', '>=', $request->string('from_date')->toString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('harvest_date', '<=', $request->string('to_date')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $q) use ($search) {
                $q->where('session_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $sessions = $query->orderByDesc('harvest_date')->paginate($perPage);

        return $this->successResponse([
            'items' => HarvestSessionResource::collection($sessions->items()),
            'pagination' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ], 'Daftar sesi panen berhasil dimuat');
    }

    /**
     * Store a newly created harvest session respecting composite FK (season_id, garden_id).
     */
    public function store(HarvestSessionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $season = HarvestSeason::findOrFail($data['season_id']);

        if (! empty($data['garden_id']) && $data['garden_id'] !== $season->garden_id) {
            return $this->errorResponse('Kebun pada sesi panen harus sesuai dengan kebun pada musim panen', 422);
        }

        $gardenId = $season->garden_id;

        if (! $this->territoryScope->canAccessGarden($request->user(), $gardenId)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk membuat sesi panen pada kebun ini', 403);
        }

        $session = HarvestSession::create([
            'season_id' => $season->id,
            'garden_id' => $gardenId,
            'harvest_date' => $data['harvest_date'],
            'session_name' => $data['session_name'] ?? null,
            'weather_condition' => $data['weather_condition'] ?? null,
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        $session->load(['season', 'garden', 'recorder'])
            ->loadCount('records')
            ->loadSum('records', 'fruit_count')
            ->loadSum('records', 'total_weight_kg');

        return $this->successResponse(
            new HarvestSessionResource($session),
            'Sesi panen berhasil dibuat',
            201
        );
    }

    /**
     * Display the specified harvest session with its tree harvest records.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $session = HarvestSession::query()
            ->with([
                'season',
                'garden',
                'recorder',
                'records' => fn ($q) => $q->with(['tree.variety', 'details'])->orderByDesc('created_at'),
            ])
            ->withCount('records')
            ->withSum('records', 'fruit_count')
            ->withSum('records', 'total_weight_kg')
            ->find($id);

        if (! $session) {
            return $this->errorResponse('Sesi panen tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $session->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke sesi panen ini', 403);
        }

        return $this->successResponse(
            new HarvestSessionResource($session),
            'Detail sesi panen berhasil dimuat'
        );
    }

    /**
     * Update the specified harvest session.
     */
    public function update(HarvestSessionRequest $request, string $id): JsonResponse
    {
        $session = HarvestSession::find($id);

        if (! $session) {
            return $this->errorResponse('Sesi panen tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $session->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk mengubah sesi panen ini', 403);
        }

        $data = $request->validated();
        $season = HarvestSeason::findOrFail($data['season_id']);

        if (! empty($data['garden_id']) && $data['garden_id'] !== $season->garden_id) {
            return $this->errorResponse('Kebun pada sesi panen harus sesuai dengan kebun pada musim panen', 422);
        }

        if ($season->garden_id !== $session->garden_id && $session->records()->exists()) {
            return $this->errorResponse(
                'Musim/kebun pada sesi panen tidak dapat dipindahkan karena sudah memiliki data hasil panen pohon',
                422
            );
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $season->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke kebun tujuan', 403);
        }

        $session->update([
            'season_id' => $season->id,
            'garden_id' => $season->garden_id,
            'harvest_date' => $data['harvest_date'],
            'session_name' => $data['session_name'] ?? null,
            'weather_condition' => $data['weather_condition'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $session->load(['season', 'garden', 'recorder'])
            ->loadCount('records')
            ->loadSum('records', 'fruit_count')
            ->loadSum('records', 'total_weight_kg');

        return $this->successResponse(
            new HarvestSessionResource($session),
            'Sesi panen berhasil diperbarui'
        );
    }

    /**
     * Remove the specified harvest session if it has no harvest records.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $session = HarvestSession::find($id);

        if (! $session) {
            return $this->errorResponse('Sesi panen tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $session->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk menghapus sesi panen ini', 403);
        }

        if ($session->records()->exists()) {
            return $this->errorResponse('Sesi panen tidak dapat dihapus karena sudah memiliki catatan hasil panen', 422);
        }

        $session->delete();

        return $this->successResponse(null, 'Sesi panen berhasil dihapus');
    }
}
