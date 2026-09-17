<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Pet;
use App\Models\PetWeightLog;
use App\Models\HealthDocument;
use Carbon\Carbon;

class HealthVaultSeeder extends Seeder
{
    public function run(): void
    {
        $pet = Pet::first();
        if (!$pet) {
            return;
        }

        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        PetWeightLog::truncate();
        HealthDocument::truncate();
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Weight Logs (simulate 6 months)
        for ($i = 6; $i >= 0; $i--) {
            PetWeightLog::create([
                'pet_id' => $pet->id,
                'weight_kg' => 20.0 + (rand(-10, 15) / 10), // Random between 19.0 and 21.5
                'logged_at' => Carbon::now()->subMonths($i)->startOfMonth(),
            ]);
        }
        
        $pet->update(['weight' => 20.4]);

        // Health Documents
        HealthDocument::create([
            'pet_id' => $pet->id,
            'title' => 'Vaccination Card',
            'file_path' => 'health_documents/dummy_vaccination.pdf',
            'type' => 'pdf',
            'file_size_bytes' => 1258291 // 1.2 MB
        ]);

        HealthDocument::create([
            'pet_id' => $pet->id,
            'title' => 'Blood Test Report',
            'file_path' => 'health_documents/dummy_bloodtest.pdf',
            'type' => 'pdf',
            'file_size_bytes' => 860160 // 840 KB
        ]);
    }
}
