<?php

namespace App\Http\Resources\Social;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Pet\PetResource;
use App\Http\Resources\Walk\WalkRouteResource;

class PostWalkSummaryResource extends JsonResource
{
    /**
     * Transform the walk session resource into an array for social feed cards.
     */
    public function toArray(Request $request): array
    {
        $durationSeconds = (int) ($this->duration_seconds ?? 0);
        $distanceKm = (float) ($this->distance_km ?? 0);

        // Calculate pace: e.g. 12:23 /km
        $paceFormatted = null;
        if ($distanceKm > 0 && $durationSeconds > 0) {
            $secondsPerKm = $durationSeconds / $distanceKm;
            $paceMinutes = (int) floor($secondsPerKm / 60);
            $paceSeconds = (int) round(fmod($secondsPerKm, 60));
            $paceFormatted = sprintf('%d:%02d /km', $paceMinutes, $paceSeconds);
        }

        // Duration in human readable form (e.g. 52 min, 1 hr 15 min)
        $durationHuman = null;
        if ($durationSeconds > 0) {
            $hours = (int) floor($durationSeconds / 3600);
            $mins = (int) round(fmod($durationSeconds, 3600) / 60);
            if ($hours > 0) {
                $durationHuman = $mins > 0 ? "{$hours} hr {$mins} min" : "{$hours} hr";
            } else {
                $durationHuman = "{$mins} min";
            }
        }

        return [
            'id' => $this->id,
            'pet_id' => $this->pet_id,
            'route_id' => $this->route_id,
            'distance_km' => $distanceKm,
            'distance_formatted' => $distanceKm . ' km',
            'duration_seconds' => $durationSeconds,
            'duration_formatted' => gmdate('H:i:s', $durationSeconds),
            'duration_human' => $durationHuman,
            'pace' => $paceFormatted,
            'steps' => $this->steps,
            'calories' => $this->calories,
            'calories_formatted' => $this->calories ? $this->calories . ' kcal' : null,
            'avg_speed_kmh' => (float) $this->avg_speed_kmh,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'route_coordinates' => $this->route_coordinates,
            'xp_earned' => $this->xp_earned,
            'route' => new WalkRouteResource($this->whenLoaded('route')),
            'pet' => new PetResource($this->whenLoaded('pet')),
            'created_at' => $this->created_at,
        ];
    }
}
