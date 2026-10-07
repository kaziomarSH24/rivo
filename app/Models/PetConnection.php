<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\AutoClearsCache;

class PetConnection extends Model
{
    use HasFactory, AutoClearsCache;

    protected $fillable = [
        'pet_id',
        'connected_pet_id',
        'status',
        'source',
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class, 'pet_id');
    }

    public function connectedPet(): BelongsTo
    {
        return $this->belongsTo(Pet::class, 'connected_pet_id');
    }
}
