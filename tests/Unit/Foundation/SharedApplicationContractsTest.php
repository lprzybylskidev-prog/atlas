<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Shared\Application\DataLifecycle\DataLifecycleBlocker;
use App\Shared\Application\DataLifecycle\DataLifecycleImpact;
use App\Shared\Application\DataLifecycle\DataLifecyclePreview;
use App\Shared\Application\DataLifecycle\DataLifecycleResult;
use App\Shared\Application\DataLifecycle\DataLifecycleStepResult;
use App\Shared\Application\Modules\ModuleDeactivationAssessment;
use App\Shared\Application\Modules\ModuleDeactivationBlocker;
use App\Shared\Application\Modules\ModuleDeactivationSafeAction;
use App\Shared\Application\Queries\CursorPageResult;
use App\Shared\Application\Queries\PageCursor;
use App\Shared\Application\Queries\PageMetadata;
use App\Shared\Application\Queries\PageResult;
use App\Shared\Application\Queries\TypedCollectionResult;
use PHPUnit\Framework\TestCase;

final class SharedApplicationContractsTest extends TestCase
{
    public function test_module_deactivation_assessment_reports_blockers_and_safe_actions(): void
    {
        $assessment = ModuleDeactivationAssessment::block(
            new ModuleDeactivationBlocker(
                processType: 'import',
                processIdentifier: 'import-01',
                reason: 'Import is still running.',
            ),
            [
                new ModuleDeactivationSafeAction(
                    action: 'cancel_import',
                    label: 'Cancel import',
                ),
            ],
        );

        self::assertFalse($assessment->canDeactivate());
        self::assertSame('import', $assessment->blockers[0]->processType);
        self::assertSame('cancel_import', $assessment->safeActions[0]->action);
    }

    public function test_framework_independent_query_results_carry_typed_items_and_pagination_metadata(): void
    {
        $first = new ContractProbeDto('first');
        $second = new ContractProbeDto('second');

        $collection = new TypedCollectionResult(ContractProbeDto::class, [$first, $second]);
        $page = new PageResult(ContractProbeDto::class, [$first], new PageMetadata(page: 1, perPage: 10, totalItems: 2));
        $cursorPage = new CursorPageResult(ContractProbeDto::class, [$second], nextCursor: new PageCursor('next-cursor'));

        self::assertSame(2, $collection->count());
        self::assertSame(2, $page->metadata->totalItems);
        self::assertTrue($cursorPage->hasMore());
    }

    public function test_data_lifecycle_preview_and_result_expose_impacts_blockers_and_idempotent_steps(): void
    {
        $preview = new DataLifecyclePreview(
            impacts: [
                new DataLifecycleImpact(
                    dataSet: 'identity.users',
                    estimatedRecords: 1,
                    irreversible: true,
                    details: [['public_id' => '01J00000000000000000000001']],
                ),
            ],
            blockers: [new DataLifecycleBlocker(code: 'active-case', message: 'Subject has an active case.')],
        );
        $result = new DataLifecycleResult(
            steps: [new DataLifecycleStepResult(step: 'anonymize_identity_user', affectedRecords: 1, idempotent: true)],
        );

        self::assertFalse($preview->canExecute());
        self::assertSame('identity.users', $preview->impacts[0]->dataSet);
        self::assertSame('01J00000000000000000000001', $preview->impacts[0]->details[0]['public_id']);
        self::assertTrue($result->completed());
        self::assertTrue($result->steps[0]->idempotent);
    }
}

final readonly class ContractProbeDto
{
    public function __construct(public string $name) {}
}
