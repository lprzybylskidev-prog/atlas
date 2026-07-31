<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\HighRiskAdministrativeAuthorization;
use App\Modules\Core\Privacy\Application\DTOs\PrivacyPreviewCommand;
use App\Modules\Core\Privacy\Application\Enums\PrivacyOperation;
use App\Modules\Core\Privacy\Application\Exceptions\PrivacyOperationExecutionException;
use App\Modules\Core\Privacy\Application\Services\DataLifecycleParticipantRegistry;
use App\Modules\Core\Privacy\Application\Services\PrivacyOperationExecutor;
use App\Modules\Core\Privacy\Application\Services\PrivacyOperationPreviewer;
use App\Modules\Core\Privacy\Application\Services\PrivacyRetentionCoverageCatalog;
use App\Modules\Core\Privacy\Presentation\Http\PrivacyPreviewInput;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PrivacyRetentionController
{
    private const HIGH_RISK_RECOVERABLE_INPUT = 'atlas_high_risk_recoverable_input';

    public function __construct(
        private ArrayTableProcessor $tables,
        private TableRequestContext $context,
        private TableSavedViewService $views,
        private DataLifecycleParticipantRegistry $participants,
        private PrivacyRetentionCoverageCatalog $coverage,
        private PrivacyOperationPreviewer $previewer,
        private PrivacyOperationExecutor $executor,
        private HighRiskAdministrativeAuthorization $adminMode,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::PRIVACY_RETENTION_COVERAGE);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $participantCount = $this->participants->count();
        $rows = array_map(
            static fn ($item): array => $item->toArray(),
            $this->coverage->items($this->participants->classNames()),
        );
        $filters = $this->filters($request, $rows);
        $filteredRows = $this->filteredRows($rows, $filters);
        $result = $this->tables->process($filteredRows, $definition, $state)
            ->withSavedViews($this->views->listFor(AdminTableDefinitions::PRIVACY_RETENTION_COVERAGE, $userId, $teamId));
        $table = $result->tableMeta(AdminTableDefinitions::PRIVACY_RETENTION_COVERAGE);
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/PrivacyRetention/Index', [
            'coverage' => $result->rows,
            'summary' => [
                'areas' => count($rows),
                'visible' => $result->total,
                'implemented' => count(array_filter($rows, static fn (array $row): bool => ($row['coverage'] ?? null) === 'implemented')),
                'partial' => count(array_filter($rows, static fn (array $row): bool => ($row['coverage'] ?? null) === 'partial')),
                'blockedHardDelete' => count(array_filter($rows, static fn (array $row): bool => ($row['hardDeletePolicy'] ?? null) === 'blocked')),
                'participants' => $participantCount,
            ],
            'latestPreview' => $this->latestPreview($request),
            'previewFormDefaults' => $this->previewFormDefaults($request),
            'autoSubmitPreview' => $this->shouldAutoSubmitRecoveredPreview($request),
            'subjectTypeOptions' => $this->subjectTypeOptions(),
            'filterOptions' => [
                'owners' => $this->uniqueValues($rows, 'ownerModule'),
                'coverage' => $this->uniqueValues($rows, 'coverage'),
            ],
            'table' => $table,
        ]);
    }

    public function previewHardDelete(Request $request): RedirectResponse
    {
        return $this->preview($request, PrivacyOperation::HardDelete);
    }

    public function previewAnonymization(Request $request): RedirectResponse
    {
        return $this->preview($request, PrivacyOperation::Anonymization);
    }

    public function executeHardDelete(Request $request, string $operation): RedirectResponse
    {
        return $this->execute($request, $operation, PrivacyOperation::HardDelete);
    }

    public function executeAnonymization(Request $request, string $operation): RedirectResponse
    {
        return $this->execute($request, $operation, PrivacyOperation::Anonymization);
    }

    private function preview(Request $request, PrivacyOperation $operation): RedirectResponse
    {
        $validated = $request->validate(PrivacyPreviewInput::rules(), [], PrivacyPreviewInput::attributes());

        if ($request->hasSession()) {
            $request->session()->forget(self::HIGH_RISK_RECOVERABLE_INPUT);
        }

        [$userId, $teamId, $actorPublicId] = $this->context->userTeam($request);
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $result = $this->previewer->preview(new PrivacyPreviewCommand(
            operation: $operation,
            subjectType: $this->stringValue($this->arrayValue($validated, 'subject_type')),
            subjectIdentifier: $this->stringValue($this->arrayValue($validated, 'subject_identifier')),
            reason: $this->stringValue($this->arrayValue($validated, 'reason')),
            actorUserId: $userId,
            teamId: $teamId,
            actorPublicId: $actorPublicId,
            teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            dryRun: true,
        ));

        return redirect()
            ->route('admin.privacy-retention.index', ['preview' => $result->publicId])
            ->with('flash.messages', [
                $result->canExecute
                    ? FlashMessage::success('flash.privacy.preview_created')
                    : FlashMessage::warning('flash.privacy.preview_blocked'),
            ]);
    }

    private function execute(Request $request, string $operationRequestPublicId, PrivacyOperation $operation): RedirectResponse
    {
        $validated = $request->validate([
            'confirmation_phrase' => ['required', 'string', 'max:180'],
        ], [], [
            'confirmation_phrase' => __('validation.attributes.confirmation_phrase'),
        ]);

        [$userId, $teamId, $actorPublicId] = $this->context->userTeam($request);
        unset($teamId);

        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        try {
            $result = $this->executor->execute(
                operationRequestPublicId: $operationRequestPublicId,
                expectedOperation: $operation,
                confirmationPhrase: $this->stringValue($this->arrayValue($validated, 'confirmation_phrase')),
                actorUserId: $userId,
                actorPublicId: $actorPublicId,
                teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            );
        } catch (PrivacyOperationExecutionException $exception) {
            return redirect()
                ->route('admin.privacy-retention.operations.index')
                ->with('flash.messages', [
                    FlashMessage::warning('flash.privacy.'.$exception->errorKey),
                ]);
        }

        return redirect()
            ->route('admin.privacy-retention.operations.index', [
                'operation' => $operation->value,
                'status' => $result->status,
            ])
            ->with('flash.messages', [
                $result->completed
                    ? FlashMessage::success('flash.privacy.execution_completed')
                    : FlashMessage::warning('flash.privacy.execution_blocked'),
            ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{owner: string, coverage: string, retention: string, participant: string}
     */
    private function filters(Request $request, array $rows): array
    {
        return [
            'owner' => $this->oneOf($request->query('owner'), $this->allOr($this->uniqueValues($rows, 'ownerModule'))),
            'coverage' => $this->oneOf($request->query('coverage'), $this->allOr($this->uniqueValues($rows, 'coverage'))),
            'retention' => $this->oneOf($request->query('retention'), ['all', 'controlled', 'not_controlled']),
            'participant' => $this->oneOf($request->query('participant'), ['all', 'registered', 'missing']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{owner: string, coverage: string, retention: string, participant: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['owner'] !== 'all' && ($row['ownerModule'] ?? null) !== $filters['owner']) {
                return false;
            }

            if ($filters['coverage'] !== 'all' && ($row['coverage'] ?? null) !== $filters['coverage']) {
                return false;
            }

            if ($filters['retention'] === 'controlled' && ($row['retentionControlled'] ?? false) !== true) {
                return false;
            }

            if ($filters['retention'] === 'not_controlled' && ($row['retentionControlled'] ?? false) !== false) {
                return false;
            }

            if ($filters['participant'] === 'registered' && ($row['hasParticipant'] ?? false) !== true) {
                return false;
            }

            if ($filters['participant'] === 'missing' && ($row['hasParticipant'] ?? false) !== false) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestPreview(Request $request): ?array
    {
        $previewPublicId = $request->query('preview');

        if (! is_string($previewPublicId) || $previewPublicId === '') {
            return null;
        }

        $row = DB::table(DatabaseTable::PRIVACY_OPERATION_REQUESTS.' as requests')
            ->join(DatabaseTable::PRIVACY_OPERATION_PREVIEWS.' as previews', 'previews.operation_request_id', '=', 'requests.id')
            ->where('requests.public_id', $previewPublicId)
            ->orderByDesc('previews.created_at')
            ->first([
                'requests.public_id',
                'requests.operation',
                'requests.subject_type',
                'requests.subject_identifier',
                'requests.status',
                'requests.dry_run',
                'requests.reason',
                'requests.confirmation_phrase',
                'requests.previewed_at',
                'previews.impacts',
                'previews.blockers',
                'previews.participant_count',
                'previews.estimated_records',
                'previews.can_execute',
            ]);

        if (! is_object($row)) {
            return null;
        }

        return [
            'publicId' => $this->stringValue($row->public_id ?? ''),
            'operation' => $this->stringValue($row->operation ?? ''),
            'subjectType' => $this->stringValue($row->subject_type ?? ''),
            'subjectIdentifier' => $this->stringValue($row->subject_identifier ?? ''),
            'status' => $this->stringValue($row->status ?? ''),
            'dryRun' => (bool) ($row->dry_run ?? true),
            'reason' => $this->stringValue($row->reason ?? ''),
            'confirmationPhrase' => $this->stringValue($row->confirmation_phrase ?? ''),
            'previewedAt' => $this->nullableStringValue($row->previewed_at ?? null),
            'impacts' => $this->jsonList($row->impacts ?? '[]'),
            'blockers' => $this->jsonList($row->blockers ?? '[]'),
            'participantCount' => $this->intValue($row->participant_count ?? null),
            'estimatedRecords' => $this->intValue($row->estimated_records ?? null),
            'canExecute' => (bool) ($row->can_execute ?? false),
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : 'all';
    }

    private function stringValue(mixed $value): string
    {
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }

    private function nullableStringValue(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{operation: string, subject_type: string, subject_identifier: string, reason: string, dry_run: bool}
     */
    private function previewFormDefaults(Request $request): array
    {
        $recoverable = $request->hasSession() ? $request->session()->get(self::HIGH_RISK_RECOVERABLE_INPUT, []) : [];
        $recoverable = is_array($recoverable) ? $recoverable : [];
        $operation = $this->stringValue($request->old('operation', $this->stringDefault($recoverable, 'operation', PrivacyOperation::HardDelete->value)));

        if (! in_array($operation, [PrivacyOperation::HardDelete->value, PrivacyOperation::Anonymization->value], true)) {
            $operation = PrivacyOperation::HardDelete->value;
        }

        return [
            'operation' => $operation,
            'subject_type' => $this->stringValue($request->old('subject_type', $this->stringDefault($recoverable, 'subject_type', 'user'))),
            'subject_identifier' => $this->stringValue($request->old('subject_identifier', $this->stringDefault($recoverable, 'subject_identifier', ''))),
            'reason' => $this->stringValue($request->old('reason', $this->stringDefault($recoverable, 'reason', ''))),
            'dry_run' => filter_var($request->old('dry_run', $this->stringDefault($recoverable, 'dry_run', '1')), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
        ];
    }

    private function shouldAutoSubmitRecoveredPreview(Request $request): bool
    {
        return $request->hasSession()
            && $request->session()->has(self::HIGH_RISK_RECOVERABLE_INPUT)
            && $this->adminMode->highRiskFresh($request);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function subjectTypeOptions(): array
    {
        return [
            ['value' => 'user', 'label' => __('pages.admin.privacy_retention.subject_type.user')],
            ['value' => 'file', 'label' => __('pages.admin.privacy_retention.subject_type.file')],
            ['value' => 'file_object', 'label' => __('pages.admin.privacy_retention.subject_type.file_object')],
        ];
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonList(mixed $value): array
    {
        if (is_array($value)) {
            return $this->arrayList($value);
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        return $this->arrayList($decoded);
    }

    private function arrayValue(mixed $values, string $key): mixed
    {
        return is_array($values) ? ($values[$key] ?? null) : null;
    }

    /**
     * @param  array<mixed>  $values
     */
    private function stringDefault(array $values, string $key, string $default): string
    {
        $value = $values[$key] ?? null;

        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }

    /**
     * @param  array<mixed>  $values
     * @return list<array<string, mixed>>
     */
    private function arrayList(array $values): array
    {
        $rows = [];

        foreach ($values as $value) {
            if (! is_array($value)) {
                continue;
            }

            $row = [];

            foreach ($value as $key => $item) {
                if (is_string($key)) {
                    $row[$key] = $item;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function allOr(array $values): array
    {
        return ['all', ...$values];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function uniqueValues(array $rows, string $key): array
    {
        $values = [];

        foreach ($rows as $row) {
            $value = $row[$key] ?? null;

            if (is_string($value) && $value !== '' && ! in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        sort($values);

        return $values;
    }
}
