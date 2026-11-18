<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductCategoryRequest;
use App\Http\Resources\Api\V1\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of agricultural product categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductCategory::query()->withCount('products');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $categories = $query->orderBy('name')->paginate($perPage);

        return $this->successResponse([
            'items' => ProductCategoryResource::collection($categories->items()),
            'pagination' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ], 'Daftar kategori produk berhasil dimuat');
    }

    /**
     * Store a newly created product category.
     */
    public function store(ProductCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $category = ProductCategory::create($data);
        $category->loadCount('products');

        return $this->successResponse(
            new ProductCategoryResource($category),
            'Kategori produk berhasil dibuat',
            201
        );
    }

    /**
     * Display the specified product category.
     */
    public function show(string $id): JsonResponse
    {
        $category = ProductCategory::query()->withCount('products')->find($id);

        if (! $category) {
            return $this->errorResponse('Kategori produk tidak ditemukan', 404);
        }

        return $this->successResponse(
            new ProductCategoryResource($category),
            'Detail kategori produk berhasil dimuat'
        );
    }

    /**
     * Update the specified product category.
     */
    public function update(ProductCategoryRequest $request, string $id): JsonResponse
    {
        $category = ProductCategory::find($id);

        if (! $category) {
            return $this->errorResponse('Kategori produk tidak ditemukan', 404);
        }

        $category->update($request->validated());
        $category->loadCount('products');

        return $this->successResponse(
            new ProductCategoryResource($category),
            'Kategori produk berhasil diperbarui'
        );
    }

    /**
     * Remove the specified product category if it has no products.
     */
    public function destroy(string $id): JsonResponse
    {
        $category = ProductCategory::find($id);

        if (! $category) {
            return $this->errorResponse('Kategori produk tidak ditemukan', 404);
        }

        if ($category->products()->exists()) {
            return $this->errorResponse(
                'Kategori produk tidak dapat dihapus karena masih memiliki produk terdaftar. Silakan nonaktifkan kategori ini.',
                422
            );
        }

        $category->delete();

        return $this->successResponse(null, 'Kategori produk berhasil dihapus');
    }
}
