<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\SmsSender;
use App\Domains\Identity\Exceptions\SmsDeliveryException;
use Illuminate\Support\Facades\Http;

class TwilioSmsSender implements SmsSender
{
    /**
     * @param  array{account_sid?: string|null, auth_token?: string|null, from?: string|null, timeout_seconds?: int}  $config
     */
    public function __construct(private readonly array $config) {}

    public function send(string $phone, string $message): void
    {
        $accountSid = trim((string) ($this->config['account_sid'] ?? ''));
        $authToken = trim((string) ($this->config['auth_token'] ?? ''));
        $from = trim((string) ($this->config['from'] ?? ''));

        if ($accountSid === '' || $authToken === '' || $from === '') {
            throw new SmsDeliveryException(
                'Twilio is not configured. Set TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN and TWILIO_FROM.',
            );
        }

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->timeout((int) ($this->config['timeout_seconds'] ?? 15))
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'To' => $phone,
                'From' => $from,
                'Body' => $message,
            ]);

        if ($response->failed()) {
            $detail = trim((string) ($response->json('message') ?? 'unknown error'));

            throw new SmsDeliveryException("Twilio rejected the message: {$detail}.");
        }
    }
}
