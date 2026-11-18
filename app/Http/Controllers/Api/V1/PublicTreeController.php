<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TreeQrCode;
use App\Services\PublicTreePresenter;
use App\Services\TreeQrCodeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PublicTreeController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TreeQrCodeService $qrCodeService,
        protected PublicTreePresenter $presenter,
    ) {}

    /**
     * Return privacy-filtered public information for a tree identified by its QR Code token (SRS 10 & 16.3).
     */
    public function show(string $token): JsonResponse
    {
        if (! $this->qrCodeService->isValidTokenFormat($token)) {
            return $this->errorResponse('Informasi pohon tidak ditemukan atau QR Code tidak aktif', 404);
        }

        $qrCode = TreeQrCode::query()
            ->where('public_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $qrCode || ! $qrCode->tree) {
            return $this->errorResponse('Informasi pohon tidak ditemukan atau QR Code tidak aktif', 404);
        }

        return $this->successResponse(
            $this->presenter->present($qrCode),
            'Informasi publik pohon berhasil dimuat'
        );
    }
}
