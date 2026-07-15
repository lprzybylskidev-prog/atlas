<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Tests\TestCase;

final class MultiFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_totp_mfa_can_be_enabled_confirmed_and_used_as_login_challenge(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->bindFakeTwoFactorProvider();

        $user = User::factory()->create([
            'email' => 'mfa@example.test',
            'password' => Hash::make('CorrectPass12!'),
        ]);

        $this->app->make(EnableTwoFactorAuthentication::class)($user);

        $user->refresh();
        self::assertFalse($user->hasEnabledTwoFactorAuthentication());
        self::assertCount(8, $user->recoveryCodes());

        $this->app->make(ConfirmTwoFactorAuthentication::class)($user, '123456');

        $user->refresh();
        self::assertTrue($user->hasEnabledTwoFactorAuthentication());
        self::assertNotNull($user->two_factor_confirmed_at);

        $response = $this->withHeader('Accept', 'application/json')->post('/login', [
            'email' => 'mfa@example.test',
            'password' => 'CorrectPass12!',
        ]);

        $response->assertOk()->assertJson([
            'two_factor' => true,
        ]);

        $this->assertGuest();
    }

    public function test_recovery_codes_are_available_for_confirmed_totp_mfa(): void
    {
        $this->bindFakeTwoFactorProvider();

        $user = User::factory()->create();

        $this->app->make(EnableTwoFactorAuthentication::class)($user);
        $this->app->make(ConfirmTwoFactorAuthentication::class)($user, '123456');

        $codes = $user->refresh()->recoveryCodes();

        self::assertCount(8, $codes);
        self::assertContainsOnly('string', $codes);
    }

    private function bindFakeTwoFactorProvider(): void
    {
        $this->app->bind(TwoFactorAuthenticationProvider::class, static fn (): TwoFactorAuthenticationProvider => new class implements TwoFactorAuthenticationProvider
        {
            public function generateSecretKey(): string
            {
                return 'TESTSECRET';
            }

            public function qrCodeUrl($companyName, $companyEmail, $secret): string
            {
                return sprintf('otpauth://totp/%s:%s?secret=%s', $companyName, $companyEmail, $secret);
            }

            public function verify($secret, $code): bool
            {
                return $secret === 'TESTSECRET' && $code === '123456';
            }
        });
    }
}
