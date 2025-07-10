<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'media_type' => $this->media_type,
            'url' => $this->url,
            'thumbnail_url' => $this->when($this->isImage(), $this->thumbnail_url),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->file_size_formatted,
            'sort_order' => $this->sort_order,
            'dimensions' => $this->when($this->metadata && isset($this->metadata['dimensions']), function () {
                return $this->metadata['dimensions'];
            }),
            'alt_text' => $this->when($this->metadata && isset($this->metadata['alt_text']), function () {
                return $this->metadata['alt_text'];
            }),
            'created_at' => $this->created_at->toISOString(),

            'media_path' => $this->when($this->shouldShowAdminData($request), $this->media_path),
            'metadata' => $this->when($this->shouldShowAdminData($request), $this->metadata),
        ];
    }

    protected function shouldShowAdminData(Request $request): bool
    {
        $user = $request->user();
        return $user && $user->hasRole(['super admin', 'admin']);
    }
}
