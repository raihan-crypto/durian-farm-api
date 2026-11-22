<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VillageResource extends JsonResource
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
            'name' => $this->name,
            'village_code' => $this->village_code,
            'district' => $this->district,
            'regency' => $this->regency,
            'province' => $this->province,
            'blocks_count' => $this->when(isset($this->blocks_count), fn () => (int) $this->blocks_count),
            'gardens_count' => $this->when(isset($this->gardens_count), fn () => (int) $this->gardens_count),
            'trees_count' => $this->when(isset($this->trees_count), fn () => (int) $this->trees_count),
            'blocks' => BlockResource::collection($this->whenLoaded('blocks')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
