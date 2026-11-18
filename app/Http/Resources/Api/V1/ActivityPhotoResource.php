<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ActivityPhotoResource extends JsonResource
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
            'activity_type' => $this->activity_type,
            'activity_id' => $this->activity_id,
            'file_name' => $this->file_name,
            'caption' => $this->caption,
            'url' => Storage::disk((string) config('durian.uploads.disk', 'public'))->url($this->file_path),
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
