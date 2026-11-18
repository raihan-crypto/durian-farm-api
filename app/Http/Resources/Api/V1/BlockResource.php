<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockResource extends JsonResource
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
            'village_id' => $this->village_id,
            'name' => $this->name,
            'area_hectares' => $this->area_hectares !== null ? (float) $this->area_hectares : null,
            'description' => $this->description,
            'village' => new VillageResource($this->whenLoaded('village')),
            'gardens_count' => $this->when(isset($this->gardens_count), fn () => (int) $this->gardens_count),
            'trees_count' => $this->when(isset($this->trees_count), fn () => (int) $this->trees_count),
            'active_farmers_count' => $this->when(isset($this->active_farmers_count), fn () => (int) $this->active_farmers_count),
            'gardens' => GardenResource::collection($this->whenLoaded('gardens')),
            'farmer_assignments' => BlockFarmerResource::collection($this->whenLoaded('blockFarmers')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
