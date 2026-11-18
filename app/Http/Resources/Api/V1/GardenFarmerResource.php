<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GardenFarmerResource extends JsonResource
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
            'farmer_id' => $this->farmer_id,
            'assignment_date' => $this->assignment_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_active' => $isActive,
            'farmer' => new FarmerResource($this->whenLoaded('farmer')),
            'garden' => new GardenResource($this->whenLoaded('garden')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
