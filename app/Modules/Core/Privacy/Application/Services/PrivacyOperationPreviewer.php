<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Application\Services;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Privacy\Application\DTOs\PrivacyPreviewCommand;
use App\Modules\Core\Privacy\Application\DTOs\PrivacyPreviewResult;
use App\Modules\Core\Privacy\Application\Enums\PrivacyOperation;
use App\Modules\Core\Privacy\Application\Public\Persistence\PrivacyDatabaseTable;
use App\Shared\Application\DataLifecycle\DataLifecycleBlocker;
use App\Shared\Application\DataLifecycle\DataLifecycleImpact;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Throwable;

final readonly class PrivacyOperationPreviewer
{
    public function __construct(
        private ConnectionInterface $db,
        private DataLifecycleParticipantRegistry $participants,
        private PrivacyRetentionCoverageCatalog $coverage,
        private AuditRecorder $audit,
    ) {}

    public function preview(PrivacyPreviewCommand $command): PrivacyPreviewResult
    {
        $subject = new DataLifecycleSubject($command->subjectType, $command->subjectIdentifier);
        $participants = $this->participants->all();
        $impacts = [];
        $blockers = [];

        foreach ($participants as $participant) {
            try {
                $preview = $participant->preview($subject, $command->operation->lifecycleOperation());
            } catch (Throwable $exception) {
                $blockers[] = new DataLifecycleBlocker(
                    code: 'participant_preview_failed',
                    message: $exception::class,
                );

                continue;
            }

            array_push($impacts, ...$preview->impacts);
            array_push($blockers, ...$preview->blockers);
        }

        $impacts = array_values(array_filter(
            $impacts,
            static fn (DataLifecycleImpact $impact): bool => $impact->estimatedRecords > 0,
        ));

        if ($participants === []) {
            $blockers[] = new DataLifecycleBlocker(
                code: 'no_lifecycle_participants',
                message: 'No data lifecycle participants are registered.',
            );
        }

        if ($this->hasActiveLegalHold($command->subjectType, $command->subjectIdentifier)) {
            $blockers[] = new DataLifecycleBlocker(
                code: 'active_legal_hold',
                message: 'Subject has an active legal hold or retention exception.',
            );
        }

        if ($impacts === []) {
            $blockers[] = new DataLifecycleBlocker(
                code: 'no_controlled_copy_impact',
                message: 'No controlled-copy participant reported an impact for this subject.',
            );
        }

        $incompleteCoverage = array_filter(
            $this->coverage->items($this->participants->classNames()),
            static fn ($item): bool => $item->coverage !== 'implemented',
        );

        if ($incompleteCoverage !== []) {
            $blockers[] = new DataLifecycleBlocker(
                code: 'controlled_copy_coverage_incomplete',
                message: 'One or more controlled-copy areas are not fully implemented for privacy execution.',
            );
        }

        $impactPayload = array_map($this->impactPayload(...), $impacts);
        $blockerPayload = array_map($this->blockerPayload(...), $blockers);
        $estimatedRecords = array_sum(array_map(
            static fn (array $impact): int => $impact['estimatedRecords'],
            $impactPayload,
        ));
        $canExecute = $blockerPayload === [];
        $status = $canExecute ? 'previewed' : 'blocked';
        $publicId = (string) Str::ulid();
        $confirmationPhrase = $command->operation->confirmationPhrase($command->subjectIdentifier);

        $this->db->transaction(function () use ($command, $publicId, $confirmationPhrase, $status, $impactPayload, $blockerPayload, $participants, $estimatedRecords, $canExecute): void {
            $requestId = $this->db->table(PrivacyDatabaseTable::OPERATION_REQUESTS)->insertGetId([
                'public_id' => $publicId,
                'operation' => $command->operation->value,
                'subject_type' => $command->subjectType,
                'subject_identifier' => $command->subjectIdentifier,
                'status' => $status,
                'dry_run' => $command->dryRun,
                'requested_by_user_id' => $command->actorUserId,
                'team_id' => $command->teamId,
                'reason' => $command->reason,
                'confirmation_phrase' => $confirmationPhrase,
                'correlation_id' => $command->correlationId,
                'previewed_at' => now(),
                'metadata' => $this->json([
                    'participant_count' => count($participants),
                    'can_execute' => $canExecute,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->db->table(PrivacyDatabaseTable::OPERATION_PREVIEWS)->insert([
                'operation_request_id' => $requestId,
                'impacts' => $this->json($impactPayload),
                'blockers' => $this->json($blockerPayload),
                'participant_count' => count($participants),
                'estimated_records' => $estimatedRecords,
                'can_execute' => $canExecute,
                'created_at' => now(),
            ]);

            $this->audit->record(new AuditEvent(
                module: 'privacy',
                action: $this->auditAction($command->operation),
                result: $canExecute ? 'succeeded' : 'rejected',
                source: 'ui',
                actorPublicId: $command->actorPublicId,
                targetType: $command->subjectType,
                targetPublicId: Str::isUlid($command->subjectIdentifier) ? $command->subjectIdentifier : null,
                aggregateType: 'privacy_operation_request',
                aggregatePublicId: $publicId,
                teamPublicId: $command->teamPublicId,
                correlationId: $command->correlationId,
                reason: $command->reason,
                metadata: [
                    'operation' => $command->operation->value,
                    'dry_run' => $command->dryRun,
                    'participant_count' => count($participants),
                    'estimated_records' => $estimatedRecords,
                    'blocker_codes' => array_map(static fn (array $blocker): string => $blocker['code'], $blockerPayload),
                ],
                security: true,
                securityCategory: SecurityAuditCategory::Privacy,
            ));
        });

        return new PrivacyPreviewResult(
            publicId: $publicId,
            status: $status,
            confirmationPhrase: $confirmationPhrase,
            impacts: $impactPayload,
            blockers: $blockerPayload,
            participantCount: count($participants),
            estimatedRecords: $estimatedRecords,
            canExecute: $canExecute,
        );
    }

    /**
     * @return array{dataSet: string, estimatedRecords: int, irreversible: bool, details: list<array<string, mixed>>}
     */
    private function impactPayload(DataLifecycleImpact $impact): array
    {
        return [
            'dataSet' => $impact->dataSet,
            'estimatedRecords' => $impact->estimatedRecords,
            'irreversible' => $impact->irreversible,
            'details' => $impact->details,
        ];
    }

    /**
     * @return array{code: string, message: string}
     */
    private function blockerPayload(DataLifecycleBlocker $blocker): array
    {
        return [
            'code' => $blocker->code,
            'message' => $blocker->message,
        ];
    }

    /**
     * @param  array<mixed>  $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function auditAction(PrivacyOperation $operation): string
    {
        return match ($operation) {
            PrivacyOperation::HardDelete => 'privacy.hard_delete_previewed',
            PrivacyOperation::Anonymization => 'privacy.anonymization_previewed',
        };
    }

    private function hasActiveLegalHold(string $subjectType, string $subjectIdentifier): bool
    {
        return $this->db->table(PrivacyDatabaseTable::LEGAL_HOLDS)
            ->where('subject_type', $subjectType)
            ->where('subject_identifier', $subjectIdentifier)
            ->whereNull('released_at')
            ->where(static function (Builder $query): void {
                $query
                    ->whereNull('expires_on')
                    ->orWhere('expires_on', '>=', now('UTC')->toDateString());
            })
            ->exists();
    }
}
