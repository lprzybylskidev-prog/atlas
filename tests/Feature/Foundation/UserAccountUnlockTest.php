<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Users\Application\Commands\UnlockUserAccountCommand;
use App\Modules\Core\Users\Application\Exceptions\InvalidUserAccountUnlock;
use App\Modules\Core\Users\Application\Exceptions\UserAccountNotFound;
use App\Modules\Core\Users\Application\UnlockUserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserAccountUnlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_unlock_clears_login_lock_and_records_security_audit(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create([
            'failed_login_attempts' => 0,
            'login_lock_count' => 2,
            'login_locked_until' => now()->addHour(),
        ]);

        $status = $this->app->make(UnlockUserAccount::class)->handle(new UnlockUserAccountCommand(
            targetPublicId: $target->public_id,
            actorPublicId: $actor->public_id,
            reason: 'Verified user identity through support process.',
        ));

        self::assertSame($target->public_id, $status->publicId);
        self::assertTrue($status->isActive);

        $target->refresh();
        self::assertSame(0, $target->failed_login_attempts);
        self::assertNull($target->login_locked_until);
        self::assertSame(2, $target->login_lock_count);

        $this->assertDatabaseHas('security_audit_events', [
            'module' => 'identity',
            'action' => 'user.login_unlock',
            'result' => 'succeeded',
            'source' => 'admin',
            'actor_public_id' => $actor->public_id,
            'target_public_id' => $target->public_id,
            'reason' => 'Verified user identity through support process.',
        ]);
    }

    public function test_admin_unlock_requires_reason(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->expectException(InvalidUserAccountUnlock::class);

        $this->app->make(UnlockUserAccount::class)->handle(new UnlockUserAccountCommand(
            targetPublicId: $target->public_id,
            actorPublicId: $actor->public_id,
            reason: '   ',
        ));
    }

    public function test_failed_admin_unlock_records_rejected_security_audit(): void
    {
        $actor = User::factory()->create();

        try {
            $this->app->make(UnlockUserAccount::class)->handle(new UnlockUserAccountCommand(
                targetPublicId: '01HZY000000000000000000000',
                actorPublicId: $actor->public_id,
                reason: 'Support requested unlock.',
            ));
        } catch (UserAccountNotFound) {
            // Expected: the failed administrative operation is still audited.
        }

        $this->assertDatabaseHas('security_audit_events', [
            'action' => 'user.login_unlock',
            'result' => 'rejected',
            'actor_public_id' => $actor->public_id,
            'target_public_id' => '01HZY000000000000000000000',
        ]);
    }
}
