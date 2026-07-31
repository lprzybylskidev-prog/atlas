<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Application\Services;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Privacy\Application\DTOs\PrivacyExecutionResult;
use App\Modules\Core\Privacy\Application\Enums\PrivacyOperation;
use App\Modules\Core\Privacy\Application\Exceptions\PrivacyOperationExecutionException;
use App\Shared\Application\DataLifecycle\DataLifecycleBlocker;
use App\Shared\Application\DataLifecycle\DataLifecycleStepResult;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use App\Shared\Infrastructure\Database\DatabaseTable;
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
        $request = $this->reserveExecutableRequest($operationRequestPublicId, $expectedOperation, $confirmationPhrase);
        $operation = PrivacyOperation::from($request['operation']);
        $subject = new DataLifecycleSubject($request['subject_type'], $request['subject_identifier']);
        $participants = $this->participants->all();
        $steps = [];
        $blockers = [];

        if ($this->hasActiveLegalHold($subject->type, $subject->identifier)) {
            $blockers[] = new DataLifecycleBlocker(
                code: 'active_legal_hold',
                message: 'Subject has an active legal hold or retention exception.',
            );
        }

        foreach ($participants as $participant) {
            if ($blockers !== []) {
                break;
            }

            try {
                $result = $participant->execute($subject, $operation->lifecycleOperation(), $correlationId ?? $request['correlation_id']);
            } catch (Throwable $exception) {
                $blockers[] = new DataLifecycleBlocker(
                    code: 'participant_execution_failed',
                    message: $exception::class,
                );

                break;
            }

            array_push($steps, ...$result->steps);
            array_push($blockers, ...$result->blockers);
        }

        $stepPayload = array_map($this->stepPayload(...), $steps);
        $blockerPayload = array_map($this->blockerPayload(...), $blockers);
        $affectedRecords = array_sum(array_map(
            static fn (array $step): int => $step['affectedRecords'],
            $stepPayload,
        ));
        $completed = $blockerPayload === [];
        $status = $completed ? 'executed' : 'blocked';

        $this->finalize(
            requestId: $request['id'],
            publicId: $request['public_id'],
            operation: $operation,
            subject: $subject,
            status: $status,
            completed: $completed,
            actorUserId: $actorUserId,
            actorPublicId: $actorPublicId,
            teamPublicId: $teamPublicId,
            correlationId: $correlationId ?? $request['correlation_id'],
            reason: $request['reason'],
            steps: $stepPayload,
            blockers: $blockerPayload,
            affectedRecords: $affectedRecords,
        );

        return new PrivacyExecutionResult(
            publicId: $request['public_id'],
            status: $status,
            steps: $stepPayload,
            blockers: $blockerPayload,
            affectedRecords: $affectedRecords,
            completed: $completed,
        );
    }

    /**
     * @return array{id: int, public_id: string, operation: string, subject_type: string, subject_identifier: string, reason: string, correlation_id: string}
     */
    private function reserveExecutableRequest(string $operationRequestPublicId, PrivacyOperation $expectedOperation, string $confirmationPhrase): array
    {
        return $this->db->transaction(function () use ($operationRequestPublicId, $expectedOperation, $confirmationPhrase): array {
            $request = $this->db->table(DatabaseTable::PRIVACY_OPERATION_REQUESTS.' as requests')
                ->join(DatabaseTable::PRIVACY_OPERATION_PREVIEWS.' as previews', 'previews.operation_request_id', '=', 'requests.id')
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

            $this->db->table(DatabaseTable::PRIVACY_OPERATION_REQUESTS)
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
            ];
        });
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
        $this->db->transaction(function () use ($requestId, $publicId, $operation, $subject, $status, $completed, $actorUserId, $actorPublicId, $teamPublicId, $correlationId, $reason, $steps, $blockers, $affectedRecords): void {
            $this->db->table(DatabaseTable::PRIVACY_OPERATION_REQUESTS)
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
                result: $completed ? 'succeeded' : 'rejected',
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
        });
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
            PrivacyOperation::HardDelete => 'privacy.hard_delete_executed',
            PrivacyOperation::Anonymization => 'privacy.anonymization_executed',
        };
    }

    private function hasActiveLegalHold(string $subjectType, string $subjectIdentifier): bool
    {
        return $this->db->table(DatabaseTable::PRIVACY_LEGAL_HOLDS)
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
