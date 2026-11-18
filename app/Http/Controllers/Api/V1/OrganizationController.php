<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\OrganizationRequest;
use App\Http\Resources\Api\V1\OrganizationResource;
use App\Models\Organization;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of organizations.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Organization::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $organizations = $query->latest()->paginate((int) $request->input('per_page', 15));

        return $this->successResponse([
            'items' => OrganizationResource::collection($organizations->items()),
            'pagination' => [
                'current_page' => $organizations->currentPage(),
                'last_page' => $organizations->lastPage(),
                'per_page' => $organizations->perPage(),
                'total' => $organizations->total(),
            ],
        ], 'Daftar organisasi berhasil dimuat');
    }

    /**
     * Store a newly created organization.
     */
    public function store(OrganizationRequest $request): JsonResponse
    {
        $org = Organization::create($request->validated());

        return $this->successResponse(
            new OrganizationResource($org),
            'Organisasi berhasil dibuat',
            201
        );
    }

    /**
     * Display the specified organization.
     */
    public function show(string $id): JsonResponse
    {
        $org = Organization::find($id);

        if (! $org) {
            return $this->errorResponse('Organisasi tidak ditemukan', 404);
        }

        return $this->successResponse(new OrganizationResource($org), 'Detail organisasi berhasil dimuat');
    }

    /**
     * Update the specified organization.
     */
    public function update(OrganizationRequest $request, string $id): JsonResponse
    {
        $org = Organization::find($id);

        if (! $org) {
            return $this->errorResponse('Organisasi tidak ditemukan', 404);
        }

        $org->update($request->validated());

        return $this->successResponse(new OrganizationResource($org), 'Organisasi berhasil diperbarui');
    }

    /**
     * Remove the specified organization.
     */
    public function destroy(string $id): JsonResponse
    {
        $org = Organization::find($id);

        if (! $org) {
            return $this->errorResponse('Organisasi tidak ditemukan', 404);
        }

        $org->delete();

        return $this->successResponse(null, 'Organisasi berhasil dihapus');
    }
}
