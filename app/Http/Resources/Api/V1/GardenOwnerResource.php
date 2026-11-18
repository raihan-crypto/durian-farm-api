<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GardenOwnerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isActive = $this->end_date === null || $this->end_date->isToday() || $this->end_date->isFuture();

        return [
            'id' => $this->id,
            'garden_id' => $this->garden_id,
            'owner_id' => $this->owner_id,
            'ownership_percentage' => $this->ownership_percentage !== null ? (float) $this->ownership_percentage : null,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_active' => $isActive,
            'owner' => new OwnerResource($this->whenLoaded('owner')),
            'garden' => new GardenResource($this->whenLoaded('garden')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
