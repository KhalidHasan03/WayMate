<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Carbon;
use Throwable;

trait NormalizesDateTimeInput
{
    protected function normalizeDateTime(string $field): void
    {
        $value = $this->input($field);

        if ($value === null || $value === '') {
            return;
        }

        try {
            $this->merge([
                $field => Carbon::parse($value)
                    ->setTimezone(config('app.timezone'))
                    ->toIso8601String(),
            ]);
        } catch (Throwable) {
            // Let the form request validation report the invalid date.
        }
    }
}
