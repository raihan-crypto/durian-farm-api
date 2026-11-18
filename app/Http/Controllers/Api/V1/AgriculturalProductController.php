<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AgriculturalProductRequest;
use App\Http\Resources\Api\V1\AgriculturalProductResource;
use App\Models\AgriculturalProduct;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgriculturalProductController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of agricultural products.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AgriculturalProduct::query()->with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->string('category_id')->toString());
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('active_ingredient', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $products = $query->orderBy('name')->paginate($perPage);

        return $this->successResponse([
            'items' => AgriculturalProductResource::collection($products->items()),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ], 'Daftar produk pertanian berhasil dimuat');
    }

    /**
     * Store a newly created agricultural product.
     */
    public function store(AgriculturalProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $product = AgriculturalProduct::create($data);
        $product->load('category');

        return $this->successResponse(
            new AgriculturalProductResource($product),
            'Produk pertanian berhasil dibuat',
            201
        );
    }

    /**
     * Display the specified agricultural product.
     */
    public function show(string $id): JsonResponse
    {
        $product = AgriculturalProduct::query()->with('category')->find($id);

        if (! $product) {
            return $this->errorResponse('Produk pertanian tidak ditemukan', 404);
        }

        return $this->successResponse(
            new AgriculturalProductResource($product),
            'Detail produk pertanian berhasil dimuat'
        );
    }

    /**
     * Update the specified agricultural product.
     */
    public function update(AgriculturalProductRequest $request, string $id): JsonResponse
    {
        $product = AgriculturalProduct::find($id);

        if (! $product) {
            return $this->errorResponse('Produk pertanian tidak ditemukan', 404);
        }

        $product->update($request->validated());
        $product->load('category');

        return $this->successResponse(
            new AgriculturalProductResource($product),
            'Produk pertanian berhasil diperbarui'
        );
    }

    /**
     * Remove the specified agricultural product if unused in activity history.
     */
    public function destroy(string $id): JsonResponse
    {
        $product = AgriculturalProduct::find($id);

        if (! $product) {
            return $this->errorResponse('Produk pertanian tidak ditemukan', 404);
        }

        if ($product->fertilizationItems()->exists() || $product->sprayingItems()->exists()) {
            return $this->errorResponse(
                'Produk tidak dapat dihapus karena sudah digunakan dalam riwayat pemupukan atau penyemprotan. Silakan nonaktifkan produk ini.',
                422
            );
        }

        $product->delete();

        return $this->successResponse(null, 'Produk pertanian berhasil dihapus');
    }
}
