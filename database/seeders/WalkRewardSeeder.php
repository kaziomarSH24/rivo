<?php

namespace Database\Seeders;

use App\Models\WalkReward;
use Illuminate\Database\Seeder;

class WalkRewardSeeder extends Seeder
{
    public function run(): void
    {
        $rewards = [
            ['level' => 1, 'required_streak_days' => 3, 'bonus_xp' => 20, 'reward_title' => 'Level 1'],
            ['level' => 2, 'required_streak_days' => 7, 'bonus_xp' => 50, 'reward_title' => 'Level 2'],
            ['level' => 3, 'required_streak_days' => 14, 'bonus_xp' => 100, 'reward_title' => 'Level 3'],
            ['level' => 4, 'required_streak_days' => 30, 'bonus_xp' => 200, 'reward_title' => 'Level 4'],
            ['level' => 5, 'required_streak_days' => 100, 'bonus_xp' => 500, 'reward_title' => 'Level 5'],
        ];

        foreach ($rewards as $reward) {
            WalkReward::updateOrCreate(
                ['level' => $reward['level']],
                $reward
            );
        }
    }
}
