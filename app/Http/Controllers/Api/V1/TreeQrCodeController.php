<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TreeQrCodeRequest;
use App\Http\Resources\Api\V1\TreeQrCodeResource;
use App\Models\Tree;
use App\Services\TerritoryScopeService;
use App\Services\TreeQrCodeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TreeQrCodeController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TerritoryScopeService $territoryScope,
        protected TreeQrCodeService $qrCodeService,
    ) {}

    /**
     * Get (or initialize) the QR Code metadata for a tree.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tree = Tree::find($id);

        if (! $tree) {
            return $this->errorResponse('Pohon tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke QR Code pohon ini', 403);
        }

        $qrCode = $this->qrCodeService->ensureForTree($tree);

        return $this->successResponse(
            new TreeQrCodeResource($qrCode),
            'QR Code pohon berhasil dimuat'
        );
    }

    /**
     * Update QR Code active status or allowed public information fields (SRS 10.1 & 10.3).
     */
    public function update(TreeQrCodeRequest $request, string $id): JsonResponse
    {
        $tree = Tree::find($id);

        if (! $tree) {
            return $this->errorResponse('Pohon tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk mengubah QR Code pohon ini', 403);
        }

        $qrCode = $this->qrCodeService->ensureForTree($tree);
        $qrCode->update($request->validated());

        return $this->successResponse(
            new TreeQrCodeResource($qrCode->refresh()),
            'Pengaturan QR Code pohon berhasil diperbarui'
        );
    }

    /**
     * Regenerate the tree QR Code with a fresh token (invalidates the previous token).
     */
    public function regenerate(Request $request, string $id): JsonResponse
    {
        $tree = Tree::find($id);

        if (! $tree) {
            return $this->errorResponse('Pohon tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk meregenerasi QR Code pohon ini', 403);
        }

        $qrCode = $this->qrCodeService->ensureForTree($tree);
        $qrCode = $this->qrCodeService->regenerate($qrCode);

        return $this->successResponse(
            new TreeQrCodeResource($qrCode),
            'QR Code pohon berhasil diregenerasi'
        );
    }

    /**
     * Stream the rendered QR Code image (SVG or PNG) for printing or display.
     */
    public function image(Request $request, string $id): Response|JsonResponse
    {
        $tree = Tree::find($id);

        if (! $tree) {
            return $this->errorResponse('Pohon tidak ditemukan', 404);
        }

        if (! $this->territoryScope->canAccessGarden($request->user(), $tree->garden_id)) {
            return $this->errorResponse('Anda tidak memiliki akses ke QR Code pohon ini', 403);
        }

        $format = strtolower($request->string('format', 'svg')->toString());
        if (! in_array($format, ['svg', 'png'], true)) {
            return $this->errorResponse('Format gambar QR Code harus svg atau png', 422);
        }

        $size = min(max((int) $request->input('size', 300), 100), 1000);
        $qrCode = $this->qrCodeService->ensureForTree($tree);
        $result = $this->qrCodeService->render($qrCode, $format, $size);

        $headers = [
            'Content-Type' => $result->getMimeType(),
            'Cache-Control' => 'private, max-age=300',
        ];

        if ($request->boolean('download')) {
            $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '_', $tree->tree_code);
            $headers['Content-Disposition'] = "attachment; filename=\"qr-{$safeCode}.{$format}\"";
        }

        return response($result->getString(), 200, $headers);
    }

    /**
     * Return available and default public field options for QR Code configuration.
     */
    public function publicFieldsMeta(): JsonResponse
    {
        return $this->successResponse([
            'available_fields' => (array) config('durian.public_tree.available_fields', []),
            'default_fields' => (array) config('durian.public_tree.default_fields', []),
        ], 'Konfigurasi informasi publik pohon berhasil dimuat');
    }
}
