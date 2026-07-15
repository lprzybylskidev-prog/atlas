<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Application\PasswordHistory;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Identity\Presentation\Fortify\Actions\UpdateUserPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PasswordHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_reuse_recent_password(): void
    {
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response('', 200),
        ]);

        $user = User::factory()->create([
            'email' => 'history@example.test',
            'password' => Hash::make('CurrentPass12!'),
        ]);

        $this->actingAs($user);

        $this->app->make(UpdateUserPassword::class)->update($user, [
            'current_password' => 'CurrentPass12!',
            'password' => 'FreshPass12!',
            'password_confirmation' => 'FreshPass12!',
        ]);

        $user->refresh();

        $this->expectException(ValidationException::class);

        $this->app->make(UpdateUserPassword::class)->update($user, [
            'current_password' => 'FreshPass12!',
            'password' => 'CurrentPass12!',
            'password_confirmation' => 'CurrentPass12!',
        ]);
    }

    public function test_password_history_keeps_last_ten_entries(): void
    {
        $user = User::factory()->create();
        $history = $this->app->make(PasswordHistory::class);

        for ($passwordNumber = 1; $passwordNumber <= 11; $passwordNumber++) {
            $history->recordNewPassword($user->internalId(), Hash::make(sprintf('Password%d!', $passwordNumber)));
        }

        self::assertSame(10, DB::table('user_password_histories')->where('user_id', $user->getKey())->count());
    }
}
