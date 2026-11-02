<?php

namespace Tests\Unit;

use App\Domains\Identity\Exceptions\SmsDeliveryException;
use App\Domains\Identity\Services\BulkSmsBdSender;
use App\Domains\Identity\Services\SslWirelessSmsSender;
use App\Domains\Identity\Services\TwilioSmsSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsSenderTest extends TestCase
{
    public function test_bulk_sms_bd_sends_expected_payload(): void
    {
        Http::fake([
            'bulksmsbd.net/*' => Http::response(['response_code' => 202, 'message' => 'Success']),
        ]);

        (new BulkSmsBdSender([
            'api_key' => 'key',
            'sender_id' => 'WayMate',
            'timeout_seconds' => 5,
        ]))->send('+8801712345678', 'Your code is 123456');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://bulksmsbd.net/api/smsapi'
                && $request['api_key'] === 'key'
                && $request['number'] === '8801712345678'
                && $request['senderid'] === 'WayMate'
                && $request['message'] === 'Your code is 123456';
        });
    }

    public function test_bulk_sms_bd_rejects_failed_response(): void
    {
        Http::fake([
            'bulksmsbd.net/*' => Http::response(['response_code' => 100, 'message' => 'Invalid API key']),
        ]);

        $this->expectException(SmsDeliveryException::class);

        (new BulkSmsBdSender([
            'api_key' => 'bad',
            'sender_id' => 'WayMate',
        ]))->send('+8801712345678', 'Your code is 123456');
    }

    public function test_bulk_sms_bd_requires_configuration(): void
    {
        $this->expectException(SmsDeliveryException::class);

        (new BulkSmsBdSender(['api_key' => '', 'sender_id' => '']))->send('+8801712345678', 'test');
    }

    public function test_ssl_wireless_sends_expected_payload(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response([
                'status' => 'SUCCESS',
                'status_code' => 200,
            ]),
        ]);

        (new SslWirelessSmsSender([
            'api_token' => 'token',
            'sid' => 'WayMate',
            'timeout_seconds' => 5,
        ]))->send('+8801712345678', 'Your code is 123456');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://smsplus.sslwireless.com/api/v3/send-sms'
                && $request['api_token'] === 'token'
                && $request['sid'] === 'WayMate'
                && $request['msisdn'] === '8801712345678'
                && $request['sms'] === 'Your code is 123456'
                && is_string($request['csms_id'])
                && strlen($request['csms_id']) === 20;
        });
    }

    public function test_ssl_wireless_rejects_failed_response(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response([
                'status' => 'FAILED',
                'status_code' => 401,
                'error_message' => 'Invalid token',
            ]),
        ]);

        $this->expectException(SmsDeliveryException::class);

        (new SslWirelessSmsSender([
            'api_token' => 'bad',
            'sid' => 'WayMate',
        ]))->send('+8801712345678', 'Your code is 123456');
    }

    public function test_twilio_sends_expected_payload(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
        ]);

        (new TwilioSmsSender([
            'account_sid' => 'AC123',
            'auth_token' => 'token',
            'from' => '+15551234567',
            'timeout_seconds' => 5,
        ]))->send('+8801712345678', 'Your code is 123456');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
                && $request['To'] === '+8801712345678'
                && $request['From'] === '+15551234567'
                && $request['Body'] === 'Your code is 123456';
        });
    }

    public function test_twilio_rejects_failed_response(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['message' => 'Invalid To number'], 400),
        ]);

        $this->expectException(SmsDeliveryException::class);

        (new TwilioSmsSender([
            'account_sid' => 'AC123',
            'auth_token' => 'token',
            'from' => '+15551234567',
        ]))->send('not-a-number', 'Your code is 123456');
    }
}
