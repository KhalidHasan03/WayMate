<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Exceptions\SmsDeliveryException;
use App\Domains\Identity\Services\OtpService;
use App\Domains\Identity\Support\PhoneNormalizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function requestOtp(
        RequestOtpRequest $request,
        OtpService $otp,
        PhoneNormalizer $phones,
    ): JsonResponse {
        $phone = $this->normalizedPhone($request->string('phone')->toString(), $phones);

        $ipKey = 'otp-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            return $this->tooManyRequests($ipKey);
        }
        RateLimiter::hit($ipKey, 60);

        $requestKey = 'otp-request:'.sha1($phone.'|'.$request->ip());
        $maxRequests = (int) config('waymate.otp.max_requests_per_window', 5);
        if (RateLimiter::tooManyAttempts($requestKey, $maxRequests)) {
            return $this->tooManyRequests($requestKey);
        }

        try {
            $code = $otp->sendCode($phone);
        } catch (SmsDeliveryException $e) {
            Log::error('OTP SMS delivery failed.', [
                'phone' => $phone,
                'driver' => config('waymate.sms.driver'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not send the verification code. Please try again later.',
            ], 502);
        }

        if ($code === null) {
            return response()->json([
                'message' => 'Please wait before requesting another code.',
            ], 429);
        }

        RateLimiter::hit($requestKey, (int) config('waymate.otp.request_window_seconds', 600));

        $response = [
            'message' => 'A verification code has been sent.',
            'expires_in' => (int) config('waymate.otp.ttl_seconds', 300),
        ];

        if (app()->environment(['local', 'testing']) && config('waymate.sms.driver') === 'log') {
            $response['code'] = $code;
        }

        return response()->json($response);
    }

    public function verifyOtp(
        VerifyOtpRequest $request,
        OtpService $otp,
        PhoneNormalizer $phones,
    ): JsonResponse {
        $phone = $this->normalizedPhone($request->string('phone')->toString(), $phones);

        $ipKey = 'otp-verify-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 30)) {
            return $this->tooManyRequests($ipKey);
        }
        RateLimiter::hit($ipKey, 600);

        $result = $otp->verify($phone, $request->string('code')->toString());

        if ($result !== OtpService::RESULT_OK) {
            $message = match ($result) {
                OtpService::RESULT_LOCKED => 'Too many incorrect attempts. Request a new code.',
                OtpService::RESULT_EXPIRED => 'The verification code has expired. Request a new code.',
                default => 'The verification code is incorrect.',
            };

            throw ValidationException::withMessages(['code' => [$message]]);
        }

        $user = DB::transaction(function () use ($phone) {
            $user = User::query()->createOrFirst(
                ['phone' => $phone],
                [
                    'name' => 'WayMate User',
                    'status' => User::STATUS_ACTIVE,
                    'locale' => 'en',
                    'phone_verified_at' => now(),
                ],
            );

            if ($user->phone_verified_at === null) {
                $user->forceFill(['phone_verified_at' => now()])->save();
            }

            $user->profile()->firstOrCreate([]);
            Role::findOrCreate('user', 'web');

            if (! $user->hasRole('user')) {
                $user->assignRole('user');
            }

            return $user;
        });

        if (! $user->isActive()) {
            return response()->json([
                'message' => 'This account is not active. Contact support.',
            ], 403);
        }

        $device = $request->string('device_name')->toString() ?: 'web';
        $token = $user->createToken($device)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('profile', 'roles')),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('profile', 'roles'));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    private function normalizedPhone(string $input, PhoneNormalizer $phones): string
    {
        try {
            return $phones->normalize($input);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'phone' => ['Enter a valid Bangladeshi mobile number.'],
            ]);
        }
    }

    private function tooManyRequests(string $key): JsonResponse
    {
        $retryAfter = RateLimiter::availableIn($key);

        return response()->json([
            'message' => 'Too many requests. Please try again later.',
        ], 429)->header('Retry-After', (string) $retryAfter);
    }
}
