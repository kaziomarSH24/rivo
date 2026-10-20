<?php

namespace App\Services\Pet;

use App\Models\CareTaskLog;
use App\Models\Pet;
use App\Models\PetWalkStat;
use App\Models\WalkSession;

class PetBadgeService
{
    /**
     * All badge definitions in Rivo
     */
    public function getAllBadgeDefinitions(): array
    {
        return [
            [
                'id' => 'walk_warrior',
                'title' => 'Walk Warrior',
                'subtitle' => 'Walked 50 km',
                'description' => 'Complete a total of 50 km across all walk sessions.',
                'icon' => 'medal_bronze_paw',
                'category' => 'walk',
                'target' => 50,
                'metric' => 'km',
            ],
            [
                'id' => 'streak_master',
                'title' => 'Streak Master',
                'subtitle' => '7-day walk streak',
                'description' => 'Maintain an active daily walking streak for 7 continuous days.',
                'icon' => 'medal_fire',
                'category' => 'walk',
                'target' => 7,
                'metric' => 'days',
            ],
            [
                'id' => 'trail_runner',
                'title' => 'Trail Runner',
                'subtitle' => 'Level 2 reached',
                'description' => 'Reach Level 2 in Walk Mode by exploring trails and earning XP.',
                'icon' => 'medal_bronze_paw',
                'category' => 'achievement',
                'target' => 2,
                'metric' => 'level',
            ],
            [
                'id' => 'training_star',
                'title' => 'Training Star',
                'subtitle' => 'Completed 10 sessions',
                'description' => 'Successfully complete 10 care or training activity tasks.',
                'icon' => 'medal_silver_star',
                'category' => 'care',
                'target' => 10,
                'metric' => 'sessions',
            ],
            [
                'id' => 'care_hero',
                'title' => 'Care Hero',
                'subtitle' => 'Completed 5 care days',
                'description' => 'Log care tasks across at least 5 different days.',
                'icon' => 'medal_gold_heart',
                'category' => 'care',
                'target' => 5,
                'metric' => 'days',
            ],
            [
                'id' => 'marathon_master',
                'title' => 'Marathon Master',
                'subtitle' => 'Walk 100 km',
                'description' => 'Accumulate a monumental 100 km of trail and park exploration.',
                'icon' => 'medal_gold_cup',
                'category' => 'walk',
                'target' => 100,
                'metric' => 'km',
            ],
        ];
    }

    /**
     * Compute badge progress and earned status for a given pet
     */
    public function getPetBadges(Pet $pet): array
    {
        $displayedIds = is_array($pet->displayed_badges) ? $pet->displayed_badges : [];

        $totalKm = (float) WalkSession::where('pet_id', $pet->id)->sum('distance_km');
        $walkStat = PetWalkStat::where('pet_id', $pet->id)->first();
        $longestStreak = $walkStat ? $walkStat->longest_streak_days : 0;
        $currentLevel = $walkStat ? $walkStat->current_level : 0;
        $completedTasksCount = CareTaskLog::where('pet_id', $pet->id)->count();
        $uniqueCareDays = CareTaskLog::where('pet_id', $pet->id)->distinct()->count('completed_at');

        $earned = [];
        $locked = [];

        foreach ($this->getAllBadgeDefinitions() as $def) {
            $currentValue = 0;
            $isEarned = false;

            switch ($def['id']) {
                case 'walk_warrior':
                    $currentValue = round($totalKm, 1);
                    $isEarned = $totalKm >= 50;
                    break;
                case 'marathon_master':
                    $currentValue = round($totalKm, 1);
                    $isEarned = $totalKm >= 100;
                    break;
                case 'streak_master':
                    $currentValue = $longestStreak;
                    $isEarned = $longestStreak >= 7;
                    break;
                case 'trail_runner':
                    $currentValue = $currentLevel;
                    $isEarned = $currentLevel >= 2;
                    break;
                case 'training_star':
                    $currentValue = $completedTasksCount;
                    $isEarned = $completedTasksCount >= 10;
                    break;
                case 'care_hero':
                    $currentValue = $uniqueCareDays ?: $completedTasksCount;
                    $isEarned = ($uniqueCareDays >= 5) || ($completedTasksCount >= 5);
                    break;
            }

            $badgeData = [
                'id' => $def['id'],
                'title' => $def['title'],
                'subtitle' => $def['subtitle'],
                'description' => $def['description'],
                'icon' => $def['icon'],
                'category' => $def['category'],
                'progress' => "{$currentValue}/{$def['target']} {$def['metric']}",
                'current_value' => $currentValue,
                'target_value' => $def['target'],
                'is_displayed' => in_array($def['id'], $displayedIds),
            ];

            if ($isEarned) {
                $earned[] = $badgeData;
            } else {
                $locked[] = $badgeData;
            }
        }

        return [
            'selected_count' => count($displayedIds),
            'max_display' => 3,
            'earned' => $earned,
            'locked' => $locked,
        ];
    }

    /**
     * Format achievements object with 3 fixed slots for pet profile
     */
    public function getProfileAchievements(Pet $pet): array
    {
        $displayedIds = is_array($pet->displayed_badges) ? array_slice($pet->displayed_badges, 0, 3) : [];
        $definitions = collect($this->getAllBadgeDefinitions())->keyBy('id');

        $slots = [];
        for ($i = 0; $i < 3; $i++) {
            if (isset($displayedIds[$i]) && isset($definitions[$displayedIds[$i]])) {
                $def = $definitions[$displayedIds[$i]];
                $slots[] = [
                    'id' => $def['id'],
                    'title' => $def['title'],
                    'subtitle' => $def['subtitle'],
                    'icon' => $def['icon'],
                    'category' => $def['category'],
                ];
            } else {
                $slots[] = null;
            }
        }

        return [
            'displayed_count' => count(array_filter($slots)),
            'max_slots' => 3,
            'slots' => $slots,
        ];
    }

    /**
     * Save up to 3 displayed badges
     */
    public function updateDisplayedBadges(Pet $pet, array $badgeIds): array
    {
        $badgeIds = array_values(array_unique(array_slice($badgeIds, 0, 3)));
        $earnedList = collect($this->getPetBadges($pet)['earned'])->pluck('id')->toArray();
        $validBadgeIds = array_intersect($badgeIds, $earnedList);

        $pet->displayed_badges = array_values($validBadgeIds);
        $pet->save();

        return $this->getProfileAchievements($pet);
    }
}

