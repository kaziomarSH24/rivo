<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoClearsCache;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pet extends Model
{
    use HasFactory, AutoClearsCache;

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'breed',
        'age_years',
        'age_months',
        'weight',
        'gender',
        'photo',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walkStats()
    {
        return $this->hasOne(PetWalkStat::class);
    }

    public function walkSessions()
    {
        return $this->hasMany(WalkSession::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function careTasks(): HasMany
    {
        return $this->hasMany(CareTask::class);
    }

    public function careTaskLogs(): HasMany
    {
        return $this->hasMany(CareTaskLog::class);
    }

    public function weightLogs(): HasMany
    {
        return $this->hasMany(PetWeightLog::class);
    }

    public function healthDocuments(): HasMany
    {
        return $this->hasMany(HealthDocument::class);
    }
}


