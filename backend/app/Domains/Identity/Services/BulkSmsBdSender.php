<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\SmsSender;
use App\Domains\Identity\Exceptions\SmsDeliveryException;
use Illuminate\Support\Facades\Http;

class BulkSmsBdSender implements SmsSender
{
    /**
     * @param  array{url?: string, api_key?: string|null, sender_id?: string|null, timeout_seconds?: int}  $config
     */
    public function __construct(private readonly array $config) {}

    public function send(string $phone, string $message): void
    {
        $apiKey = trim((string) ($this->config['api_key'] ?? ''));
        $senderId = trim((string) ($this->config['sender_id'] ?? ''));

        if ($apiKey === '' || $senderId === '') {
            throw new SmsDeliveryException(
                'BulkSMSBD is not configured. Set BULKSMSBD_API_KEY and BULKSMSBD_SENDER_ID.',
            );
        }

        $response = Http::asForm()
            ->timeout((int) ($this->config['timeout_seconds'] ?? 15))
            ->post((string) ($this->config['url'] ?? 'https://bulksmsbd.net/api/smsapi'), [
                'api_key' => $apiKey,
                'type' => 'text',
                'number' => ltrim($phone, '+'),
                'senderid' => $senderId,
                'message' => $message,
            ]);

        if ($response->failed()) {
            throw new SmsDeliveryException("BulkSMSBD returned HTTP {$response->status()}.");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new SmsDeliveryException('BulkSMSBD returned an invalid response.');
        }

        $code = (string) ($payload['response_code'] ?? '');

        if ($code !== '202') {
            $detail = trim((string) ($payload['message'] ?? $payload['error'] ?? 'unknown error'));

            throw new SmsDeliveryException("BulkSMSBD rejected the message: {$detail}.");
        }
    }
}
