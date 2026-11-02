<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'matching.corridor_m' => 1200,
            'matching.min_overlap_m' => 500,
            'matching.cos_threshold' => 0.5,
            'matching.score_threshold' => 55,
            'matching.lead_time_min' => 15,
            'matching.horizon_days' => 7,
            'matching.offer_window_min' => 60,
            'matching.request_window_min' => 90,
            'fare.max_fare_version' => 3,
            'fare.adjustment_vote_window_min' => 10,
            'join.request_ttl_hours' => 24,
            'join.offer_ttl_hours' => 12,
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }
}
