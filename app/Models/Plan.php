<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'price',
        'apple_product_id',
        'google_product_id',
        'features',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}


