<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];
        
        // Extract a clean type string from the class name
        // e.g., App\Notifications\SmartAlertNotification -> smart_alert
        $className = class_basename($this->type);
        $fallbackType = Str::snake(str_replace('Notification', '', $className));

        return [
            'id' => $this->id,
            'type' => $data['type'] ?? $fallbackType,
            'title' => $data['title'] ?? 'New Notification',
            'message' => $data['message'] ?? '',
            
            // UI specific fields for Mobile App
            'icon' => $data['icon'] ?? null, 
            'action_url' => $data['action_url'] ?? null, 
            
            // Any additional contextual data
            'extra_data' => $data['extra_data'] ?? null,
            
            // Status and Timestamps
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at ? $this->read_at->toIso8601String() : null,
            'created_at' => $this->created_at->toIso8601String(),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }
}

