<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalkRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'distance_km',
        'est_duration_minutes',
        'route_coordinates',
        'is_favorite',
    ];

    protected $casts = [
        'route_coordinates' => 'array',
        'is_favorite' => 'boolean',
        'distance_km' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
