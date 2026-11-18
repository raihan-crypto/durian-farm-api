<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreeMeasurementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tree_id' => $this->tree_id,
            'measured_at' => $this->measured_at?->toISOString(),
            'diameter_cm' => $this->diameter_cm !== null ? (float) $this->diameter_cm : null,
            'height_m' => $this->height_m !== null ? (float) $this->height_m : null,
            'canopy_width_m' => $this->canopy_width_m !== null ? (float) $this->canopy_width_m : null,
            'notes' => $this->notes,
            'recorded_by' => $this->recorded_by,
            'recorder' => $this->whenLoaded('recorder', fn () => $this->recorder ? [
                'id' => $this->recorder->id,
                'name' => $this->recorder->name,
            ] : null),
            'photos' => ActivityPhotoResource::collection($this->whenLoaded('photos')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
