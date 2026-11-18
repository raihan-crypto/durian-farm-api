<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HarvestSeasonResource extends JsonResource
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
            'garden_id' => $this->garden_id,
            'name' => $this->name,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'garden' => new GardenResource($this->whenLoaded('garden')),
            'sessions_count' => $this->when(isset($this->sessions_count), fn () => (int) $this->sessions_count),
            'sessions' => HarvestSessionResource::collection($this->whenLoaded('sessions')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
