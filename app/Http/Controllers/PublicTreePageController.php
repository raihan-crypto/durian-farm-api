<?php

namespace App\Http\Controllers;

use App\Models\TreeQrCode;
use App\Services\PublicTreePresenter;
use App\Services\TreeQrCodeService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicTreePageController extends Controller
{
    public function __construct(
        protected TreeQrCodeService $qrCodeService,
        protected PublicTreePresenter $presenter,
    ) {}

    /**
     * Display the public Blade page reached by scanning a tree QR Code (SRS 10.2).
     */
    public function show(string $token): View|Response
    {
        if (! $this->qrCodeService->isValidTokenFormat($token)) {
            return response()->view('public.tree-unavailable', [], 404);
        }

        $qrCode = TreeQrCode::query()
            ->where('public_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $qrCode || ! $qrCode->tree) {
            return response()->view('public.tree-unavailable', [], 404);
        }

        return view('public.tree', [
            'tree' => $this->presenter->present($qrCode),
        ]);
    }
}
