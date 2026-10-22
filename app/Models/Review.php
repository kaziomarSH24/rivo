<?php

namespace App\Models;

use App\Traits\AutoClearsCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Review extends Model
{
    use HasFactory, AutoClearsCache;

    protected $fillable = [
        'user_id',
        'order_id',
        'reviewable_type',
        'reviewable_id',
        'rating',
        'comment',
        'photos',
        'is_anonymous',
    ];

    protected $casts = [
        'rating' => 'integer',
        'photos' => 'array',
        'is_anonymous' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Cache tags of related models to invalidate on change.
     */
    public function getRelatedCacheTags(): array
    {
        return ['products', 'stores'];
    }
}