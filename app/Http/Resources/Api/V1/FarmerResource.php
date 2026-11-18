<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FarmerResource extends JsonResource
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
            'user_id' => $this->user_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'user' => new UserResource($this->whenLoaded('user')),
            'block_assignments_count' => $this->when(isset($this->block_farmers_count), fn () => (int) $this->block_farmers_count),
            'garden_assignments_count' => $this->when(isset($this->garden_farmers_count), fn () => (int) $this->garden_farmers_count),
            'block_assignments' => BlockFarmerResource::collection($this->whenLoaded('blockFarmers')),
            'garden_assignments' => GardenFarmerResource::collection($this->whenLoaded('gardenFarmers')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
