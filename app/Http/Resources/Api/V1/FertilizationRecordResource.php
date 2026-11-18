<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FertilizationRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $methods = (array) config('durian.activities.fertilization_methods', []);

        return [
            'id' => $this->id,
            'tree_id' => $this->tree_id,
            'activity_date' => $this->activity_date?->toISOString(),
            'method' => $this->method,
            'method_label' => $methods[$this->method] ?? $this->method,
            'dosage_notes' => $this->dosage_notes,
            'notes' => $this->notes,
            'recorded_by' => $this->recorded_by,
            'tree' => $this->whenLoaded('tree', fn () => $this->tree ? [
                'id' => $this->tree->id,
                'tree_code' => $this->tree->tree_code,
                'tree_name' => $this->tree->tree_name,
                'garden_id' => $this->tree->garden_id,
                'garden_name' => $this->tree->relationLoaded('garden') ? $this->tree->garden?->name : null,
            ] : null),
            'recorder' => $this->whenLoaded('recorder', fn () => $this->recorder ? [
                'id' => $this->recorder->id,
                'name' => $this->recorder->name,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->relationLoaded('product') ? $item->product?->name : null,
                'brand' => $item->relationLoaded('product') ? $item->product?->brand : null,
                'category_name' => ($item->relationLoaded('product') && $item->product?->relationLoaded('category'))
                    ? $item->product->category?->name
                    : null,
                'quantity' => $item->quantity !== null ? (float) $item->quantity : null,
                'unit' => $item->unit,
                'notes' => $item->notes,
            ])->values()),
            'photos' => ActivityPhotoResource::collection($this->whenLoaded('photos')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
