<?php
namespace App\Http\Resources\Chat;

use Carbon\Carbon;

trait FormatsTimestamps
{
    /**
     * Timestamp-ke Messenger-er moto format kore.
     */
    private function formatTimestamp(Carbon $timestamp): string
    {
        if ($timestamp->isToday()) {
            return $timestamp->format('h:i A'); // 4:43 PM
        }

        if ($timestamp->isYesterday()) {
            return 'Yesterday';
        }

        if ($timestamp->diffInDays(now()) < 7) {
            return $timestamp->format('l'); // Friday
        }

        return $timestamp->format('M j'); // Aug 22
    }
}
