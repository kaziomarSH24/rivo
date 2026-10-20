<?php

namespace App\Models;

use App\Traits\AutoClearsCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreDocument extends Model
{
    use HasFactory, AutoClearsCache;

    protected $fillable = [
        'store_id',
        'type',
        'file_path',
        'original_name',
        'status',
        'rejection_reason',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}