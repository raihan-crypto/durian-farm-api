<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockFarmerResource extends JsonResource
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
            'block_id' => $this->block_id,
            'farmer_id' => $this->farmer_id,
            'assignment_date' => $this->assignment_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_active' => $isActive,
            'farmer' => new FarmerResource($this->whenLoaded('farmer')),
            'block' => new BlockResource($this->whenLoaded('block')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
