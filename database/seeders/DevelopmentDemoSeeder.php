<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentDemoSeeder extends Seeder
{
    public const PREVIEW_EMAIL = 'atlas@example.test';

    public const PREVIEW_PASSWORD = 'password';

    public function run(): void
    {
        $user = User::query()->firstOrNew([
            'email' => self::PREVIEW_EMAIL,
        ]);

        $user->forceFill([
            'name' => 'Atlas Demo',
            'password' => Hash::make(self::PREVIEW_PASSWORD),
            'email_verified_at' => now(),
        ])->save();
    }
}
