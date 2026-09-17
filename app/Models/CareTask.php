<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoClearsCache;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareTask extends Model
{
    use HasFactory, AutoClearsCache;

    protected $fillable = [
        'pet_id',
        'type',
        'title',
        'description',
        'frequency',
        'preferred_time',
        'is_reminder_on'
    ];

    protected $casts = [
        'is_reminder_on' => 'boolean',
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CareTaskLog::class);
    }
}
