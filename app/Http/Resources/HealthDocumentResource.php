<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HealthDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'file_url' => asset('storage/' . $this->file_path),
            'type' => $this->type,
            'file_size_bytes' => $this->file_size_bytes,
            'file_size_human' => round($this->file_size_bytes / 1024 / 1024, 2) . ' MB',
            'created_at' => $this->created_at->format('d M Y'),
        ];
    }
}
