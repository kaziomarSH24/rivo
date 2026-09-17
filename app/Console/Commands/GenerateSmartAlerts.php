<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Pet;
use App\Models\WalkSession;
use App\Models\User;
use App\Notifications\SmartAlertNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateSmartAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:generate-smart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate smart alerts for users based on pet activity and upcoming appointments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Smart Alerts Generation...');

        // Process users with pets
        User::with('pets')->chunk(100, function ($users) {
            foreach ($users as $user) {
                if (!$user->pets->count()) continue;

                foreach ($user->pets as $pet) {
                    $this->checkVaccinationAlerts($user, $pet);
                    $this->checkActivityAlerts($user, $pet);
                }
            }
        });

        $this->info('Smart Alerts generated successfully.');
        return Command::SUCCESS;
    }

    protected function checkVaccinationAlerts(User $user, Pet $pet)
    {
        // Check for upcoming vaccinations (appointments with type 'vaccine' in the next 14 days)
        $upcomingVaccines = Appointment::where('pet_id', $pet->id)
            ->where('status', 'upcoming')
            ->where('type', 'vaccination')
            ->whereBetween('datetime', [Carbon::now(), Carbon::now()->addDays(14)])
            ->get();

        foreach ($upcomingVaccines as $vaccine) {
            $daysLeft = (int) ceil(Carbon::now()->floatDiffInDays($vaccine->datetime, false));
            if ($daysLeft < 0) $daysLeft = 0;
            
            // Check if we already notified for this recently to avoid spam (simple check)
            $hasNotified = $user->notifications()
                ->where('type', SmartAlertNotification::class)
                ->where('data->title', 'Vaccination due soon')
                ->where('data->message', 'like', "%{$pet->name}%")
                ->where('created_at', '>', Carbon::now()->subDays(3)) // only notify once every 3 days
                ->exists();

            if (!$hasNotified) {
                $user->notify(new SmartAlertNotification(
                    'Vaccination due soon',
                    "{$vaccine->title} for {$pet->name} in {$daysLeft} days",
                    'vaccination',
                    "rivo://pets/{$pet->id}/vaccinations/{$vaccine->id}", // Deep link
                    "vaccine_icon" // Icon identifier
                ));
            }
        }
    }

    protected function checkActivityAlerts(User $user, Pet $pet)
    {
        // Calculate steps in last 7 days vs previous 7 days
        $last7DaysSteps = WalkSession::where('pet_id', $pet->id)
            ->whereBetween('start_time', [Carbon::now()->subDays(7), Carbon::now()])
            ->sum('steps');

        $previous7DaysSteps = WalkSession::where('pet_id', $pet->id)
            ->whereBetween('start_time', [Carbon::now()->subDays(14), Carbon::now()->subDays(7)])
            ->sum('steps');

        // Only alert if there is a significant drop (e.g., < 60% of previous week) and previous week had meaningful activity
        if ($previous7DaysSteps > 1000 && $last7DaysSteps < ($previous7DaysSteps * 0.6)) {
            
            $dropPercentage = round(((1 - ($last7DaysSteps / $previous7DaysSteps)) * 100));

            $hasNotified = $user->notifications()
                ->where('type', SmartAlertNotification::class)
                ->where('data->title', 'Activity below average')
                ->where('data->message', 'like', "%{$pet->name}%")
                ->where('created_at', '>', Carbon::now()->subDays(7)) // only notify once a week
                ->exists();

            if (!$hasNotified) {
                $user->notify(new SmartAlertNotification(
                    'Activity below average',
                    "{$dropPercentage}% fewer steps than usual for {$pet->name}",
                    'activity',
                    "rivo://pets/{$pet->id}/activity", // Deep link
                    "activity_icon" // Icon identifier
                ));
            }
        }
    }
}



