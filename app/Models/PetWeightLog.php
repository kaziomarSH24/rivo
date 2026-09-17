<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetWeightLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'pet_id',
        'weight_kg',
        'logged_at'
    ];

    protected $casts = [
        'logged_at' => 'datetime',
        'weight_kg' => 'float',
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }
}
