<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\SmsSender;
use App\Domains\Identity\Exceptions\SmsDeliveryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SslWirelessSmsSender implements SmsSender
{
    /**
     * @param  array{url?: string, api_token?: string|null, sid?: string|null, timeout_seconds?: int}  $config
     */
    public function __construct(private readonly array $config) {}

    public function send(string $phone, string $message): void
    {
        $apiToken = trim((string) ($this->config['api_token'] ?? ''));
        $sid = trim((string) ($this->config['sid'] ?? ''));

        if ($apiToken === '' || $sid === '') {
            throw new SmsDeliveryException(
                'SSL Wireless is not configured. Set SSLWIRELESS_API_TOKEN and SSLWIRELESS_SID.',
            );
        }

        $response = Http::asJson()
            ->acceptJson()
            ->timeout((int) ($this->config['timeout_seconds'] ?? 15))
            ->post((string) ($this->config['url'] ?? 'https://smsplus.sslwireless.com/api/v3/send-sms'), [
                'api_token' => $apiToken,
                'sid' => $sid,
                'msisdn' => ltrim($phone, '+'),
                'sms' => $message,
                'csms_id' => Str::random(20),
            ]);

        if ($response->failed()) {
            throw new SmsDeliveryException("SSL Wireless returned HTTP {$response->status()}.");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new SmsDeliveryException('SSL Wireless returned an invalid response.');
        }

        $status = (string) ($payload['status'] ?? '');
        $statusCode = (int) ($payload['status_code'] ?? 0);

        if ($status !== 'SUCCESS' || $statusCode !== 200) {
            $detail = trim((string) ($payload['error_message'] ?? 'unknown error'));

            throw new SmsDeliveryException("SSL Wireless rejected the message: {$detail}.");
        }
    }
}
