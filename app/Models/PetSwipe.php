<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetSwipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'pet_id',
        'target_pet_id',
        'action',
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class, 'pet_id');
    }

    public function targetPet(): BelongsTo
    {
        return $this->belongsTo(Pet::class, 'target_pet_id');
    }
}
