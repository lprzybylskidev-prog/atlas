<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitPolicyCatalog;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitRejectionRecorder;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

final readonly class ResetRateLimitCounterController
{
    public function __construct(
        private AuditRecorder $audit,
        private RateLimitRejectionRecorder $rejections,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $policy = $validated['policy'];
        $limiterKey = $validated['limiter_key'];
        $reason = trim($validated['reason']);
        $actorPublicId = data_get($request->user(), 'public_id');
        $correlationId = $request->attributes->get('correlation_id');

        RateLimitPolicyCatalog::fromConfiguredValue(config('atlas.security.rate_limits.policies'))->get($policy);

        RateLimiter::clear($limiterKey);

        DB::table(DatabaseTable::RATE_LIMIT_REJECTIONS)
            ->where('policy', $policy)
            ->where('limiter_key_hash', $this->rejections->hash($limiterKey))
            ->delete();

        $this->audit->record(new AuditEvent(
            module: 'identity',
            action: 'rate_limit.counter_reset',
            result: 'succeeded',
            source: 'admin',
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            targetType: 'rate_limit_counter',
            correlationId: is_string($correlationId) ? $correlationId : null,
            reason: $reason,
            metadata: [
                'policy' => $policy,
                'limiter_key' => $limiterKey,
                'limiter_key_hash' => $this->rejections->hash($limiterKey),
            ],
            security: true,
            securityCategory: 'rate_limit',
        ));

        return redirect()->route('admin.rate-limits.index')->with('success', 'Rate-limit counter was reset.');
    }

    /**
     * @return array{policy: string, limiter_key: string, reason: string}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'policy' => ['required', 'string', 'max:120'],
            'limiter_key' => ['required', 'string', 'max:500'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $values = $this->stringKeyedArray($validated);

        return [
            'policy' => $this->stringValue($values, 'policy'),
            'limiter_key' => $this->stringValue($values, 'limiter_key'),
            'reason' => $this->stringValue($values, 'reason'),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function stringValue(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function stringKeyedArray(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
