<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Users\Application\ActivateUserAccount;
use App\Modules\Core\Users\Application\Commands\ActivateUserAccountCommand;
use App\Modules\Core\Users\Application\Commands\DeactivateUserAccountCommand;
use App\Modules\Core\Users\Application\DeactivateUserAccount;
use App\Modules\Core\Users\Application\Exceptions\UserAccountNotFound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class UserAccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_account_can_be_deactivated_and_reactivated(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        $user = User::factory()->create([
            'email' => 'status@example.test',
            'password' => Hash::make('CorrectPass12!'),
        ]);

        $deactivated = $this->app->make(DeactivateUserAccount::class)->handle(new DeactivateUserAccountCommand(
            publicId: $user->public_id,
        ));

        self::assertFalse($deactivated->isActive);

        $user->refresh();
        self::assertFalse($user->isActive());
        self::assertNotNull($user->deactivated_at);

        $this->post('/login', [
            'email' => 'status@example.test',
            'password' => 'CorrectPass12!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $activated = $this->app->make(ActivateUserAccount::class)->handle(new ActivateUserAccountCommand(
            publicId: $user->public_id,
        ));

        self::assertTrue($activated->isActive);

        $user->refresh();
        self::assertTrue($user->isActive());
        self::assertNull($user->deactivated_at);

        $this->post('/login', [
            'email' => 'status@example.test',
            'password' => 'CorrectPass12!',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_deactivating_missing_user_account_fails_explicitly(): void
    {
        $this->expectException(UserAccountNotFound::class);

        $this->app->make(DeactivateUserAccount::class)->handle(new DeactivateUserAccountCommand(
            publicId: '01HZY000000000000000000000',
        ));
    }
}
