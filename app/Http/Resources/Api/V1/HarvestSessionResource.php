<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HarvestSessionResource extends JsonResource
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
            'season_id' => $this->season_id,
            'garden_id' => $this->garden_id,
            'harvest_date' => $this->harvest_date?->toISOString(),
            'session_name' => $this->session_name,
            'weather_condition' => $this->weather_condition,
            'notes' => $this->notes,
            'recorded_by' => $this->recorded_by,
            'season' => $this->whenLoaded('season', fn () => $this->season ? [
                'id' => $this->season->id,
                'name' => $this->season->name,
                'status' => $this->season->status,
            ] : null),
            'garden' => $this->whenLoaded('garden', fn () => $this->garden ? [
                'id' => $this->garden->id,
                'name' => $this->garden->name,
            ] : null),
            'recorder' => $this->whenLoaded('recorder', fn () => $this->recorder ? [
                'id' => $this->recorder->id,
                'name' => $this->recorder->name,
            ] : null),
            'records_count' => $this->when(isset($this->records_count), fn () => (int) $this->records_count),
            'total_fruits' => $this->when(isset($this->records_sum_fruit_count), fn () => (int) ($this->records_sum_fruit_count ?? 0)),
            'total_weight_kg' => $this->when(isset($this->records_sum_total_weight_kg), fn () => round((float) ($this->records_sum_total_weight_kg ?? 0), 3)),
            'records' => HarvestRecordResource::collection($this->whenLoaded('records')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
