<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FruitDevelopmentRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stages = (array) config('durian.activities.fruit_development_stages', []);

        return [
            'id' => $this->id,
            'tree_id' => $this->tree_id,
            'observation_date' => $this->observation_date?->toDateString(),
            'fruit_count' => $this->fruit_count,
            'development_stage' => $this->development_stage,
            'development_stage_label' => $stages[$this->development_stage] ?? $this->development_stage,
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
