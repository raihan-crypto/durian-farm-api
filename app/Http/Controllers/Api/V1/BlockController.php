<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BlockRequest;
use App\Http\Requests\Api\V1\FarmerAssignmentRequest;
use App\Http\Resources\Api\V1\BlockFarmerResource;
use App\Http\Resources\Api\V1\BlockResource;
use App\Models\Block;
use App\Models\BlockFarmer;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BlockController extends Controller
{
    use ApiResponse;

    public function __construct(protected TerritoryScopeService $territoryScope) {}

    /**
     * Display a listing of blocks.
     */
    public function index(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();

        $query = Block::query()
            ->with(['village'])
            ->withCount([
                'gardens',
                'trees',
                'blockFarmers as active_farmers_count' => function (Builder $q) use ($today) {
                    $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
                },
            ]);

        $accessibleBlockIds = $this->territoryScope->getAccessibleBlockIds($request->user());
        if ($accessibleBlockIds !== null) {
            $query->whereIn('blocks.id', $accessibleBlockIds);
        }

        if ($request->filled('village_id')) {
            $query->where('village_id', $request->string('village_id')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $q) use ($search) {
                $q->where('blocks.name', 'like', "%{$search}%")
                    ->orWhere('blocks.description', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $blocks = $query->orderBy('blocks.name')->paginate($perPage);

        return $this->successResponse([
            'items' => BlockResource::collection($blocks->items()),
            'pagination' => [
                'current_page' => $blocks->currentPage(),
                'last_page' => $blocks->lastPage(),
                'per_page' => $blocks->perPage(),
                'total' => $blocks->total(),
            ],
        ], 'Daftar blok berhasil dimuat');
    }

    /**
     * Store a newly created block.
     */
    public function store(BlockRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($user && $user->hasRole('pemerintah_desa')) {
            if (! empty($user->village_id) && $data['village_id'] !== $user->village_id) {
                return $this->errorResponse('Anda hanya dapat mengajukan blok untuk wilayah desa Anda sendiri', 403);
            }
            $data['approval_status'] = 'pending_addition';
            $data['requested_by'] = $user->id;
        } else {
            $data['approval_status'] = $data['approval_status'] ?? 'approved';
        }

        $block = Block::create($data);
        $block->load('village')->loadCount(['gardens', 'trees']);

        $message = ($block->approval_status === 'pending_addition')
            ? 'Permohonan penambahan blok berhasil diajukan dan menunggu persetujuan admin'
            : 'Blok berhasil dibuat';

        return $this->successResponse(
            new BlockResource($block),
            $message,
            201
        );
    }

    /**
     * Display the specified block with gardens and farmer assignments.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $today = Carbon::today()->toDateString();
        $accessibleGardenIds = $this->territoryScope->getAccessibleGardenIds($request->user());

        $block = Block::query()
            ->with([
                'village',
                'gardens' => function ($query) use ($accessibleGardenIds) {
                    if ($accessibleGardenIds !== null) {
                        $query->whereIn('gardens.id', $accessibleGardenIds);
                    }
                    $query->withCount('trees')->orderBy('name');
                },
                'blockFarmers' => function ($query) {
                    $query->with('farmer')->orderByDesc('assignment_date');
                },
            ])
            ->withCount([
                'gardens',
                'trees',
                'blockFarmers as active_farmers_count' => function (Builder $q) use ($today) {
                    $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
                },
            ])
            ->find($id);

        if (! $block) {
            return $this->errorResponse('Blok tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessBlock($request->user(), $block->id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke blok ini', 403);
        }

        return $this->successResponse(new BlockResource($block), 'Detail blok berhasil dimuat');
    }

    /**
     * Update the specified block.
     */
    public function update(BlockRequest $request, string $id): JsonResponse
    {
        $block = Block::find($id);

        if (! $block) {
            return $this->errorResponse('Blok tidak ditemukan', 404);
        }

        $block->update($request->validated());
        $block->load('village')->loadCount(['gardens', 'trees']);

        return $this->successResponse(new BlockResource($block), 'Blok berhasil diperbarui');
    }

    /**
     * Remove the specified block if it has no gardens.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $block = Block::find($id);

        if (! $block) {
            return $this->errorResponse('Blok tidak ditemukan', 404);
        }

        if ($block->gardens()->exists()) {
            return $this->errorResponse('Blok tidak dapat dihapus karena masih memiliki kebun terdaftar', 422);
        }

        $user = $request->user();
        if ($user && $user->hasRole('pemerintah_desa')) {
            if ($user->village_id && $block->village_id !== $user->village_id) {
                return $this->errorResponse('Anda hanya dapat mengajukan penghapusan blok untuk desa Anda sendiri', 403);
            }
            $block->update([
                'approval_status' => 'pending_deletion',
                'requested_by' => $user->id,
            ]);

            return $this->successResponse(
                new BlockResource($block),
                'Permohonan penghapusan blok berhasil diajukan dan menunggu persetujuan admin'
            );
        }

        $block->delete();

        return $this->successResponse(null, 'Blok berhasil dihapus');
    }

    /**
     * Approve a block addition or deletion request.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $block = Block::find($id);

        if (! $block) {
            return $this->errorResponse('Blok tidak ditemukan', 404);
        }

        if ($block->approval_status === 'pending_deletion') {
            $block->delete();

            return $this->successResponse(null, 'Permohonan penghapusan blok disetujui, blok telah dihapus');
        }

        $block->update([
            'approval_status' => 'approved',
        ]);

        return $this->successResponse(new BlockResource($block), 'Permohonan penambahan blok disetujui');
    }

    /**
     * Reject a block addition or deletion request.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $block = Block::find($id);

        if (! $block) {
            return $this->errorResponse('Blok tidak ditemukan', 404);
        }

        if ($block->approval_status === 'pending_addition') {
            $block->delete();

            return $this->successResponse(null, 'Permohonan penambahan blok ditolak dan data dibatalkan');
        }

        $block->update([
            'approval_status' => 'approved',
        ]);

        return $this->successResponse(new BlockResource($block), 'Permohonan penghapusan blok ditolak');
    }

    /**
     * List farmer assignments for a block.
     */
    public function farmers(Request $request, string $id): JsonResponse
    {
        $block = Block::find($id);

        if (! $block) {
            return $this->errorResponse('Blok tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessBlock($request->user(), $block->id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke blok ini', 403);
        }

        $query = BlockFarmer::query()
            ->with('farmer')
            ->where('block_id', $block->id);

        if ($request->boolean('active_only')) {
            $today = Carbon::today()->toDateString();
            $query->where(function (Builder $q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            });
        }

        $assignments = $query->orderByDesc('assignment_date')->get();

        return $this->successResponse(
            BlockFarmerResource::collection($assignments),
            'Daftar penugasan petani pada blok berhasil dimuat'
        );
    }

    /**
     * Assign a farmer to a block.
     */
    public function assignFarmer(FarmerAssignmentRequest $request, string $id): JsonResponse
    {
        $block = Block::find($id);

        if (! $block) {
            return $this->errorResponse('Blok tidak ditemukan', 404);
        }

        $data = $request->validated();
        $assignmentDate = $data['assignment_date'] ?? Carbon::today()->toDateString();

        $duplicate = BlockFarmer::query()
            ->where('block_id', $block->id)
            ->where('farmer_id', $data['farmer_id'])
            ->whereDate('assignment_date', $assignmentDate)
            ->exists();

        if ($duplicate) {
            return $this->errorResponse('Petani sudah memiliki penugasan pada blok ini dengan tanggal mulai yang sama', 422);
        }

        $assignment = BlockFarmer::create([
            'block_id' => $block->id,
            'farmer_id' => $data['farmer_id'],
            'assignment_date' => $assignmentDate,
            'end_date' => $data['end_date'] ?? null,
        ]);

        $assignment->load('farmer');

        return $this->successResponse(
            new BlockFarmerResource($assignment),
            'Petani berhasil ditugaskan ke blok',
            201
        );
    }

    /**
     * Update or end a farmer assignment on a block.
     */
    public function updateFarmerAssignment(FarmerAssignmentRequest $request, string $id, string $assignmentId): JsonResponse
    {
        $assignment = BlockFarmer::query()
            ->where('block_id', $id)
            ->find($assignmentId);

        if (! $assignment) {
            return $this->errorResponse('Penugasan petani pada blok tidak ditemukan', 404);
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
            $duplicate = BlockFarmer::query()
                ->where('block_id', $id)
                ->where('farmer_id', $assignment->farmer_id)
                ->whereDate('assignment_date', $effectiveAssignmentDate)
                ->where('id', '!=', $assignment->id)
                ->exists();

            if ($duplicate) {
                return $this->errorResponse('Petani sudah memiliki penugasan pada blok ini dengan tanggal mulai yang sama', 422);
            }
        }

        $assignment->update([
            'assignment_date' => $effectiveAssignmentDate,
            'end_date' => $effectiveEndDate,
        ]);

        $assignment->load('farmer');

        return $this->successResponse(
            new BlockFarmerResource($assignment),
            'Penugasan petani pada blok berhasil diperbarui'
        );
    }

    /**
     * Remove a farmer assignment from a block.
     */
    public function removeFarmerAssignment(string $id, string $assignmentId): JsonResponse
    {
        $assignment = BlockFarmer::query()
            ->where('block_id', $id)
            ->find($assignmentId);

        if (! $assignment) {
            return $this->errorResponse('Penugasan petani pada blok tidak ditemukan', 404);
        }

        $assignment->delete();

        return $this->successResponse(null, 'Penugasan petani pada blok berhasil dihapus');
    }
}
