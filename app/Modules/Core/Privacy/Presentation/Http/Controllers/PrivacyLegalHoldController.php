<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Privacy\Application\Public\Persistence\PrivacyDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PrivacyLegalHoldController
{
    public function __construct(
        private ArrayTableProcessor $tables,
        private TableRequestContext $context,
        private TableSavedViewService $views,
        private AuditRecorder $audit,
    ) {}

    public function index(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::PRIVACY_LEGAL_HOLDS);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $rows = $this->rows();
        $filters = $this->filters($request, $rows);
        $filteredRows = $this->filteredRows($rows, $filters);
        $result = $this->tables->process($filteredRows, $definition, $state)
            ->withSavedViews($this->views->listFor(AdminTableDefinitions::PRIVACY_LEGAL_HOLDS, $userId, $teamId));
        $table = $result->tableMeta(AdminTableDefinitions::PRIVACY_LEGAL_HOLDS);
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/PrivacyRetention/LegalHolds', [
            'holds' => $result->rows,
            'summary' => [
                'total' => count($rows),
                'visible' => $result->total,
                'active' => count(array_filter($rows, static fn (array $row): bool => ($row['status'] ?? null) === 'active')),
                'expired' => count(array_filter($rows, static fn (array $row): bool => ($row['status'] ?? null) === 'expired')),
                'released' => count(array_filter($rows, static fn (array $row): bool => ($row['status'] ?? null) === 'released')),
                'withExpiry' => count(array_filter($rows, static fn (array $row): bool => ($row['expiresOn'] ?? '') !== '')),
            ],
            'filterOptions' => [
                'subjectTypes' => $this->uniqueValues($rows, 'subjectType'),
                'teams' => $this->uniqueValues($rows, 'teamPublicId'),
            ],
            'table' => $table,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/PrivacyRetention/LegalHoldCreate', [
            'formDefaults' => [
                'subject_type' => $this->stringValue($request->old('subject_type', 'user')),
                'subject_identifier' => $this->stringValue($request->old('subject_identifier', '')),
                'reason' => $this->stringValue($request->old('reason', '')),
                'expires_on' => $this->stringValue($request->old('expires_on', '')),
            ],
            'subjectTypeOptions' => $this->subjectTypeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject_type' => ['required', 'string', Rule::in($this->subjectTypeValues())],
            'subject_identifier' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:12', 'max:2000'],
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after:today'],
        ], [], [
            'subject_type' => __('validation.attributes.privacy_subject_type'),
            'subject_identifier' => __('validation.attributes.privacy_subject_identifier'),
            'reason' => __('validation.attributes.reason'),
            'expires_on' => __('validation.attributes.privacy_legal_hold_expires_on'),
        ]);

        [$userId, $teamId, $actorPublicId] = $this->context->userTeam($request);
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $publicId = (string) Str::ulid();
        $subjectType = $this->stringValue($this->arrayValue($validated, 'subject_type'));
        $subjectIdentifier = $this->stringValue($this->arrayValue($validated, 'subject_identifier'));
        $reason = $this->stringValue($this->arrayValue($validated, 'reason'));
        $expiresOn = $this->nullableStringValue($this->arrayValue($validated, 'expires_on'));

        DB::transaction(function () use ($publicId, $subjectType, $subjectIdentifier, $reason, $expiresOn, $userId, $teamId, $actorPublicId, $teamPublicId): void {
            DB::table(PrivacyDatabaseTable::LEGAL_HOLDS)->insert([
                'public_id' => $publicId,
                'subject_type' => $subjectType,
                'subject_identifier' => $subjectIdentifier,
                'created_by_user_id' => $userId,
                'team_id' => $teamId,
                'reason' => $reason,
                'expires_on' => $expiresOn,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit->record(new AuditEvent(
                module: 'privacy',
                action: 'privacy.legal_hold_created',
                result: 'succeeded',
                source: 'ui',
                actorPublicId: $actorPublicId,
                targetType: $subjectType,
                targetPublicId: Str::isUlid($subjectIdentifier) ? $subjectIdentifier : null,
                aggregateType: 'privacy_legal_hold',
                aggregatePublicId: $publicId,
                teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
                reason: $reason,
                metadata: [
                    'subject_type' => $subjectType,
                    'subject_identifier' => $subjectIdentifier,
                    'expires_on' => $expiresOn,
                ],
                security: true,
                securityCategory: SecurityAuditCategory::Privacy,
            ));
        });

        return redirect()
            ->route('admin.privacy-retention.legal-holds.index')
            ->with('flash.messages', [FlashMessage::success('flash.privacy.legal_hold_created')]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        return array_values(DB::table(PrivacyDatabaseTable::LEGAL_HOLDS.' as holds')
            ->leftJoin(IdentityDatabaseTable::USERS.' as creators', 'creators.id', '=', 'holds.created_by_user_id')
            ->leftJoin(TeamsDatabaseTable::TEAMS.' as teams', 'teams.id', '=', 'holds.team_id')
            ->orderByDesc('holds.created_at')
            ->get([
                'holds.public_id',
                'holds.subject_type',
                'holds.subject_identifier',
                'holds.reason',
                'holds.expires_on',
                'holds.released_at',
                'holds.release_reason',
                'holds.created_at',
                'creators.public_id as created_by_public_id',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
            ])
            ->map(fn (object $row): array => $this->row($row))
            ->all());
    }

    private function arrayValue(mixed $values, string $key): mixed
    {
        return is_array($values) ? ($values[$key] ?? null) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(object $row): array
    {
        $releasedAt = $this->nullableStringValue($row->released_at ?? null);
        $expiresOn = $this->nullableStringValue($row->expires_on ?? null);

        return [
            'publicId' => $this->stringValue($row->public_id ?? ''),
            'subjectType' => $this->stringValue($row->subject_type ?? ''),
            'subjectIdentifier' => $this->stringValue($row->subject_identifier ?? ''),
            'status' => $this->holdStatus($releasedAt, $expiresOn),
            'teamPublicId' => $this->stringValue($row->team_public_id ?? ''),
            'teamName' => $this->stringValue($row->team_name ?? ''),
            'createdByPublicId' => $this->stringValue($row->created_by_public_id ?? ''),
            'reason' => $this->stringValue($row->reason ?? ''),
            'expiresOn' => $expiresOn ?? '',
            'releasedAt' => $releasedAt ?? '',
            'releaseReason' => $this->stringValue($row->release_reason ?? ''),
            'createdAt' => $this->stringValue($row->created_at ?? ''),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{status: string, subject_type: string, team: string}
     */
    private function filters(Request $request, array $rows): array
    {
        return [
            'status' => $this->oneOf($request->query('status'), ['all', 'active', 'expired', 'released']),
            'subject_type' => $this->oneOf($request->query('subject_type'), $this->allOr($this->uniqueValues($rows, 'subjectType'))),
            'team' => $this->oneOf($request->query('team'), $this->allOr($this->uniqueValues($rows, 'teamPublicId'))),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{status: string, subject_type: string, team: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['status'] !== 'all' && ($row['status'] ?? null) !== $filters['status']) {
                return false;
            }

            if ($filters['subject_type'] !== 'all' && ($row['subjectType'] ?? null) !== $filters['subject_type']) {
                return false;
            }

            if ($filters['team'] !== 'all' && ($row['teamPublicId'] ?? null) !== $filters['team']) {
                return false;
            }

            return true;
        }));
    }

    private function holdStatus(?string $releasedAt, ?string $expiresOn): string
    {
        if ($releasedAt !== null) {
            return 'released';
        }

        if ($expiresOn !== null && $expiresOn < now('UTC')->toDateString()) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : 'all';
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

    /**
     * @return list<string>
     */
    private function subjectTypeValues(): array
    {
        return array_map(
            static fn (array $option): string => $option['value'],
            $this->subjectTypeOptions(),
        );
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
}
