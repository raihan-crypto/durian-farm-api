<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DurianVarietyResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'origin' => $this->origin,
            'description' => $this->description,
            'characteristics' => $this->characteristics,
            'photo_url' => $this->photo_path
                ? Storage::disk((string) config('durian.uploads.disk', 'public'))->url($this->photo_path)
                : null,
            'is_active' => (bool) ($this->is_active ?? true),
            'trees_count' => $this->when(isset($this->trees_count), fn () => (int) $this->trees_count),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
