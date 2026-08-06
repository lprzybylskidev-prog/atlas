<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AuthenticationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_routes_are_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Public User',
            'email' => 'public@example.test',
            'password' => 'CorrectPass12!',
            'password_confirmation' => 'CorrectPass12!',
        ])->assertNotFound();
    }

    public function test_active_user_can_authenticate_with_valid_credentials(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        $user = User::factory()->create([
            'email' => 'active@example.test',
            'password' => Hash::make('CorrectPass12!'),
        ]);

        $this->post('/login', [
            'email' => 'active@example.test',
            'password' => 'CorrectPass12!',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_with_expired_password_cannot_authenticate(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Carbon::setTestNow(Carbon::parse('2026-08-01 12:00:00'));

        User::factory()->create([
            'email' => 'expired-password@example.test',
            'password' => Hash::make('CorrectPass12!'),
            'password_changed_at' => now()->subDays(91),
        ]);

        $this->post('/login', [
            'email' => 'expired-password@example.test',
            'password' => 'CorrectPass12!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        Carbon::setTestNow();
    }

    public function test_inactive_user_cannot_authenticate_with_valid_credentials(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        User::factory()->inactive()->create([
            'email' => 'inactive@example.test',
            'password' => Hash::make('CorrectPass12!'),
        ]);

        $this->post('/login', [
            'email' => 'inactive@example.test',
            'password' => 'CorrectPass12!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_waiting_for_first_password_cannot_authenticate(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        User::factory()->awaitingFirstPassword()->create([
            'email' => 'awaiting-first-password@example.test',
            'password' => Hash::make('CorrectPass12!'),
        ]);

        $this->post('/login', [
            'email' => 'awaiting-first-password@example.test',
            'password' => 'CorrectPass12!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_reset_password_page_is_available_for_first_password_links(): void
    {
        $this->get('/reset-password/example-token?email=user@example.test')->assertOk();
    }

    public function test_password_reset_link_request_does_not_disclose_account_existence(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        User::factory()->create([
            'email' => 'known-reset@example.test',
        ]);

        $known = $this->withHeader('Accept', 'application/json')->post('/forgot-password', [
            'email' => 'known-reset@example.test',
        ]);

        $missing = $this->withHeader('Accept', 'application/json')->post('/forgot-password', [
            'email' => 'missing-reset@example.test',
        ]);

        $known->assertOk();
        $missing->assertOk();
        self::assertSame($known->json('message'), $missing->json('message'));
        self::assertStringNotContainsString('known-reset', (string) $known->getContent());
        self::assertStringNotContainsString('missing-reset', (string) $missing->getContent());
    }

    public function test_first_password_setup_marks_email_as_verified(): void
    {
        $user = User::factory()->awaitingFirstPassword()->make();

        self::assertFalse($user->hasSetFirstPassword());
        self::assertNull($user->email_verified_at);

        $user->markFirstPasswordAsSet();

        self::assertTrue($user->hasSetFirstPassword());
        self::assertNotNull($user->email_verified_at);
    }

    public function test_password_reset_tokens_are_one_time_and_new_tokens_invalidate_previous_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-token@example.test',
        ]);

        /** @var PasswordBroker $broker */
        $broker = Password::broker();

        $firstToken = $broker->createToken($user);
        $secondToken = $broker->createToken($user);

        self::assertFalse($broker->tokenExists($user, $firstToken));
        self::assertTrue($broker->tokenExists($user, $secondToken));

        $status = $broker->reset([
            'email' => 'reset-token@example.test',
            'token' => $secondToken,
            'password' => 'CorrectPass12!',
        ], static function (CanResetPassword $resetUser): void {
            self::assertSame('reset-token@example.test', $resetUser->getEmailForPasswordReset());
        });

        self::assertSame(PasswordBroker::PASSWORD_RESET, $status);
        self::assertFalse($broker->tokenExists($user, $secondToken));
    }

    public function test_login_rate_limit_response_is_generic_and_does_not_disclose_thresholds(): void
    {
        $email = sprintf('missing-%s@example.test', Str::lower((string) Str::ulid()));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withHeader('Accept', 'application/json')->post('/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->withHeader('Accept', 'application/json')->post('/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
        self::assertStringNotContainsString('5 attempts', (string) $response->getContent());
        self::assertStringNotContainsString('60 seconds', (string) $response->getContent());
        self::assertStringNotContainsString('missing-', (string) $response->getContent());
    }
}
