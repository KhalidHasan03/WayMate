<?php

namespace Tests\Feature;

use App\Domains\Identity\Contracts\SmsSender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeSmsSender;
use Tests\TestCase;

class AuthOtpTest extends TestCase
{
    use RefreshDatabase;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);
    }

    public function test_otp_request_sends_hashed_code(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/request', [
            'phone' => '01712345678',
        ]);

        $response->assertOk()
            ->assertJsonPath('expires_in', 300);

        $this->assertCount(1, $this->sms->sent);
        $this->assertSame('+8801712345678', $this->sms->sent[0]['phone']);
        $this->assertNotNull($this->sms->lastCode());

        $payload = Cache::get('otp:code:+8801712345678');
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('hash', $payload);
        $this->assertSame(0, $payload['attempts']);
        $this->assertStringNotContainsString($this->sms->lastCode(), json_encode($payload));
    }

    public function test_valid_otp_creates_user_and_issues_token(): void
    {
        $this->requestCode('+8801712345678');

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+8801712345678',
            'code' => $this->sms->lastCode(),
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'phone', 'roles']]);

        $user = User::query()->where('phone', '+8801712345678')->firstOrFail();

        $this->assertNotNull($user->phone_verified_at);
        $this->assertNotNull($user->profile);
        $this->assertTrue($user->hasRole('user'));
        $this->assertCount(1, $user->tokens);

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'phpunit']);
    }

    public function test_wrong_otp_is_rejected(): void
    {
        $this->requestCode('+8801712345678');
        $code = $this->sms->lastCode();
        $wrong = str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+8801712345678',
            'code' => $wrong,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertDatabaseMissing('users', ['phone' => '+8801712345678']);
    }

    public function test_otp_locks_after_max_attempts(): void
    {
        $this->requestCode('+8801712345678');
        $correct = $this->sms->lastCode();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', [
                'phone' => '+8801712345678',
                'code' => '999999',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+8801712345678',
            'code' => $correct,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_expired_or_missing_otp_is_rejected(): void
    {
        $this->requestCode('+8801712345678');
        $code = $this->sms->lastCode();

        Cache::flush();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+8801712345678',
            'code' => $code,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_resend_is_rate_limited_by_cooldown(): void
    {
        $this->requestCode('+8801712345678');

        $this->postJson('/api/v1/auth/otp/request', [
            'phone' => '+8801712345678',
        ])->assertStatus(429);
    }

    public function test_failed_sms_delivery_returns_502_and_allows_retry(): void
    {
        $this->sms->fail = true;

        $this->postJson('/api/v1/auth/otp/request', [
            'phone' => '01712345678',
        ])->assertStatus(502);

        $this->assertFalse(Cache::has('otp:code:+8801712345678'));
        $this->assertFalse(Cache::has('otp:cooldown:+8801712345678'));

        $this->sms->fail = false;

        $this->requestCode('+8801712345678');
        $this->assertCount(1, $this->sms->sent);
    }

    public function test_failed_delivery_does_not_consume_request_quota(): void
    {
        $this->sms->fail = true;

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/request', [
                'phone' => '01712345678',
            ])->assertStatus(502);
        }

        $this->sms->fail = false;

        $this->requestCode('+8801712345678');
        $this->assertCount(1, $this->sms->sent);
    }

    public function test_log_driver_returns_debug_code_to_local_client(): void
    {
        config(['waymate.sms.driver' => 'log']);

        $response = $this->postJson('/api/v1/auth/otp/request', [
            'phone' => '01712345678',
        ]);

        $response->assertOk()->assertJsonStructure(['message', 'expires_in', 'code']);
        $this->assertSame($this->sms->lastCode(), $response->json('code'));
    }

    public function test_real_sms_driver_does_not_return_debug_code(): void
    {
        config(['waymate.sms.driver' => 'bulksmsbd']);

        $response = $this->postJson('/api/v1/auth/otp/request', [
            'phone' => '01712345678',
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('code', $response->json());
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/otp/request', [
            'phone' => '12345',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_suspended_user_cannot_login(): void
    {
        User::factory()->suspended()->create(['phone' => '+8801712345678']);

        $this->requestCode('+8801712345678');

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+8801712345678',
            'code' => $this->sms->lastCode(),
        ])->assertForbidden();
    }

    public function test_me_and_profile_update_require_token(): void
    {
        $user = User::factory()->withProfile()->create();

        $this->getJson('/api/v1/me')->assertUnauthorized();

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->withToken($token)
            ->patchJson('/api/v1/me/profile', [
                'name' => 'New Name',
                'bio' => 'Hello',
                'notify_prefs' => ['push' => true, 'sms' => false],
                'accept_terms' => true,
                'accept_cost_share_disclaimer' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.profile.bio', 'Hello');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('Hello', $user->profile->bio);
        $this->assertTrue((bool) $user->profile->notify_prefs['push']);
        $this->assertNotNull($user->profile->terms_accepted_at);
        $this->assertNotNull($user->profile->cost_share_disclaimer_at);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertCount(0, $user->tokens);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    private function requestCode(string $phone): void
    {
        $this->postJson('/api/v1/auth/otp/request', [
            'phone' => $phone,
        ])->assertOk();
    }
}
