<?php

namespace App\Services;

use App\Models\WalkSession;
use App\Models\PetWalkStat;
use App\Models\WalkReward;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WalkService extends BaseService
{
    protected string $modelClass = WalkSession::class;

    protected function getAllowedFilters(): array
    {
        return ['pet_id', 'route_id', 'start_time'];
    }

    protected function getAllowedIncludes(): array
    {
        return ['pet', 'route'];
    }

    protected function getAllowedSorts(): array
    {
        return ['created_at', 'start_time', 'distance_km', 'duration_seconds', 'calories'];
    }

    /**
     * Complete a walk and calculate XP/Streak.
     */
    public function endWalk(int $petId, array $data): array
    {
        return DB::transaction(function () use ($petId, $data) {
            // 1. Calculate Base XP (50 XP per km)
            $distanceKm = $data['distance_km'] ?? 0;
            $baseXp = (int) round($distanceKm * 50);
            $data['xp_earned'] = $baseXp;
            $data['pet_id'] = $petId;
            $data['end_time'] = $data['end_time'] ?? now();

            // Save Walk Session
            $walk = $this->create($data);

            // 2. Update Pet Stats
            $stat = PetWalkStat::firstOrCreate(
                ['pet_id' => $petId],
                [
                    'daily_distance_goal_km' => 2.50,
                    'total_xp' => 0,
                    'current_level' => 0,
                    'current_streak_days' => 0,
                    'longest_streak_days' => 0,
                ]
            );

            $today = Carbon::parse($data['end_time'])->startOfDay();
            $lastWalk = $stat->last_walk_date ? Carbon::parse($stat->last_walk_date)->startOfDay() : null;

            $streakUpdated = false;
            $streakBonusXp = 0;
            $levelUp = false;
            $newRewardTitle = null;

            if (!$lastWalk || $lastWalk->lessThan($today)) {
                // First walk of the day
                if (!$lastWalk || $lastWalk->diffInDays($today) === 1) {
                    $stat->current_streak_days += 1;
                } elseif ($lastWalk->diffInDays($today) > 1) {
                    $stat->current_streak_days = 1; // reset streak
                }
                
                $stat->last_walk_date = $today;
                $streakUpdated = true;

                if ($stat->current_streak_days > $stat->longest_streak_days) {
                    $stat->longest_streak_days = $stat->current_streak_days;
                }

                // Check for Level Up based on streak days
                $reward = WalkReward::where('required_streak_days', $stat->current_streak_days)->first();
                if ($reward && $reward->level > $stat->current_level) {
                    $stat->current_level = $reward->level;
                    $streakBonusXp = $reward->bonus_xp;
                    $levelUp = true;
                    $newRewardTitle = $reward->reward_title;
                }
            }

            // Total XP for this walk (Base + Bonus)
            $totalXpEarned = $baseXp + $streakBonusXp;
            
            // Update walk session with total XP (including bonus if any)
            $walk->update(['xp_earned' => $totalXpEarned]);

            // Add to Pet's total XP
            $stat->total_xp += $totalXpEarned;
            $stat->save();

            return [
                'walk' => $walk,
                'stat' => $stat,
                'level_up' => $levelUp,
                'bonus_xp' => $streakBonusXp,
                'new_reward' => $newRewardTitle
            ];
        });
    }

    public function getPetStatistics(int $petId)
    {
        $last7Days = now()->subDays(6)->startOfDay();
        
        $dailyData = WalkSession::where('pet_id', $petId)
            ->where('start_time', '>=', $last7Days)
            ->selectRaw('DATE(start_time) as date, SUM(distance_km) as total_distance, SUM(calories) as total_calories, COUNT(*) as walks_count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $totalWeekDistance = $dailyData->sum('total_distance');
        $totalWeekCalories = $dailyData->sum('total_calories');
        $totalWeekWalks = $dailyData->sum('walks_count');

        return [
            'daily_data' => $dailyData,
            'summary' => [
                'week_distance_km' => round($totalWeekDistance, 2),
                'week_calories' => $totalWeekCalories,
                'week_walks' => $totalWeekWalks,
            ]
        ];
    }
}
