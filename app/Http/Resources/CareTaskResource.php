<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class CareTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'frequency' => $this->frequency,
            'preferred_time' => $this->preferred_time ? Carbon::parse($this->preferred_time)->format('h:i A') : null,
            'is_completed_today' => $this->is_completed_today ?? false,
            'xp_reward' => config("care.xp_rewards.{$this->type}", config('care.xp_rewards.default', 10)),
        ];
    }
}


