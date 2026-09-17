<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'pet_id',
        'title',
        'file_path',
        'type',
        'file_size_bytes'
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }
}
