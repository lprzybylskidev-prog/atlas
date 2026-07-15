<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Application\LoginProtection\LoginAttemptProtection;
use App\Modules\Core\Identity\Infrastructure\Notifications\AccountLockedNotification;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class LoginProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_attempts_lock_account_after_configured_threshold_with_escalating_durations(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00'));

        $user = User::factory()->create();
        $protection = $this->app->make(LoginAttemptProtection::class);

        for ($attempt = 1; $attempt <= 9; $attempt++) {
            $result = $protection->recordFailedAttempt($user->refresh());
            self::assertFalse($result->locked);
            self::assertSame($attempt, $result->failedAttempts);
        }

        $firstLock = $protection->recordFailedAttempt($user->refresh());

        self::assertTrue($firstLock->locked);
        self::assertTrue($user->refresh()->isLoginLocked());
        self::assertSame(1, $user->login_lock_count);
        self::assertSame(0, $user->failed_login_attempts);
        self::assertSame('2026-07-15 12:15:00', $user->loginLockedUntil()?->toDateTimeString());
        Notification::assertSentTo($user, AccountLockedNotification::class);

        Carbon::setTestNow(Carbon::parse('2026-07-15 12:16:00'));

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $secondLock = $protection->recordFailedAttempt($user->refresh());
        }

        self::assertTrue($secondLock->locked);
        self::assertSame(2, $user->refresh()->login_lock_count);
        self::assertSame('2026-07-15 12:46:00', $user->loginLockedUntil()?->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_successful_login_resets_failed_attempt_count(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        $user = User::factory()->create([
            'email' => 'reset-attempts@example.test',
            'password' => Hash::make('CorrectPass12!'),
            'failed_login_attempts' => 4,
        ]);

        $this->post('/login', [
            'email' => 'reset-attempts@example.test',
            'password' => 'CorrectPass12!',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        self::assertSame(0, $user->refresh()->failed_login_attempts);
        self::assertNull($user->login_locked_until);
    }
}
