<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LostPetReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'pet_id',
        'user_id',
        'status',
        'last_seen_date',
        'last_seen_time',
        'last_seen_location',
        'latitude',
        'longitude',
        'additional_info',
        'reward_amount',
        'last_seen_photo',
    ];

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
