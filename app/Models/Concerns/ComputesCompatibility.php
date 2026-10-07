<?php

namespace App\Models\Concerns;

use App\Models\Pet;

trait ComputesCompatibility
{
    public function calculateCompatibilityWith(Pet $otherPet): int
    {
        $score = 50; // Base score

        // Breed Match (+15)
        if (!empty($this->breed) && !empty($otherPet->breed) && $this->breed === $otherPet->breed) {
            $score += 15;
        }

        // Energy Level Match (+15)
        if (!empty($this->energy_level) && !empty($otherPet->energy_level) && $this->energy_level === $otherPet->energy_level) {
            $score += 15;
        }

        // Traits Overlap (+ up to 15)
        $myTraits = is_array($this->traits) ? $this->traits : [];
        $otherTraits = is_array($otherPet->traits) ? $otherPet->traits : [];
        if (count($myTraits) > 0 && count($otherTraits) > 0) {
            $common = array_intersect($myTraits, $otherTraits);
            $overlapPercent = count($common) / max(count($myTraits), count($otherTraits));
            $score += (int) ($overlapPercent * 15);
        }

        // Type match (Dog vs Dog) - MUST match, if not maybe score drops? 
        // We assume they only discover same type usually, but if not:
        if ($this->type !== $otherPet->type) {
            $score -= 30; // Dogs and Cats might not be a 95% match
        } else {
            $score += 5; // Same species bump
        }

        return min(max($score, 10), 99); // Clamp between 10% and 99%
    }
}
