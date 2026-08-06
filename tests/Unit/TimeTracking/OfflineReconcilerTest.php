<?php

declare(strict_types=1);

namespace Tests\Unit\TimeTracking;

use App\Modules\Optional\TimeTracking\Domain\Offline\OfflineReconciler;
use App\Modules\Optional\TimeTracking\Domain\Offline\OfflineReconciliationDecision;
use App\Modules\Optional\TimeTracking\Domain\Offline\OfflineReconciliationPolicy;
use App\Modules\Optional\TimeTracking\Domain\Offline\OfflineReconciliationRequest;
use App\Modules\Optional\TimeTracking\Domain\Offline\OfflineReconciliationState;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class OfflineReconcilerTest extends TestCase
{
    public function test_it_accepts_ordered_monotonic_elapsed_time_for_the_active_lease(): void
    {
        $anchor = $this->instant('2026-08-01 08:00:00');

        $result = (new OfflineReconciler)->reconcile(
            request: new OfflineReconciliationRequest(
                workSessionPublicId: '01K1J79A0Q9Q70R99P9GF7K8DX',
                deviceLeaseId: 'lease-primary',
                sequence: 12,
                serverAnchorAt: $anchor,
                monotonicElapsedSeconds: 120,
            ),
            state: $this->state(receivedAt: $anchor->add(new DateInterval('PT125S')), lastAcceptedSequence: 11),
            policy: $this->policy(),
        );

        self::assertTrue($result->accepted());
        self::assertSame(OfflineReconciliationDecision::AcceptedActive, $result->decision);
        self::assertSame(120, $result->acceptedSeconds);
        self::assertSame('2026-08-01T08:02:00+02:00', $result->countedEndsAt?->format(DATE_ATOM));
    }

    public function test_it_ends_counted_work_at_inactivity_threshold_when_offline_too_long(): void
    {
        $anchor = $this->instant('2026-08-01 08:00:00');

        $result = (new OfflineReconciler)->reconcile(
            request: new OfflineReconciliationRequest(
                workSessionPublicId: '01K1J79A0Q9Q70R99P9GF7K8DX',
                deviceLeaseId: 'lease-primary',
                sequence: 12,
                serverAnchorAt: $anchor,
                monotonicElapsedSeconds: 601,
            ),
            state: $this->state(receivedAt: $anchor->add(new DateInterval('PT601S')), lastAcceptedSequence: 11),
            policy: $this->policy(),
        );

        self::assertTrue($result->accepted());
        self::assertSame(OfflineReconciliationDecision::AcceptedEndedByInactivity, $result->decision);
        self::assertSame(600, $result->acceptedSeconds);
        self::assertSame('2026-08-01T08:10:00+02:00', $result->countedEndsAt?->format(DATE_ATOM));
    }

    public function test_it_rejects_duplicate_and_reordered_sequences(): void
    {
        $anchor = $this->instant('2026-08-01 08:00:00');
        $reconciler = new OfflineReconciler;

        $duplicate = $reconciler->reconcile(
            request: $this->request(sequence: 12, anchor: $anchor),
            state: $this->state(receivedAt: $anchor->add(new DateInterval('PT120S')), lastAcceptedSequence: 12),
            policy: $this->policy(),
        );
        $reordered = $reconciler->reconcile(
            request: $this->request(sequence: 11, anchor: $anchor),
            state: $this->state(receivedAt: $anchor->add(new DateInterval('PT120S')), lastAcceptedSequence: 12),
            policy: $this->policy(),
        );

        self::assertSame(OfflineReconciliationDecision::RejectedDuplicate, $duplicate->decision);
        self::assertSame(OfflineReconciliationDecision::RejectedReordered, $reordered->decision);
    }

    public function test_it_rejects_expired_sessions_parallel_work_excessive_gap_and_clock_anomalies(): void
    {
        $anchor = $this->instant('2026-08-01 08:00:00');
        $reconciler = new OfflineReconciler;

        $expired = $reconciler->reconcile(
            request: $this->request(anchor: $anchor, workSessionPublicId: 'expired-session'),
            state: $this->state(receivedAt: $anchor->add(new DateInterval('PT120S'))),
            policy: $this->policy(),
        );
        $parallel = $reconciler->reconcile(
            request: $this->request(anchor: $anchor, deviceLeaseId: 'other-lease'),
            state: $this->state(receivedAt: $anchor->add(new DateInterval('PT120S'))),
            policy: $this->policy(),
        );
        $excessiveGap = $reconciler->reconcile(
            request: $this->request(anchor: $anchor, monotonicElapsedSeconds: 3601),
            state: $this->state(receivedAt: $anchor->add(new DateInterval('PT3601S'))),
            policy: $this->policy(),
        );
        $clockAnomaly = $reconciler->reconcile(
            request: $this->request(anchor: $anchor, monotonicElapsedSeconds: 120),
            state: $this->state(receivedAt: $anchor->add(new DateInterval('PT300S'))),
            policy: $this->policy(),
        );

        self::assertSame(OfflineReconciliationDecision::RejectedExpiredSession, $expired->decision);
        self::assertSame(OfflineReconciliationDecision::RejectedParallelWork, $parallel->decision);
        self::assertSame(OfflineReconciliationDecision::RejectedExcessiveGap, $excessiveGap->decision);
        self::assertSame(OfflineReconciliationDecision::RejectedClockAnomaly, $clockAnomaly->decision);
    }

    private function request(
        int $sequence = 12,
        ?DateTimeImmutable $anchor = null,
        string $workSessionPublicId = '01K1J79A0Q9Q70R99P9GF7K8DX',
        string $deviceLeaseId = 'lease-primary',
        int $monotonicElapsedSeconds = 120,
    ): OfflineReconciliationRequest {
        return new OfflineReconciliationRequest(
            workSessionPublicId: $workSessionPublicId,
            deviceLeaseId: $deviceLeaseId,
            sequence: $sequence,
            serverAnchorAt: $anchor ?? $this->instant('2026-08-01 08:00:00'),
            monotonicElapsedSeconds: $monotonicElapsedSeconds,
        );
    }

    private function state(
        DateTimeImmutable $receivedAt,
        int $lastAcceptedSequence = 11,
    ): OfflineReconciliationState {
        return new OfflineReconciliationState(
            activeWorkSessionPublicId: '01K1J79A0Q9Q70R99P9GF7K8DX',
            activeDeviceLeaseId: 'lease-primary',
            lastAcceptedSequence: $lastAcceptedSequence,
            receivedAt: $receivedAt,
            sessionExpiresAt: $receivedAt->add(new DateInterval('PT1H')),
        );
    }

    private function policy(): OfflineReconciliationPolicy
    {
        return new OfflineReconciliationPolicy(
            inactivityThresholdSeconds: 600,
            maximumOfflineGapSeconds: 3600,
            clockSkewToleranceSeconds: 30,
        );
    }

    private function instant(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('Europe/Warsaw'));
    }
}
