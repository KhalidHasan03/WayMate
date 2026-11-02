<?php

namespace Tests\Support;

use App\Domains\Identity\Contracts\SmsSender;
use App\Domains\Identity\Exceptions\SmsDeliveryException;

class FakeSmsSender implements SmsSender
{
    /** @var array<int, array{phone: string, message: string}> */
    public array $sent = [];

    public bool $fail = false;

    public function send(string $phone, string $message): void
    {
        if ($this->fail) {
            throw new SmsDeliveryException('Fake SMS provider failed.');
        }

        $this->sent[] = ['phone' => $phone, 'message' => $message];
    }

    public function lastCode(): ?string
    {
        $last = end($this->sent);

        if ($last === false) {
            return null;
        }

        return preg_match('/\b(\d{6})\b/', $last['message'], $matches) === 1
            ? $matches[1]
            : null;
    }
}
