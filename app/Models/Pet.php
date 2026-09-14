<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoClearsCache;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
