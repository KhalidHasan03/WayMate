<?php

namespace App\Providers;

use App\Domains\Identity\Contracts\SmsSender;
use App\Domains\Identity\Services\BulkSmsBdSender;
use App\Domains\Identity\Services\LogSmsSender;
use App\Domains\Identity\Services\SslWirelessSmsSender;
use App\Domains\Identity\Services\TwilioSmsSender;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsSender::class, function () {
            $timeout = (int) config('waymate.sms.timeout_seconds', 15);

            return match (config('waymate.sms.driver')) {
                'log' => new LogSmsSender,
                'bulksmsbd' => new BulkSmsBdSender([
                    ...(array) config('waymate.sms.bulksmsbd'),
                    'timeout_seconds' => $timeout,
                ]),
                'sslwireless' => new SslWirelessSmsSender([
                    ...(array) config('waymate.sms.sslwireless'),
                    'timeout_seconds' => $timeout,
                ]),
                'twilio' => new TwilioSmsSender([
                    ...(array) config('waymate.sms.twilio'),
                    'timeout_seconds' => $timeout,
                ]),
                default => throw new InvalidArgumentException(
                    'Unsupported SMS driver ['.config('waymate.sms.driver').'].',
                ),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
