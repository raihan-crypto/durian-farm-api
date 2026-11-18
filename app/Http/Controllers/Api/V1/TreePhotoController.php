<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PhotoUploadRequest;
use App\Http\Resources\Api\V1\ActivityPhotoResource;
use App\Models\ActivityPhoto;
use App\Models\Tree;
use App\Services\PhotoStorageService;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreePhotoController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TerritoryScopeService $territoryScope,
        protected PhotoStorageService $photoStorage,
    ) {}

    /**
     * List photos attached to a tree.
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $tree = Tree::find($id);

        if (! $tree) {
            return $this->errorResponse('Pohon tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke foto pohon ini', 403);
        }

        $photos = $tree->photos()->get();

        return $this->successResponse(
            ActivityPhotoResource::collection($photos),
            'Daftar foto pohon berhasil dimuat'
        );
    }

    /**
     * Upload a photo for a tree.
     */
    public function store(PhotoUploadRequest $request, string $id): JsonResponse
    {
        $tree = Tree::find($id);

        if (! $tree) {
            return $this->errorResponse('Pohon tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk mengunggah foto pohon ini', 403);
        }

        $photo = $this->photoStorage->storeActivityPhoto(
            $request->file('photo'),
            Tree::PHOTO_ACTIVITY_TYPE,
            $tree->id,
            $request->user()->id,
            $request->input('caption')
        );

        return $this->successResponse(
            new ActivityPhotoResource($photo),
            'Foto pohon berhasil diunggah',
            201
        );
    }

    /**
     * Delete a photo attached to a tree.
     */
    public function destroy(Request $request, string $id, string $photoId): JsonResponse
    {
        $tree = Tree::find($id);

        if (! $tree) {
            return $this->errorResponse('Pohon tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk menghapus foto pohon ini', 403);
        }

        $photo = ActivityPhoto::query()
            ->where('activity_type', Tree::PHOTO_ACTIVITY_TYPE)
            ->where('activity_id', $tree->id)
            ->find($photoId);

        if (! $photo) {
            return $this->errorResponse('Foto pohon tidak ditemukan', 404);
        }

        $this->photoStorage->deleteActivityPhoto($photo);

        return $this->successResponse(null, 'Foto pohon berhasil dihapus');
    }
}
