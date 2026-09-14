<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetWalkStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'pet_id',
        'daily_distance_goal_km',
        'total_xp',
        'current_level',
        'current_streak_days',
        'longest_streak_days',
        'last_walk_date',
    ];

    protected $casts = [
        'last_walk_date' => 'date',
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }
}
