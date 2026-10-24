<?php

namespace App\Models;

use App\Traits\AutoClearsCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Order extends Model
{
    use HasFactory, AutoClearsCache;

    protected $fillable = [
        'order_number',
        'user_id',
        'store_id',
        'address_id',
        'delivery_address_json',
        'status',
        'delivery_type',
        'estimated_delivery_time',
        'subtotal',
        'delivery_fee',
        'tax',
        'discount',
        'total',
        'payment_method',
        'transaction_id',
        'payment_status',
        'payment_metadata',
        'notes',
        'placed_at',
        'delivered_at',
        'cancelled_at',
        'rejection_reason',
    ];

    protected $casts = [
        'delivery_address_json' => 'array',
        'payment_metadata' => 'array',
        'subtotal' => 'float',
        'delivery_fee' => 'float',
        'tax' => 'float',
        'discount' => 'float',
        'total' => 'float',
        'placed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'address_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function transaction(): MorphOne
    {
        return $this->morphOne(Transaction::class, 'payable');
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'payable');
    }

    public function scopeOngoing($query)
    {
        return $query->whereIn('status', ['new', 'preparing', 'ready_for_pickup', 'out_for_delivery']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function getRelatedCacheTags(): array
    {
        return [
            "stores:{$this->store_id}:orders",
            "stores:{$this->store_id}:dashboard",
            "stores:{$this->store_id}:analytics",
            "stores:{$this->store_id}",
        ];
    }
}
