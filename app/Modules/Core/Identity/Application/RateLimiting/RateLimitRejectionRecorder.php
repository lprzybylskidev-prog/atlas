<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\RateLimiting;

use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RateLimitRejectionRecorder
{
    public function record(string $policy, string $limiterKey, ?string $requestId = null): void
    {
        $now = Carbon::now();
        $hash = $this->hash($limiterKey);
        $preview = $this->preview($limiterKey);

        $updated = DB::table(IdentityDatabaseTable::RATE_LIMIT_REJECTIONS)
            ->where('policy', $policy)
            ->where('limiter_key_hash', $hash)
            ->update([
                'limiter_key_preview' => $preview,
                'rejections_count' => DB::raw('rejections_count + 1'),
                'last_rejected_at' => $now,
                'last_request_id' => $requestId,
                'updated_at' => $now,
            ]);

        if ($updated > 0) {
            return;
        }

        DB::table(IdentityDatabaseTable::RATE_LIMIT_REJECTIONS)->insert([
            'policy' => $policy,
            'limiter_key_hash' => $hash,
            'limiter_key_preview' => $preview,
            'rejections_count' => 1,
            'first_rejected_at' => $now,
            'last_rejected_at' => $now,
            'last_request_id' => $requestId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function hash(string $limiterKey): string
    {
        return hash('sha256', $limiterKey);
    }

    public function preview(string $limiterKey): string
    {
        $safe = preg_replace('/[[:cntrl:]]/', '', $limiterKey) ?? '';

        return Str::limit($safe, 180, '...');
    }
}
