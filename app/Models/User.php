<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Traits\AutoClearsCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Cashier\Billable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens, Billable, LogsActivity;

    use AutoClearsCache; // Magic Starts Here!

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'country_code',
        'password',
        'otp',
        'otp_expires_at',
        'verification_token',
        'email_verified_at',
        'is_active',
        'fcm_token',
        'provider_name',
        'provider_id',
        'avatar',
        'plan_id',
        'revenuecat_app_user_id',
        'subscription_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp',
        'verification_token',
        'fcm_token',
        'otp_expires_at'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function fcmTokens()
    {
        return $this->hasMany(FcmToken::class);
    }

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }

    public function walkRoutes()
    {
        return $this->hasMany(WalkRoute::class);
    }

    // Activity Log Configuration, it's also customizable
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email']) //customize the fields you want to log
            ->logOnlyDirty() //log only the changed fields
            ->setDescriptionForEvent(fn(string $eventName) => "User has been {$eventName}")
            ->useLogName('user_activity');
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}

