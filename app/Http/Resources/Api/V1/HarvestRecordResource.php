<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HarvestRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fruitCount = (int) $this->fruit_count;
        $totalWeight = (float) $this->total_weight_kg;

        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'garden_id' => $this->garden_id,
            'tree_id' => $this->tree_id,
            'fruit_count' => $fruitCount,
            'total_weight_kg' => round($totalWeight, 3),
            'average_weight_per_fruit_kg' => $fruitCount > 0 ? round($totalWeight / $fruitCount, 3) : 0.0,
            'notes' => $this->notes,
            'session' => $this->whenLoaded('session', fn () => $this->session ? [
                'id' => $this->session->id,
                'season_id' => $this->session->season_id,
                'harvest_date' => $this->session->harvest_date?->toISOString(),
                'session_name' => $this->session->session_name,
            ] : null),
            'garden' => $this->whenLoaded('garden', fn () => $this->garden ? [
                'id' => $this->garden->id,
                'name' => $this->garden->name,
            ] : null),
            'tree' => $this->whenLoaded('tree', fn () => $this->tree ? [
                'id' => $this->tree->id,
                'tree_code' => $this->tree->tree_code,
                'tree_name' => $this->tree->tree_name,
                'variety_name' => $this->tree->relationLoaded('variety') ? $this->tree->variety?->name : null,
            ] : null),
            'details' => $this->whenLoaded('details', fn () => $this->details->map(fn ($detail) => [
                'id' => $detail->id,
                'fruit_grade' => $detail->fruit_grade,
                'fruit_count' => (int) $detail->fruit_count,
                'weight_kg' => round((float) $detail->weight_kg, 3),
                'notes' => $detail->notes,
            ])->values()),
            'photos' => ActivityPhotoResource::collection($this->whenLoaded('photos')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
