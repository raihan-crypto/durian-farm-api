<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GardenResource extends JsonResource
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
            'block_id' => $this->block_id,
            'name' => $this->name,
            'area_hectares' => $this->area_hectares !== null ? (float) $this->area_hectares : null,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'description' => $this->description,
            'block' => new BlockResource($this->whenLoaded('block')),
            'trees_count' => $this->when(isset($this->trees_count), fn () => (int) $this->trees_count),
            'harvest_seasons_count' => $this->when(isset($this->harvest_seasons_count), fn () => (int) $this->harvest_seasons_count),
            'harvest_sessions_count' => $this->when(isset($this->harvest_sessions_count), fn () => (int) $this->harvest_sessions_count),
            'owners' => GardenOwnerResource::collection($this->whenLoaded('gardenOwners')),
            'farmer_assignments' => GardenFarmerResource::collection($this->whenLoaded('gardenFarmers')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
