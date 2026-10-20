<?php

namespace App\Http\Resources\Walk;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalkRewardResource extends JsonResource
{
    protected ?int $currentStreak = null;

    public function setCurrentStreak(int $streak): self
    {
        $this->currentStreak = $streak;
        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'required_streak_days' => $this->required_streak_days,
            'bonus_xp' => $this->bonus_xp,
            'reward_title' => $this->reward_title,
            'is_unlocked' => $this->currentStreak !== null ? ($this->currentStreak >= $this->required_streak_days) : false,
        ];
    }
}
