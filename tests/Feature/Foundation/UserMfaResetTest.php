<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Users\Application\Commands\ResetUserMfaCommand;
use App\Modules\Core\Users\Application\Exceptions\InvalidUserMfaReset;
use App\Modules\Core\Users\Application\ResetUserMfa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

final class UserMfaResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_mfa_reset_clears_totp_state_and_records_security_audit(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('TESTSECRET'),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['code-one'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
        ]);

        $status = $this->app->make(ResetUserMfa::class)->handle(new ResetUserMfaCommand(
            targetPublicId: $target->public_id,
            actorPublicId: $actor->public_id,
            reason: 'User lost authenticator device.',
        ));

        self::assertSame($target->public_id, $status->publicId);

        $target->refresh();
        self::assertNull($target->two_factor_secret);
        self::assertNull($target->two_factor_recovery_codes);
        self::assertNull($target->two_factor_confirmed_at);

        $this->assertDatabaseHas('audit_events', [
            'module' => 'identity',
            'action' => 'user.mfa_reset',
            'result' => 'succeeded',
            'source' => 'admin',
            'actor_public_id' => $actor->public_id,
            'target_public_id' => $target->public_id,
            'reason' => 'User lost authenticator device.',
        ]);
    }

    public function test_admin_mfa_reset_requires_reason(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->expectException(InvalidUserMfaReset::class);

        $this->app->make(ResetUserMfa::class)->handle(new ResetUserMfaCommand(
            targetPublicId: $target->public_id,
            actorPublicId: $actor->public_id,
            reason: '',
        ));
    }
}
