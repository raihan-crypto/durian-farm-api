<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\OwnerRequest;
use App\Http\Resources\Api\V1\OwnerResource;
use App\Models\Owner;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of garden owners.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Owner::query()->withCount('gardenOwners');

        if ($request->filled('owner_type')) {
            $query->where('owner_type', $request->string('owner_type')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $owners = $query->orderBy('name')->paginate($perPage);

        return $this->successResponse([
            'items' => OwnerResource::collection($owners->items()),
            'pagination' => [
                'current_page' => $owners->currentPage(),
                'last_page' => $owners->lastPage(),
                'per_page' => $owners->perPage(),
                'total' => $owners->total(),
            ],
        ], 'Daftar pemilik kebun berhasil dimuat');
    }

    /**
     * Store a newly created garden owner.
     */
    public function store(OwnerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['owner_type'] = $data['owner_type'] ?? 'individual';

        $owner = Owner::create($data);
        $owner->loadCount('gardenOwners');

        return $this->successResponse(
            new OwnerResource($owner),
            'Pemilik kebun berhasil dibuat',
            201
        );
    }

    /**
     * Display the specified garden owner with owned gardens.
     */
    public function show(string $id): JsonResponse
    {
        $owner = Owner::query()
            ->with([
                'gardenOwners' => fn ($q) => $q->with('garden.block.village')->orderByDesc('start_date'),
            ])
            ->withCount('gardenOwners')
            ->find($id);

        if (! $owner) {
            return $this->errorResponse('Pemilik kebun tidak ditemukan', 404);
        }

        return $this->successResponse(new OwnerResource($owner), 'Detail pemilik kebun berhasil dimuat');
    }

    /**
     * Update the specified garden owner.
     */
    public function update(OwnerRequest $request, string $id): JsonResponse
    {
        $owner = Owner::find($id);

        if (! $owner) {
            return $this->errorResponse('Pemilik kebun tidak ditemukan', 404);
        }

        $owner->update($request->validated());
        $owner->loadCount('gardenOwners');

        return $this->successResponse(new OwnerResource($owner), 'Pemilik kebun berhasil diperbarui');
    }

    /**
     * Remove the specified garden owner if not linked to any garden.
     */
    public function destroy(string $id): JsonResponse
    {
        $owner = Owner::find($id);

        if (! $owner) {
            return $this->errorResponse('Pemilik kebun tidak ditemukan', 404);
        }

        if ($owner->gardenOwners()->exists()) {
            return $this->errorResponse('Pemilik kebun tidak dapat dihapus karena masih terhubung dengan data kepemilikan kebun', 422);
        }

        $owner->delete();

        return $this->successResponse(null, 'Pemilik kebun berhasil dihapus');
    }
}
