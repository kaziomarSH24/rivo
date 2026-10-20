<?php

namespace App\Models;

use App\Traits\AutoClearsCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecentSearch extends Model
{
    use HasFactory, AutoClearsCache;

    protected $fillable = [
        'user_id',
        'query',
        'type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}