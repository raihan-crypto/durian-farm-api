<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ActivityPhotoUploadRequest;
use App\Http\Resources\Api\V1\ActivityPhotoResource;
use App\Models\ActivityPhoto;
use App\Services\ActivityTargetResolver;
use App\Services\PhotoStorageService;
use App\Services\TerritoryScopeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityPhotoController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ActivityTargetResolver $targetResolver,
        protected TerritoryScopeService $territoryScope,
        protected PhotoStorageService $photoStorage,
    ) {}

    /**
     * List activity photos for a validated target, or paginated list for unrestricted managers/admins.
     */
    public function index(Request $request): JsonResponse
    {
        $hasType = $request->filled('activity_type');
        $hasId = $request->filled('activity_id');

        if ($hasType && $hasId) {
            $activityType = $request->string('activity_type')->toString();
            $activityId = $request->string('activity_id')->toString();

            $target = $this->targetResolver->resolve($activityType, $activityId);

            if (! $target) {
                return $this->errorResponse('Aktivitas tujuan foto tidak ditemukan', 404);
            }

            if (! $this->territoryScope->canAccessGarden($request->user(), $target['garden_id'])) {
                return $this->errorResponse('Anda tidak memiliki akses ke foto aktivitas ini', 403);
            }

            $photos = ActivityPhoto::query()
                ->where('activity_type', $activityType)
                ->where('activity_id', $activityId)
                ->orderByDesc('created_at')
                ->get();

            return $this->successResponse(
                ActivityPhotoResource::collection($photos),
                'Daftar foto aktivitas berhasil dimuat'
            );
        }

        if ($this->territoryScope->isScoped($request->user())) {
            return $this->errorResponse('Parameter activity_type dan activity_id wajib diisi', 422);
        }

        $query = ActivityPhoto::query();

        if ($hasType) {
            $query->where('activity_type', $request->string('activity_type')->toString());
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $photos = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->successResponse([
            'items' => ActivityPhotoResource::collection($photos->items()),
            'pagination' => [
                'current_page' => $photos->currentPage(),
                'last_page' => $photos->lastPage(),
                'per_page' => $photos->perPage(),
                'total' => $photos->total(),
            ],
        ], 'Daftar foto aktivitas berhasil dimuat');
    }

    /**
     * Upload a photo linked to a validated polymorphic activity target (SRS 5.5 & 15.4).
     */
    public function store(ActivityPhotoUploadRequest $request): JsonResponse
    {
        $data = $request->validated();
        $target = $this->targetResolver->resolve($data['activity_type'], $data['activity_id']);

        if (! $target) {
            return $this->errorResponse('Aktivitas tujuan tidak ditemukan untuk foto ini', 422);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $target['garden_id'])) {
            return $this->errorResponse('Anda tidak memiliki akses untuk mengunggah foto pada aktivitas ini', 403);
        }

        $photo = $this->photoStorage->storeActivityPhoto(
            $request->file('photo'),
            $data['activity_type'],
            $data['activity_id'],
            $request->user()->id,
            $data['caption'] ?? null
        );

        return $this->successResponse(
            new ActivityPhotoResource($photo),
            'Foto aktivitas berhasil diunggah',
            201
        );
    }

    /**
     * Display the specified activity photo metadata and URL.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $photo = ActivityPhoto::find($id);

        if (! $photo) {
            return $this->errorResponse('Foto aktivitas tidak ditemukan', 404);
        }

        $target = $this->targetResolver->resolve($photo->activity_type, $photo->activity_id);

        if ($this->territoryScope->isScoped($request->user())) {
            if (! $target || ! $this->territoryScope->canAccessGarden($request->user(), $target['garden_id'])) {
                return $this->errorResponse('Anda tidak memiliki akses ke foto aktivitas ini', 403);
            }
        }

        return $this->successResponse(
            new ActivityPhotoResource($photo),
            'Detail foto aktivitas berhasil dimuat'
        );
    }

    /**
     * Remove the specified activity photo with proper ownership/territory authorization.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $photo = ActivityPhoto::find($id);

        if (! $photo) {
            return $this->errorResponse('Foto aktivitas tidak ditemukan', 404);
        }

        $user = $request->user();
        $target = $this->targetResolver->resolve($photo->activity_type, $photo->activity_id);

        if ($this->territoryScope->isScoped($user)) {
            if (! $target || ! $this->territoryScope->canAccessGarden($user, $target['garden_id'])) {
                return $this->errorResponse('Anda tidak memiliki akses untuk menghapus foto ini', 403);
            }

            if ($photo->uploaded_by !== $user->id) {
                return $this->errorResponse('Anda hanya dapat menghapus foto yang Anda unggah sendiri', 403);
            }
        }

        $this->photoStorage->deleteActivityPhoto($photo);

        return $this->successResponse(null, 'Foto aktivitas berhasil dihapus');
    }
}
