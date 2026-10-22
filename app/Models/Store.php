<?php

namespace App\Models;

use App\Traits\AutoClearsCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes, AutoClearsCache;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'type',
        'owner_name',
        'phone',
        'email',
        'street_address',
        'city',
        'state',
        'zip_code',
        'latitude',
        'longitude',
        'logo',
        'banner',
        'rating',
        'reviews_count',
        'fulfillment_rate',
        'avg_prep_time_minutes',
        'delivery_fee',
        'delivery_time_text',
        'is_verified',
        'verification_status',
        'is_live',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'rating' => 'float',
        'delivery_fee' => 'float',
        'fulfillment_rate' => 'float',
        'reviews_count' => 'integer',
        'avg_prep_time_minutes' => 'integer',
        'is_verified' => 'boolean',
        'is_live' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StoreDocument::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
    public function getRelatedCacheTags(): array
    {
        return ['products'];
    }
}
