<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Database\Factories;

use App\Modules\Core\Identity\Domain\ValueObjects\UserPublicId;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'public_id' => UserPublicId::new()->toString(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'first_password_set_at' => now(),
            'is_active' => true,
            'deactivated_at' => null,
            'failed_login_attempts' => 0,
            'login_lock_count' => 0,
            'login_locked_until' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }

    public function awaitingFirstPassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'first_password_set_at' => null,
        ]);
    }
}
