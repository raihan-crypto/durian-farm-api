<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OwnerResource extends JsonResource
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
            'owner_type' => $this->owner_type,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'gardens_count' => $this->when(isset($this->garden_owners_count), fn () => (int) $this->garden_owners_count),
            'garden_ownerships' => GardenOwnerResource::collection($this->whenLoaded('gardenOwners')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
