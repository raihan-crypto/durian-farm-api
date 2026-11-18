<?php

namespace App\Http\Resources\Api\V1;

use App\Services\PublicTreePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreeQrCodeResource extends JsonResource
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
            'tree_id' => $this->tree_id,
            'public_token' => $this->public_token,
            'public_url' => $this->qr_code,
            'is_active' => (bool) $this->is_active,
            'generated_at' => $this->generated_at?->toISOString(),
            'public_fields' => app(PublicTreePresenter::class)->effectiveFields($this->resource),
            'uses_default_public_fields' => $this->public_fields === null,
            'image_urls' => [
                'svg' => url("/api/v1/trees/{$this->tree_id}/qr-code/image?format=svg"),
                'png' => url("/api/v1/trees/{$this->tree_id}/qr-code/image?format=png"),
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
