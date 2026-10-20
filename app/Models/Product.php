<?php

namespace App\Models;

use App\Traits\AutoClearsCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, AutoClearsCache;

    protected $fillable = [
        'store_id',
        'category_id',
        'brand_id',
        'name',
        'slug',
        'description',
        'pet_type',
        'price',
        'discount_price',
        'sku',
        'stock',
        'low_stock_threshold',
        'is_active',
        'is_online',
        'is_instore',
        'images',
        'rating',
        'reviews_count',
        'sales_count',
    ];

    protected $casts = [
        'price' => 'float',
        'discount_price' => 'float',
        'stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'is_active' => 'boolean',
        'is_online' => 'boolean',
        'is_instore' => 'boolean',
        'images' => 'array',
        'rating' => 'float',
        'reviews_count' => 'integer',
        'sales_count' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('stock', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock', '<=', 0);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
}