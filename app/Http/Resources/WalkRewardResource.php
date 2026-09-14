<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalkRewardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'required_streak_days' => $this->required_streak_days,
            'bonus_xp' => $this->bonus_xp,
            'reward_title' => $this->reward_title,
        ];
    }
}
