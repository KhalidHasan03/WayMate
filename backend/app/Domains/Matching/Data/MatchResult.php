<?php

namespace App\Domains\Matching\Data;

final class MatchResult
{
    public function __construct(
        public readonly float $pickupDeviationM,
        public readonly float $dropoffDeviationM,
        public readonly float $overlapM,
        public readonly float $detourM,
        public readonly float $cosTheta,
        public readonly float $pickupProgressM,
        public readonly float $dropoffProgressM,
        public readonly int $score,
    ) {}

    public function toArray(): array
    {
        return [
            'pickup_deviation_m' => (int) round($this->pickupDeviationM),
            'dropoff_deviation_m' => (int) round($this->dropoffDeviationM),
            'overlap_m' => (int) round($this->overlapM),
            'detour_m' => (int) round($this->detourM),
            'cos_theta' => round($this->cosTheta, 3),
            'score' => $this->score,
            'reasons' => $this->reasons(),
        ];
    }

    /**
     * Human-readable explanations shown in the apps.
     *
     * @return array<int, string>
     */
    private function reasons(): array
    {
        $reasons = [];

        if ($this->pickupDeviationM <= 400) {
            $reasons[] = 'Pickup is on the route';
        } elseif ($this->pickupDeviationM <= 1000) {
            $reasons[] = 'Short pickup detour';
        }

        if ($this->dropoffDeviationM <= 400) {
            $reasons[] = 'Drop-off is on the route';
        } elseif ($this->dropoffDeviationM <= 1000) {
            $reasons[] = 'Short drop-off detour';
        }

        if ($this->cosTheta >= 0.85) {
            $reasons[] = 'Same direction';
        }

        if ($this->overlapM >= 3000) {
            $reasons[] = 'Long shared route';
        }

        return $reasons;
    }
}
