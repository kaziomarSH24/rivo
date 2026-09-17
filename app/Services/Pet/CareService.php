<?php

namespace App\Services\Pet;

use App\Models\CareTask;
use App\Models\CareTaskLog;
use App\Models\Pet;
use App\Models\PetWalkStat;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CareService
{
    public function getCareDashboard(Pet $pet): array
    {
        $tasks = $pet->careTasks()->get();
        $todayLogs = $pet->careTaskLogs()
            ->whereDate('completed_at', Carbon::today())
            ->get();

        $completedTaskIds = $todayLogs->pluck('care_task_id')->toArray();
        $xpEarnedToday = $todayLogs->sum('xp_earned');

        $weeklyLogs = $pet->careTaskLogs()
            ->whereBetween('completed_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->get();
        $xpEarnedWeekly = $weeklyLogs->sum('xp_earned');

        // Attach dynamic flag so Resource can use it
        $tasks->each(function ($task) use ($completedTaskIds) {
            $task->is_completed_today = in_array($task->id, $completedTaskIds);
        });

        $totalTasks = $tasks->count();
        $completedCount = count($completedTaskIds);
        $progress = $totalTasks > 0 ? round(($completedCount / $totalTasks) * 100) : 0;

        $stat = $pet->walkStats()->firstOrCreate(
            ['pet_id' => $pet->id],
            ['total_xp' => 0, 'current_streak_days' => 0]
        );

        $levelInfo = $this->calculateLevelInfo($stat->total_xp);

        return [
            'summary' => [
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedCount,
                'progress_percentage' => $progress,
                'xp_earned_today' => $xpEarnedToday,
                'xp_earned_weekly' => $xpEarnedWeekly,
                'streak_days' => $stat->current_streak_days,
                'level' => $levelInfo['level_name'],
                'current_xp' => $levelInfo['current_xp'],
                'next_level_xp' => $levelInfo['next_level_xp'],
            ],
            'tasks' => $tasks
        ];
    }

    public function createTask(Pet $pet, array $data): CareTask
    {
        return $pet->careTasks()->create([
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'frequency' => $data['frequency'] ?? 'Every Day',
            'preferred_time' => $data['preferred_time'] ?? null,
            'is_reminder_on' => $data['is_reminder_on'] ?? true,
        ]);
    }

    public function completeTask(Pet $pet, CareTask $careTask): ?CareTaskLog
    {
        $alreadyCompleted = CareTaskLog::where('care_task_id', $careTask->id)
            ->whereDate('completed_at', Carbon::today())
            ->exists();

        if ($alreadyCompleted) {
            return null;
        }

        $xpEarned = config("care.xp_rewards.{$careTask->type}", config('care.xp_rewards.default', 10));

        $log = CareTaskLog::create([
            'care_task_id' => $careTask->id,
            'pet_id' => $pet->id,
            'xp_earned' => $xpEarned,
            'completed_at' => now(),
        ]);

        $stat = $pet->walkStats()->firstOrCreate(
            ['pet_id' => $pet->id],
            ['total_xp' => 0, 'current_streak_days' => 0]
        );
        $stat->total_xp += $xpEarned;
        $stat->current_level = floor($stat->total_xp / 1000) + 1;
        $stat->save();

        return $log;
    }

    private function calculateLevelInfo(int $totalXp): array
    {
        $level = floor($totalXp / 1000) + 1;
        $nextLevelXp = $level * 1000;
        
        $titles = [
            1 => 'Beginner Pup',
            5 => 'Active Pet',
            10 => 'Paw Master',
            20 => 'Legendary Pet'
        ];
        
        $title = 'Beginner Pup';
        foreach (array_reverse($titles, true) as $reqLevel => $t) {
            if ($level >= $reqLevel) {
                $title = $t;
                break;
            }
        }

        return [
            'level_name' => "{$title} - Level {$level}",
            'current_xp' => $totalXp,
            'next_level_xp' => $nextLevelXp,
        ];
    }
}


