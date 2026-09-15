<?php

namespace App\Services\Walk;



use App\Services\BaseService;

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

            if ($lastWalk) {
                $daysDiff = $today->diffInDays($lastWalk);
                if ($daysDiff == 1) {
                    $stat->current_streak_days += 1;
                    $streakUpdated = true;
                } elseif ($daysDiff > 1) {
                    $stat->current_streak_days = 1;
                    $streakUpdated = true;
                }
            } else {
                $stat->current_streak_days = 1;
                $streakUpdated = true;
            }

            if ($stat->current_streak_days > $stat->longest_streak_days) {
                $stat->longest_streak_days = $stat->current_streak_days;
            }

            $stat->last_walk_date = $today->toDateString();

            if ($streakUpdated) {
                $reward = WalkReward::where('required_streak_days', '<=', $stat->current_streak_days)
                    ->orderBy('required_streak_days', 'desc')
                    ->first();

                if ($reward && $reward->level > $stat->current_level) {
                    $stat->current_level = $reward->level;
                    $streakBonusXp = $reward->bonus_xp;
                    $levelUp = true;
                }
            }

            $totalXpEarned = $baseXp + $streakBonusXp;
            $stat->total_xp += $totalXpEarned;
            $stat->save();

            return [
                'walk' => $walk,
                'level_up' => $levelUp,
                'streak_updated' => $streakUpdated,
                'bonus_xp' => $streakBonusXp,
            ];
        });
    }

    public function getPetStatistics(int $petId)
    {
        $period = request('filter.period', 'week'); // 'week' or 'month'
        
        if ($period === 'month') {
            $startDate = now()->subDays(27)->startOfDay(); // 4 weeks
            // Group by week
            $walks = WalkSession::where('pet_id', $petId)
                ->where('created_at', '>=', $startDate)
                ->get();
                
            $chartData = collect();
            for ($i = 0; $i < 4; $i++) {
                $weekStart = now()->subDays(27 - ($i * 7))->startOfDay();
                $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();
                
                $weekWalks = $walks->whereBetween('created_at', [$weekStart, $weekEnd]);
                
                $chartData->push([
                    'label' => 'W' . ($i + 1),
                    'total_distance' => round($weekWalks->sum('distance_km'), 2),
                    'total_calories' => $weekWalks->sum('calories'),
                    'walks_count' => $weekWalks->count(),
                ]);
            }
            
            $totalDistance = $chartData->sum('total_distance');
            $totalCalories = $chartData->sum('total_calories');
            $totalWalks = $chartData->sum('walks_count');
            
        } else {
            // Default: 'week' (Last 7 days daily data)
            $startDate = now()->subDays(6)->startOfDay();
            
            $walks = WalkSession::where('pet_id', $petId)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, SUM(distance_km) as total_distance, SUM(calories) as total_calories, COUNT(*) as walks_count')
                ->groupBy('date')
                ->orderBy('date')
                ->get();
                
            $chartData = $walks;
            $totalDistance = $walks->sum('total_distance');
            $totalCalories = $walks->sum('total_calories');
            $totalWalks = $walks->sum('walks_count');
        }

        // 2. Patterns Data (Last 30 days)
        $last30DaysWalks = WalkSession::with('route')
            ->where('pet_id', $petId)
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->get();

        $patterns = [
            'preferred_time' => 'N/A',
            'most_active_day' => 'N/A',
            'avg_walk_distance' => 0,
            'most_walked_areas' => []
        ];

        if ($last30DaysWalks->count() > 0) {
            // Preferred Time (Morning, Afternoon, Evening)
            $times = $last30DaysWalks->map(function($w) {
                $hour = $w->created_at->hour;
                if ($hour >= 5 && $hour < 12) return 'Morning (5AM - 12PM)';
                if ($hour >= 12 && $hour < 17) return 'Afternoon (12PM - 5PM)';
                if ($hour >= 17 && $hour < 21) return 'Evening (5PM - 9PM)';
                return 'Night (9PM - 5AM)';
            });
            $patterns['preferred_time'] = $times->countBy()->sortDesc()->keys()->first();

            // Most Active Day
            $days = $last30DaysWalks->map(function($w) { return $w->created_at->format('l'); });
            $patterns['most_active_day'] = $days->countBy()->sortDesc()->keys()->first();

            // Avg Walk Distance
            $patterns['avg_walk_distance'] = round($last30DaysWalks->avg('distance_km'), 1);

            // Most Walked Areas (Group by Route)
            $totalRoutedWalks = $last30DaysWalks->whereNotNull('route_id')->count();
            if ($totalRoutedWalks > 0) {
                $routeCounts = $last30DaysWalks->whereNotNull('route_id')->groupBy('route_id');
                $areas = [];
                foreach ($routeCounts as $rId => $walksGroup) {
                    $route = $walksGroup->first()->route;
                    $percentage = round(($walksGroup->count() / $totalRoutedWalks) * 100);
                    $areas[] = [
                        'name' => $route ? $route->name : 'Unknown Route',
                        'percentage' => $percentage
                    ];
                }
                // Sort by highest percentage
                usort($areas, function($a, $b) { return $b['percentage'] <=> $a['percentage']; });
                $patterns['most_walked_areas'] = array_slice($areas, 0, 3); // Top 3
            } else {
                $patterns['most_walked_areas'] = [['name' => 'Unmapped routes', 'percentage' => 100]];
            }
        }

        return [
            'chart_data' => $chartData,
            'summary' => [
                'week_distance_km' => round($totalDistance, 2),
                'week_calories' => $totalCalories,
                'week_walks' => $totalWalks,
            ],
            'patterns' => $patterns
        ];
    }

    public function getRoutesTabInsights(int $petId, int $userId)
    {
        // 1. Summary Cards
        $last7Days = now()->subDays(6)->startOfDay();
        $weekWalks = WalkSession::where('pet_id', $petId)->where('created_at', '>=', $last7Days)->get();
        
        $weekDistance = $weekWalks->sum('distance_km');
        $weekDuration = $weekWalks->sum('duration_seconds');
        $walksLogged = $weekWalks->count();
        $avgMinutes = $walksLogged > 0 ? round(($weekDuration / 60) / $walksLogged) : 0;
        $savedRoutesCount = \App\Models\WalkRoute::where('user_id', $userId)->where('is_favorite', true)->count();

        $summary = [
            'week_distance_km' => round($weekDistance, 1),
            'avg_minutes' => $avgMinutes,
            'saved_routes_count' => $savedRoutesCount,
            'walks_logged' => $walksLogged,
        ];

        // 2. Favorite Routes with Times Walked
        $favoriteRoutes = \App\Models\WalkRoute::where('user_id', $userId)
            ->where('is_favorite', true)
            ->withCount(['walkSessions' => function ($query) use ($petId) {
                $query->where('pet_id', $petId);
            }])
            ->orderByDesc('walk_sessions_count')
            ->get();

        // 3. Suggested Walks Logic
        // Find the user's current local hour
        $currentHour = now()->hour;
        $suggestions = [];

        if ($favoriteRoutes->count() > 0) {
            // Best Time Now Logic: Find a route frequently walked around this time (+/- 3 hours)
            $bestTimeRoute = $favoriteRoutes->first(); // fallback to most walked
            $suggestions[] = [
                'suggestion_type' => 'Best Time Now',
                'title' => $bestTimeRoute->name,
                'subtitle' => 'You usually walk around this time - it\'s perfect right now',
                'distance_km' => $bestTimeRoute->distance_km,
                'est_duration_minutes' => $bestTimeRoute->est_duration_minutes,
                'match_percentage' => rand(85, 98) // Mocked AI confidence percentage
            ];

            if ($favoriteRoutes->count() > 1) {
                $eveningRoute = $favoriteRoutes->skip(1)->first();
                $suggestions[] = [
                    'suggestion_type' => $currentHour < 15 ? 'Evening Option' : 'Morning Option',
                    'title' => $eveningRoute->name,
                    'subtitle' => 'Weather looks great, similar to your last walk here',
                    'distance_km' => $eveningRoute->distance_km,
                    'est_duration_minutes' => $eveningRoute->est_duration_minutes,
                    'match_percentage' => rand(70, 85)
                ];
            }
        }

        return [
            'summary' => $summary,
            'favorite_routes' => $favoriteRoutes->map(function($route) {
                return [
                    'id' => $route->id,
                    'name' => $route->name,
                    'distance_km' => $route->distance_km,
                    'est_duration_minutes' => $route->est_duration_minutes,
                    'times_walked' => $route->walk_sessions_count,
                    'route_coordinates' => $route->route_coordinates,
                ];
            }),
            'suggested_walks' => $suggestions
        ];
    }
}



