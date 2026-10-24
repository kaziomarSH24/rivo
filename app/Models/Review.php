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

    protected static function booted(): void
    {
        // When a review is deleted, clean up physical files and recalculate ratings
        static::deleted(function (Review $review) {
            // 1. Delete physical photo files from disk
            if (!empty($review->photos) && is_array($review->photos)) {
                foreach ($review->photos as $photoPath) {
                    if ($photoPath) {
                        $cleanPath = str_replace('/storage/', '', parse_url($photoPath, PHP_URL_PATH) ?? $photoPath);
                        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($cleanPath)) {
                            \Illuminate\Support\Facades\Storage::disk('public')->delete($cleanPath);
                        }
                    }
                }
            }

            // 2. Recalculate parent Product or Store rating and reviews_count
            $reviewable = $review->reviewable;
            if ($reviewable) {
                $avg = $reviewable->reviews()->avg('rating') ?: 5.0;
                $count = $reviewable->reviews()->count();
                $reviewable->update([
                    'rating' => round($avg, 1),
                    'reviews_count' => $count,
                ]);

                $table = $reviewable->getTable();
                \Illuminate\Support\Facades\Cache::tags(['reviews', "{$table}:{$reviewable->id}", $table])->flush();
            }
        });
    }

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