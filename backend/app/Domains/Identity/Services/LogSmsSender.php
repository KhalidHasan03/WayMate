<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\SmsSender;
use App\Domains\Identity\Exceptions\SmsDeliveryException;
use Illuminate\Support\Facades\Log;

class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        if (app()->environment('production') && ! config('waymate.sms.allow_log_in_production', false)) {
            throw new SmsDeliveryException(
                'The log SMS driver is disabled in production. Configure a real SMS provider before going live.',
            );
        }

        Log::info('SMS dispatched', [
            'to' => $phone,
            'message' => $message,
        ]);
    }
}
