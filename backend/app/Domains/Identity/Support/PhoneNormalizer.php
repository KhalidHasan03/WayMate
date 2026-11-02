<?php

namespace App\Domains\Identity\Support;

use InvalidArgumentException;

class PhoneNormalizer
{
    public function normalize(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if (str_starts_with($digits, '880')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            $digits = '1'.$digits;
        }

        if (! preg_match('/^1[3-9]\d{8}$/', $digits)) {
            throw new InvalidArgumentException('Invalid Bangladeshi mobile number.');
        }

        return '+880'.$digits;
    }

    public function isValid(string $input): bool
    {
        try {
            $this->normalize($input);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function toLocal(string $e164): string
    {
        if (! str_starts_with($e164, '+880')) {
            throw new InvalidArgumentException('Not a Bangladeshi E.164 number.');
        }

        return '0'.substr($e164, 4);
    }
}
