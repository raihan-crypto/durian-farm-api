<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FloweringRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statuses = (array) config('durian.activities.flowering_statuses', []);

        return [
            'id' => $this->id,
            'tree_id' => $this->tree_id,
            'flowering_date' => $this->flowering_date?->toDateString(),
            'flowering_status' => $this->flowering_status,
            'flowering_status_label' => $statuses[$this->flowering_status] ?? $this->flowering_status,
            'flower_count' => $this->flower_count,
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
            'photos' => ActivityPhotoResource::collection($this->whenLoaded('photos')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
