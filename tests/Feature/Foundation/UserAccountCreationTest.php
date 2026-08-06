<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Users\Application\Commands\CreateUserAccountCommand;
use App\Modules\Core\Users\Application\CreateUserAccount;
use App\Modules\Core\Users\Application\Exceptions\InvalidUserAccountData;
use App\Modules\Core\Users\Infrastructure\Notifications\FirstPasswordSetupNotification;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class UserAccountCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_created_user_receives_first_password_link_and_cannot_authenticate_before_setup(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        Notification::fake();

        $created = $this->app->make(CreateUserAccount::class)->handle(new CreateUserAccountCommand(
            name: 'Operations User',
            email: 'Operations.User@Example.Test',
        ));

        self::assertSame('operations.user@example.test', $created->email);
        self::assertTrue($created->firstPasswordLinkIssued);

        $user = User::query()->where('email', 'operations.user@example.test')->firstOrFail();

        self::assertSame($created->publicId, $user->public_id);
        self::assertTrue($user->isActive());
        self::assertFalse($user->hasSetFirstPassword());
        self::assertNull($user->email_verified_at);
        self::assertSame(User::DEFAULT_AVATAR_COLOR, $user->avatar_color);
        self::assertFalse(Hash::check('', $user->password));

        $this->assertDatabaseHas(DatabaseTable::PASSWORD_RESET_TOKENS, [
            'email' => 'operations.user@example.test',
        ]);

        Notification::assertSentOnDemand(
            FirstPasswordSetupNotification::class,
            static fn (FirstPasswordSetupNotification $notification): bool => str_contains((string) $notification->toMail($user)->render(), '/reset-password/'),
        );

        $this->post('/login', [
            'email' => 'operations.user@example.test',
            'password' => 'WrongPass12!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_account_email_must_be_unique(): void
    {
        User::factory()->create([
            'email' => 'existing@example.test',
        ]);

        $this->expectException(InvalidUserAccountData::class);

        $this->app->make(CreateUserAccount::class)->handle(new CreateUserAccountCommand(
            name: 'Duplicate User',
            email: 'EXISTING@example.test',
        ));
    }

    public function test_user_model_assigns_default_avatar_color_when_missing(): void
    {
        $user = User::factory()->create([
            'avatar_color' => null,
        ]);

        self::assertSame(User::DEFAULT_AVATAR_COLOR, $user->avatar_color);
    }
}
