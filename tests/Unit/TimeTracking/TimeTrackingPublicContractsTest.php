<?php

declare(strict_types=1);

namespace Tests\Unit\TimeTracking;

use App\Modules\Optional\TimeTracking\Application\Public\Contracts\BusinessEventRecorder;
use App\Modules\Optional\TimeTracking\Application\Public\Contracts\MetricDefinitionProvider;
use App\Modules\Optional\TimeTracking\Application\Public\Contracts\MetricRecalculator;
use App\Modules\Optional\TimeTracking\Application\Public\DTOs\AnalysisContextSnapshot;
use App\Modules\Optional\TimeTracking\Application\Public\DTOs\BusinessEventInput;
use App\Modules\Optional\TimeTracking\Application\Public\DTOs\DerivedMetricResult;
use App\Modules\Optional\TimeTracking\Application\Public\DTOs\MetricDefinition;
use App\Modules\Optional\TimeTracking\Application\Public\DTOs\MetricDefinitionSnapshot;
use App\Modules\Optional\TimeTracking\Application\Public\DTOs\MetricRecalculationRequest;
use App\Modules\Optional\TimeTracking\Application\Public\DTOs\MetricRecalculationResult;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TimeTrackingPublicContractsTest extends TestCase
{
    public function test_business_events_capture_source_trace_and_analysis_context(): void
    {
        $capturedAt = $this->instant('2026-08-01 08:00:00');
        $snapshot = new AnalysisContextSnapshot(
            teamPublicId: '01K1J7APZKQ63CJS7HZAH4NX2M',
            teamName: 'Collection Team A',
            roleKeys: ['collector', 'case_reviewer'],
            processPublicId: 'process-2026-08-01',
            processKey: 'case_review',
            moduleKey: 'cases',
            capturedAt: $capturedAt,
        );

        $event = new BusinessEventInput(
            sourceEventId: 'cases:reviewed:123',
            ownerModuleKey: 'cases',
            eventKey: 'case.reviewed',
            schemaVersion: 1,
            occurredAt: $capturedAt,
            userPublicId: '01K1J79A0Q9Q70R99P9GF7K8DX',
            teamPublicId: '01K1J7APZKQ63CJS7HZAH4NX2M',
            workSessionPublicId: 'work-session-1',
            contextSnapshot: $snapshot,
            attributes: ['queue' => 'priority', 'manual' => true],
            metricInputs: ['reviewed_cases' => 1],
        );

        self::assertSame('cases:reviewed:123', $event->sourceEventId);
        self::assertSame('cases', $event->contextSnapshot->moduleKey);
        self::assertSame(['collector', 'case_reviewer'], $event->contextSnapshot->roleKeys);
        self::assertSame(1, $event->metricInputs['reviewed_cases']);
    }

    public function test_metric_definitions_are_versioned_and_results_are_traceable(): void
    {
        $definition = new MetricDefinition(
            metricKey: 'cases.reviewed',
            ownerModuleKey: 'cases',
            ruleVersion: 2,
            calculationRuleKey: 'sum.reviewed_cases',
            labelTranslationKey: 'time_tracking.metrics.cases_reviewed',
            sourceEventKeys: ['case.reviewed'],
        );
        $result = new DerivedMetricResult(
            metricKey: 'cases.reviewed',
            ruleVersion: 2,
            value: 15.0,
            sourceEventIds: ['cases:reviewed:123'],
            calculatedAt: $this->instant('2026-08-01 12:00:00'),
        );

        self::assertSame(2, $definition->ruleVersion);
        self::assertSame(['case.reviewed'], $definition->sourceEventKeys);
        self::assertSame(['cases:reviewed:123'], $result->sourceEventIds);
    }

    public function test_recalculation_request_selects_a_metric_rule_version_and_range(): void
    {
        $definition = new MetricDefinition(
            metricKey: 'cases.reviewed',
            ownerModuleKey: 'cases',
            ruleVersion: 2,
            calculationRuleKey: 'sum.reviewed_cases',
            labelTranslationKey: 'time_tracking.metrics.cases_reviewed',
            sourceEventKeys: ['case.reviewed'],
        );
        $result = new MetricRecalculationResult(
            definitionSnapshot: new MetricDefinitionSnapshot($definition, $this->instant('2026-08-01 12:00:00')),
            results: [
                new DerivedMetricResult(
                    metricKey: 'cases.reviewed',
                    ruleVersion: 2,
                    value: 15.0,
                    sourceEventIds: ['cases:reviewed:123'],
                    calculatedAt: $this->instant('2026-08-01 12:00:00'),
                ),
            ],
        );
        $request = new MetricRecalculationRequest(
            metricKey: 'cases.reviewed',
            ruleVersion: 2,
            startsAt: $this->instant('2026-08-01 00:00:00'),
            endsAt: $this->instant('2026-08-02 00:00:00'),
            teamPublicId: '01K1J7APZKQ63CJS7HZAH4NX2M',
        );

        self::assertSame('cases.reviewed', $request->metricKey);
        self::assertSame(2, $request->ruleVersion);
        self::assertSame($definition, $result->definitionSnapshot->definition);
        self::assertCount(1, $result->results);
    }

    public function test_public_api_contracts_are_explicit_interfaces(): void
    {
        self::assertTrue(interface_exists(BusinessEventRecorder::class));
        self::assertTrue(interface_exists(MetricDefinitionProvider::class));
        self::assertTrue(interface_exists(MetricRecalculator::class));
    }

    public function test_metric_results_require_source_traceability(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DerivedMetricResult(
            metricKey: 'cases.reviewed',
            ruleVersion: 1,
            value: 1.0,
            sourceEventIds: [],
            calculatedAt: $this->instant('2026-08-01 12:00:00'),
        );
    }

    private function instant(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('Europe/Warsaw'));
    }
}
