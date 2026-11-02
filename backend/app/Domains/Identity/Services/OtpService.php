<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\SmsSender;
use App\Domains\Identity\Exceptions\SmsDeliveryException;
use Illuminate\Support\Facades\Cache;
use Throwable;

class OtpService
{
    public const RESULT_OK = 'ok';

    public const RESULT_INVALID = 'invalid';

    public const RESULT_EXPIRED = 'expired';

    public const RESULT_LOCKED = 'locked';

    public function __construct(
        private readonly SmsSender $sms,
    ) {}

    public function sendCode(string $phone): ?string
    {
        $cooldownKey = $this->cooldownKey($phone);

        if (Cache::has($cooldownKey)) {
            return null;
        }

        $code = (string) random_int(100000, 999999);
        $ttl = (int) config('waymate.otp.ttl_seconds', 300);

        Cache::put($this->codeKey($phone), [
            'hash' => $this->hash($code),
            'attempts' => 0,
        ], $ttl);

        Cache::put($cooldownKey, true, (int) config('waymate.otp.resend_cooldown_seconds', 60));

        try {
            $this->sms->send(
                $phone,
                "Your WayMate verification code is {$code}. It expires in 5 minutes. Never share this code.",
            );
        } catch (Throwable $e) {
            Cache::forget($this->codeKey($phone));
            Cache::forget($cooldownKey);

            if ($e instanceof SmsDeliveryException) {
                throw $e;
            }

            throw new SmsDeliveryException('The SMS provider could not deliver the code.', previous: $e);
        }

        return $code;
    }

    public function verify(string $phone, string $code): string
    {
        $key = $this->codeKey($phone);
        $payload = Cache::get($key);

        if (! is_array($payload) || ! isset($payload['hash'], $payload['attempts'])) {
            return self::RESULT_EXPIRED;
        }

        $maxAttempts = (int) config('waymate.otp.max_attempts', 5);

        if ((int) $payload['attempts'] >= $maxAttempts) {
            Cache::forget($key);

            return self::RESULT_LOCKED;
        }

        if (! hash_equals($payload['hash'], $this->hash($code))) {
            $payload['attempts'] = (int) $payload['attempts'] + 1;
            $ttl = max(1, (int) config('waymate.otp.ttl_seconds', 300));
            Cache::put($key, $payload, $ttl);

            return self::RESULT_INVALID;
        }

        Cache::forget($key);

        return self::RESULT_OK;
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function codeKey(string $phone): string
    {
        return 'otp:code:'.$phone;
    }

    private function cooldownKey(string $phone): string
    {
        return 'otp:cooldown:'.$phone;
    }
}
