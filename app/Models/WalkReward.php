<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalkReward extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'required_streak_days',
        'bonus_xp',
        'reward_title',
    ];
}
