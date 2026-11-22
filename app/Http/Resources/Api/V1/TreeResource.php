<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreeResource extends JsonResource
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
            'variety_id' => $this->variety_id,
            'tree_code' => $this->tree_code,
            'tree_identity_number' => $this->tree_identity_number,
            'tree_name' => $this->tree_name,
            'photo_path' => $this->photo_path,
            'photo_url' => $this->photo_path ? asset('storage/'.$this->photo_path) : null,
            'planted_at' => $this->planted_at?->toDateString(),
            'planting_age_months' => $this->planting_age_months,
            'age_months' => $this->ageInMonths(),
            'initial_diameter_cm' => $this->initial_diameter_cm !== null ? (float) $this->initial_diameter_cm : null,
            'initial_height_m' => $this->initial_height_m !== null ? (float) $this->initial_height_m : null,
            'initial_canopy_width_m' => $this->initial_canopy_width_m !== null ? (float) $this->initial_canopy_width_m : null,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'notes' => $this->notes,
            'garden' => new GardenResource($this->whenLoaded('garden')),
            'variety' => new DurianVarietyResource($this->whenLoaded('variety')),
            'latest_measurement' => new TreeMeasurementResource($this->whenLoaded('latestMeasurement')),
            'qr_code' => new TreeQrCodeResource($this->whenLoaded('qrCode')),
            'photos' => ActivityPhotoResource::collection($this->whenLoaded('photos')),
            'measurements_count' => $this->when(isset($this->measurements_count), fn () => (int) $this->measurements_count),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
