<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalkSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'pet_id',
        'route_id',
        'distance_km',
        'duration_seconds',
        'steps',
        'calories',
        'avg_speed_kmh',
        'start_time',
        'end_time',
        'route_coordinates',
        'xp_earned',
    ];

    protected $casts = [
        'route_coordinates' => 'array',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'distance_km' => 'decimal:2',
        'avg_speed_kmh' => 'decimal:2',
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(WalkRoute::class, 'route_id');
    }
}
