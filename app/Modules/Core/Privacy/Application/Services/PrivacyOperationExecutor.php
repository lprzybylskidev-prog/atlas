<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Application\Services;

use App\Modules\Core\Privacy\Application\DTOs\PrivacyExecutionResult;
use App\Modules\Core\Privacy\Application\Enums\PrivacyOperation;
use App\Modules\Core\Privacy\Application\Exceptions\PrivacyOperationExecutionException;
use App\Modules\Core\Privacy\Infrastructure\Persistence\TableNames\PrivacyDatabaseTable;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use App\Shared\Application\Audit\Enums\SecurityAuditCategory;
use App\Shared\Application\DataLifecycle\Contracts\DataLifecycleParticipant;
use App\Shared\Application\DataLifecycle\DataLifecycleImpact;
use App\Shared\Application\DataLifecycle\DataLifecycleStepResult;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Throwable;

final readonly class PrivacyOperationExecutor
{
    public function __construct(
        private ConnectionInterface $db,
        private DataLifecycleParticipantRegistry $participants,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $operationRequestPublicId,
        PrivacyOperation $expectedOperation,
        string $confirmationPhrase,
        int $actorUserId,
        ?string $actorPublicId,
        ?string $teamPublicId,
        ?string $correlationId = null,
    ): PrivacyExecutionResult {
        try {
            return $this->db->transaction(function () use ($operationRequestPublicId, $expectedOperation, $confirmationPhrase, $actorUserId, $actorPublicId, $teamPublicId, $correlationId): PrivacyExecutionResult {
                $request = $this->reserveExecutableRequest($operationRequestPublicId, $expectedOperation, $confirmationPhrase);
                $operation = PrivacyOperation::from($request['operation']);
                $subject = new DataLifecycleSubject($request['subject_type'], $request['subject_identifier']);
                $participants = $this->participants->all();

                $this->assertPreviewCurrent($request, $subject, $operation, $participants);

                $steps = [];

                foreach ($participants as $participant) {
                    try {
                        $result = $participant->execute($subject, $operation->lifecycleOperation(), $correlationId ?? $request['correlation_id']);
                    } catch (Throwable) {
                        throw new PrivacyOperationExecutionException('privacy_execution_participant_failed');
                    }

                    if ($result->blockers !== []) {
                        throw new PrivacyOperationExecutionException('privacy_execution_stale_preview');
                    }

                    array_push($steps, ...$result->steps);
                }

                $stepPayload = array_map($this->stepPayload(...), $steps);
                $affectedRecords = array_sum(array_map(
                    static fn (array $step): int => $step['affectedRecords'],
                    $stepPayload,
                ));

                $this->finalize(
                    requestId: $request['id'],
                    publicId: $request['public_id'],
                    operation: $operation,
                    subject: $subject,
                    status: 'executed',
                    completed: true,
                    actorUserId: $actorUserId,
                    actorPublicId: $actorPublicId,
                    teamPublicId: $teamPublicId,
                    correlationId: $correlationId ?? $request['correlation_id'],
                    reason: $request['reason'],
                    steps: $stepPayload,
                    blockers: [],
                    affectedRecords: $affectedRecords,
                );

                return new PrivacyExecutionResult(
                    publicId: $request['public_id'],
                    status: 'executed',
                    steps: $stepPayload,
                    blockers: [],
                    affectedRecords: $affectedRecords,
                    completed: true,
                );
            });
        } catch (PrivacyOperationExecutionException $exception) {
            $this->recordRejectedAttempt(
                operationRequestPublicId: $operationRequestPublicId,
                operation: $expectedOperation,
                actorPublicId: $actorPublicId,
                teamPublicId: $teamPublicId,
                correlationId: $correlationId,
                failureCode: $exception->errorKey,
            );

            throw $exception;
        }
    }

    /**
     * @return array{id: int, public_id: string, operation: string, subject_type: string, subject_identifier: string, reason: string, correlation_id: string, impacts: string, blockers: string, participant_count: int, snapshot_hash: string}
     */
    private function reserveExecutableRequest(string $operationRequestPublicId, PrivacyOperation $expectedOperation, string $confirmationPhrase): array
    {
        $request = $this->db->table(PrivacyDatabaseTable::OPERATION_REQUESTS.' as requests')
            ->join(PrivacyDatabaseTable::OPERATION_PREVIEWS.' as previews', 'previews.operation_request_id', '=', 'requests.id')
            ->where('requests.public_id', $operationRequestPublicId)
            ->orderByDesc('previews.created_at')
            ->lockForUpdate()
            ->first([
                'requests.id',
                'requests.public_id',
                'requests.operation',
                'requests.subject_type',
                'requests.subject_identifier',
                'requests.status',
                'requests.reason',
                'requests.confirmation_phrase',
                'requests.correlation_id',
                'previews.impacts',
                'previews.blockers',
                'previews.participant_count',
                'previews.snapshot_hash',
                'previews.can_execute',
            ]);

        if (! is_object($request)) {
            throw new PrivacyOperationExecutionException('privacy_execution_not_found');
        }

        $values = (array) $request;
        $requestId = $this->intValue($values['id'] ?? null);
        $publicId = $this->requiredString($values['public_id'] ?? null);
        $operation = $this->requiredString($values['operation'] ?? null);
        $subjectType = $this->requiredString($values['subject_type'] ?? null);
        $subjectIdentifier = $this->requiredString($values['subject_identifier'] ?? null);
        $status = $this->requiredString($values['status'] ?? null);
        $reason = $this->requiredString($values['reason'] ?? null);
        $storedConfirmationPhrase = $this->requiredString($values['confirmation_phrase'] ?? null);
        $correlationId = $this->requiredString($values['correlation_id'] ?? null);
        $canExecute = (bool) ($values['can_execute'] ?? false);

        if ($storedConfirmationPhrase !== $confirmationPhrase) {
            throw new PrivacyOperationExecutionException('privacy_execution_confirmation_mismatch');
        }

        if ($operation !== $expectedOperation->value) {
            throw new PrivacyOperationExecutionException('privacy_execution_operation_mismatch');
        }

        if ($status !== 'previewed' || $canExecute !== true) {
            throw new PrivacyOperationExecutionException('privacy_execution_not_executable');
        }

        $this->db->table(PrivacyDatabaseTable::OPERATION_REQUESTS)
            ->where('id', $requestId)
            ->update([
                'status' => 'executing',
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

        return [
            'id' => $requestId,
            'public_id' => $publicId,
            'operation' => $operation,
            'subject_type' => $subjectType,
            'subject_identifier' => $subjectIdentifier,
            'reason' => $reason,
            'correlation_id' => $correlationId,
            'impacts' => $this->requiredString($values['impacts'] ?? null),
            'blockers' => $this->requiredString($values['blockers'] ?? null),
            'participant_count' => $this->intValue($values['participant_count'] ?? null),
            'snapshot_hash' => $this->requiredString($values['snapshot_hash'] ?? null),
        ];
    }

    /**
     * @param  list<array{step: string, affectedRecords: int, idempotent: bool}>  $steps
     * @param  list<array{code: string, message: string}>  $blockers
     */
    private function finalize(
        int $requestId,
        string $publicId,
        PrivacyOperation $operation,
        DataLifecycleSubject $subject,
        string $status,
        bool $completed,
        int $actorUserId,
        ?string $actorPublicId,
        ?string $teamPublicId,
        ?string $correlationId,
        string $reason,
        array $steps,
        array $blockers,
        int $affectedRecords,
    ): void {
        $this->db->table(PrivacyDatabaseTable::OPERATION_REQUESTS)
            ->where('id', $requestId)
            ->update([
                'status' => $status,
                'dry_run' => false,
                'executed_at' => $completed ? now() : null,
                'metadata' => $this->json([
                    'executed_by_user_id' => $actorUserId,
                    'affected_records' => $affectedRecords,
                    'step_count' => count($steps),
                    'blocker_codes' => array_map(static fn (array $blocker): string => $blocker['code'], $blockers),
                    'steps' => $steps,
                ]),
                'updated_at' => now(),
            ]);

        $this->audit->record(new AuditEvent(
            module: 'privacy',
            action: $this->auditAction($operation),
            result: $completed ? 'succeeded' : ($this->hasExecutionFailure($blockers) ? 'failed' : 'rejected'),
            source: 'ui',
            actorPublicId: $actorPublicId,
            targetType: $subject->type,
            targetPublicId: Str::isUlid($subject->identifier) ? $subject->identifier : null,
            aggregateType: 'privacy_operation_request',
            aggregatePublicId: $publicId,
            teamPublicId: $teamPublicId,
            correlationId: $correlationId,
            reason: $reason,
            metadata: [
                'operation' => $operation->value,
                'affected_records' => $affectedRecords,
                'step_count' => count($steps),
                'blocker_codes' => array_map(static fn (array $blocker): string => $blocker['code'], $blockers),
            ],
            security: true,
            securityCategory: SecurityAuditCategory::Privacy,
        ));
    }

    /**
     * @param  array{id: int, public_id: string, operation: string, subject_type: string, subject_identifier: string, reason: string, correlation_id: string, impacts: string, blockers: string, participant_count: int, snapshot_hash: string}  $request
     * @param  list<DataLifecycleParticipant>  $participants
     */
    private function assertPreviewCurrent(array $request, DataLifecycleSubject $subject, PrivacyOperation $operation, array $participants): void
    {
        if ($this->hasActiveLegalHold($subject->type, $subject->identifier)
            || $request['participant_count'] !== count($participants)
        ) {
            throw new PrivacyOperationExecutionException('privacy_execution_stale_preview');
        }

        $impacts = [];

        foreach ($participants as $participant) {
            try {
                $preview = $participant->preview($subject, $operation->lifecycleOperation());
            } catch (Throwable) {
                throw new PrivacyOperationExecutionException('privacy_execution_stale_preview');
            }

            if ($preview->blockers !== []) {
                throw new PrivacyOperationExecutionException('privacy_execution_stale_preview');
            }

            array_push($impacts, ...$preview->impacts);
        }

        $impacts = array_values(array_filter(
            $impacts,
            static fn (DataLifecycleImpact $impact): bool => $impact->estimatedRecords > 0,
        ));

        if ($impacts === []) {
            throw new PrivacyOperationExecutionException('privacy_execution_stale_preview');
        }

        $payload = array_map($this->impactPayload(...), $impacts);
        $currentHash = hash('sha256', $this->json([
            'impacts' => $payload,
            'blockers' => [],
            'participant_count' => count($participants),
        ]));

        if (! hash_equals($request['snapshot_hash'], $currentHash)) {
            throw new PrivacyOperationExecutionException('privacy_execution_stale_preview');
        }
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
     * @return array{step: string, affectedRecords: int, idempotent: bool}
     */
    private function stepPayload(DataLifecycleStepResult $step): array
    {
        return [
            'step' => $step->step,
            'affectedRecords' => $step->affectedRecords,
            'idempotent' => $step->idempotent,
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
            PrivacyOperation::HardDelete => 'privacy.hard_delete_executed',
            PrivacyOperation::Anonymization => 'privacy.anonymization_executed',
        };
    }

    private function recordRejectedAttempt(
        string $operationRequestPublicId,
        PrivacyOperation $operation,
        ?string $actorPublicId,
        ?string $teamPublicId,
        ?string $correlationId,
        string $failureCode,
    ): void {
        $record = function () use ($operationRequestPublicId, $operation, $actorPublicId, $teamPublicId, $correlationId, $failureCode): void {
            if (in_array($failureCode, ['privacy_execution_participant_failed', 'privacy_execution_stale_preview'], true)) {
                $this->db->table(PrivacyDatabaseTable::OPERATION_REQUESTS)
                    ->where('public_id', $operationRequestPublicId)
                    ->whereIn('status', ['previewed', 'executing'])
                    ->update([
                        'status' => 'blocked',
                        'updated_at' => now(),
                    ]);
            }

            $this->audit->record(new AuditEvent(
                module: 'privacy',
                action: $this->auditAction($operation),
                result: $failureCode === 'privacy_execution_participant_failed' ? 'failed' : 'rejected',
                source: 'ui',
                actorPublicId: $actorPublicId,
                aggregateType: 'privacy_operation_request',
                aggregatePublicId: Str::isUlid($operationRequestPublicId) ? $operationRequestPublicId : null,
                teamPublicId: $teamPublicId,
                correlationId: $correlationId,
                reason: $failureCode,
                metadata: [
                    'operation' => $operation->value,
                    'failure_code' => $failureCode,
                ],
                security: true,
                securityCategory: SecurityAuditCategory::Privacy,
            ));
        };

        $this->db->transaction($record);
    }

    /** @param list<array{code: string, message: string}> $blockers */
    private function hasExecutionFailure(array $blockers): bool
    {
        return array_any($blockers, static fn (array $blocker): bool => $blocker['code'] === 'participant_execution_failed');
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

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function requiredString(mixed $value): string
    {
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }
}
