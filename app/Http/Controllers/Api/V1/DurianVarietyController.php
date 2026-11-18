<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DurianVarietyRequest;
use App\Http\Requests\Api\V1\PhotoUploadRequest;
use App\Http\Resources\Api\V1\DurianVarietyResource;
use App\Models\DurianVariety;
use App\Services\PhotoStorageService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DurianVarietyController extends Controller
{
    use ApiResponse;

    public function __construct(protected PhotoStorageService $photoStorage) {}

    /**
     * Display a listing of durian varieties.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DurianVariety::query()->withCount('trees');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('origin', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('characteristics', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $varieties = $query->orderBy('name')->paginate($perPage);

        return $this->successResponse([
            'items' => DurianVarietyResource::collection($varieties->items()),
            'pagination' => [
                'current_page' => $varieties->currentPage(),
                'last_page' => $varieties->lastPage(),
                'per_page' => $varieties->perPage(),
                'total' => $varieties->total(),
            ],
        ], 'Daftar varietas durian berhasil dimuat');
    }

    /**
     * Store a newly created durian variety.
     */
    public function store(DurianVarietyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $variety = DurianVariety::create($data);
        $variety->loadCount('trees');

        return $this->successResponse(
            new DurianVarietyResource($variety),
            'Varietas durian berhasil dibuat',
            201
        );
    }

    /**
     * Display the specified durian variety.
     */
    public function show(string $id): JsonResponse
    {
        $variety = DurianVariety::query()->withCount('trees')->find($id);

        if (! $variety) {
            return $this->errorResponse('Varietas durian tidak ditemukan', 404);
        }

        return $this->successResponse(
            new DurianVarietyResource($variety),
            'Detail varietas durian berhasil dimuat'
        );
    }

    /**
     * Update the specified durian variety.
     */
    public function update(DurianVarietyRequest $request, string $id): JsonResponse
    {
        $variety = DurianVariety::find($id);

        if (! $variety) {
            return $this->errorResponse('Varietas durian tidak ditemukan', 404);
        }

        $variety->update($request->validated());
        $variety->loadCount('trees');

        return $this->successResponse(
            new DurianVarietyResource($variety),
            'Varietas durian berhasil diperbarui'
        );
    }

    /**
     * Remove the specified durian variety if no trees reference it.
     */
    public function destroy(string $id): JsonResponse
    {
        $variety = DurianVariety::find($id);

        if (! $variety) {
            return $this->errorResponse('Varietas durian tidak ditemukan', 404);
        }

        if ($variety->trees()->exists()) {
            return $this->errorResponse(
                'Varietas tidak dapat dihapus karena masih digunakan oleh data pohon. Silakan nonaktifkan varietas ini.',
                422
            );
        }

        $this->photoStorage->deleteFile($variety->photo_path);
        $variety->delete();

        return $this->successResponse(null, 'Varietas durian berhasil dihapus');
    }

    /**
     * Upload or replace the photo for a durian variety.
     */
    public function uploadPhoto(PhotoUploadRequest $request, string $id): JsonResponse
    {
        $variety = DurianVariety::find($id);

        if (! $variety) {
            return $this->errorResponse('Varietas durian tidak ditemukan', 404);
        }

        $newPath = $this->photoStorage->storeFile($request->file('photo'), "varieties/{$variety->id}");
        $oldPath = $variety->photo_path;

        $variety->update(['photo_path' => $newPath]);
        $this->photoStorage->deleteFile($oldPath);
        $variety->loadCount('trees');

        return $this->successResponse(
            new DurianVarietyResource($variety),
            'Foto varietas berhasil diunggah'
        );
    }

    /**
     * Remove the photo of a durian variety.
     */
    public function deletePhoto(string $id): JsonResponse
    {
        $variety = DurianVariety::find($id);

        if (! $variety) {
            return $this->errorResponse('Varietas durian tidak ditemukan', 404);
        }

        $this->photoStorage->deleteFile($variety->photo_path);
        $variety->update(['photo_path' => null]);
        $variety->loadCount('trees');

        return $this->successResponse(
            new DurianVarietyResource($variety),
            'Foto varietas berhasil dihapus'
        );
    }
}
