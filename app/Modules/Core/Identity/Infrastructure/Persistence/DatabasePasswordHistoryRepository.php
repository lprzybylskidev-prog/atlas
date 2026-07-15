<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Contracts\PasswordHistoryRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class DatabasePasswordHistoryRepository implements PasswordHistoryRepository
{
    public function containsRecentPassword(int $userId, string $plainPassword, int $limit): bool
    {
        $hashes = DB::table('user_password_histories')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('password_hash');

        foreach ($hashes as $hash) {
            if (is_string($hash) && Hash::check($plainPassword, $hash)) {
                return true;
            }
        }

        return false;
    }

    public function record(int $userId, string $passwordHash, int $limit): void
    {
        DB::table('user_password_histories')->insert([
            'user_id' => $userId,
            'password_hash' => $passwordHash,
            'created_at' => now(),
        ]);

        $retainedIds = DB::table('user_password_histories')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id');

        DB::table('user_password_histories')
            ->where('user_id', $userId)
            ->whereNotIn('id', $retainedIds)
            ->delete();
    }
}
