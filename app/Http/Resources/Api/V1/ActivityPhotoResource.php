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
        $disk = Storage::disk((string) config('durian.uploads.disk', 'public'));

        return [
            'id' => $this->id,
            'activity_type' => $this->activity_type,
            'activity_id' => $this->activity_id,
            'file_name' => $this->file_name,
            'caption' => $this->caption,
            'url' => $disk->url($this->file_path),
            'thumbnail_url' => $this->thumbnail_path ? $disk->url($this->thumbnail_path) : null,
            'mime_type' => $this->mime_type,
            'file_size_bytes' => $this->file_size_bytes,
            'width' => $this->width,
            'height' => $this->height,
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
