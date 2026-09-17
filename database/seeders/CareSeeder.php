<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Pet;
use App\Models\CareTask;
use App\Models\CareTaskLog;
use Carbon\Carbon;

class CareSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pet = Pet::first();
        if (!$pet) {
            return;
        }

        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        CareTaskLog::truncate();
        CareTask::truncate();
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Create Care Tasks
        $task1 = CareTask::create([
            'pet_id' => $pet->id,
            'type' => 'food',
            'title' => 'Morning Meal',
            'description' => 'Give a healthy breakfast',
            'frequency' => 'Every Day',
            'preferred_time' => '08:00:00',
            'is_reminder_on' => true
        ]);

        $task2 = CareTask::create([
            'pet_id' => $pet->id,
            'type' => 'walk',
            'title' => 'Morning Walk',
            'description' => '30 min walk in the park',
            'frequency' => 'Every Day',
            'preferred_time' => '07:00:00',
            'is_reminder_on' => true
        ]);

        $task3 = CareTask::create([
            'pet_id' => $pet->id,
            'type' => 'food',
            'title' => 'Evening Meal',
            'description' => 'Give a healthy dinner',
            'frequency' => 'Every Day',
            'preferred_time' => '19:00:00',
            'is_reminder_on' => true
        ]);

        $task4 = CareTask::create([
            'pet_id' => $pet->id,
            'type' => 'medicine',
            'title' => 'Evening Vitamin',
            'description' => 'One pill of vitamin supplement',
            'frequency' => 'Every Day',
            'preferred_time' => '19:30:00',
            'is_reminder_on' => true
        ]);

        // Complete 2 tasks for today
        CareTaskLog::create([
            'care_task_id' => $task1->id,
            'pet_id' => $pet->id,
            'xp_earned' => 10,
            'completed_at' => Carbon::today()->setTime(8, 15)
        ]);

        CareTaskLog::create([
            'care_task_id' => $task2->id,
            'pet_id' => $pet->id,
            'xp_earned' => 20, // Walk gives 20 XP
            'completed_at' => Carbon::today()->setTime(7, 30)
        ]);

        // Create some past logs to simulate streak (yesterday)
        CareTaskLog::create([
            'care_task_id' => $task1->id,
            'pet_id' => $pet->id,
            'xp_earned' => 10,
            'completed_at' => Carbon::yesterday()->setTime(8, 10)
        ]);
        CareTaskLog::create([
            'care_task_id' => $task3->id,
            'pet_id' => $pet->id,
            'xp_earned' => 10,
            'completed_at' => Carbon::yesterday()->setTime(19, 10)
        ]);
    }
}

