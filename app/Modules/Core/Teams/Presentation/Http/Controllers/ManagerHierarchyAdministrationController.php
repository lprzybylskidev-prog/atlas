<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Presentation\Http\Controllers;

use App\Modules\Core\Teams\Application\Exceptions\ManagerHierarchyViolation;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamMembershipManager;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerHierarchyNode;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerImpactPreview;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerRelationshipSummary;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ManagerHierarchyAdministrationController
{
    public function __construct(
        private readonly ManagerHierarchy $hierarchy,
        private readonly UserTeamMembershipManager $memberships,
        private readonly ArrayTableProcessor $tables,
        private readonly TableSavedViewService $views,
        private readonly TableRequestContext $context,
    ) {}

    public function index(Request $request): Response
    {
        $teamPublicId = $this->selectedTeamPublicId($request);
        $filters = $this->managerFilters($request, $teamPublicId);
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::MANAGERS);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $activeTeamId] = $this->context->userTeam($request);
        $result = $this->tables->process($teamPublicId === '' ? [] : $this->filteredManagerRows($this->managerRows($teamPublicId), $filters), $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $activeTeamId));
        $table = $result->tableMeta($definition->key);
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/Managers/Index', [
            'selectedTeamPublicId' => $teamPublicId,
            'teamOptions' => array_map(static fn ($team): array => [
                'value' => $team->publicId,
                'label' => $team->name,
            ], $this->memberships->activeTeamOptions()),
            'managers' => $result->rows,
            'table' => $table,
        ]);
    }

    /**
     * @return array{team: string, type: string, directReports: string, subtreeReports: string}
     */
    private function managerFilters(Request $request, string $teamPublicId): array
    {
        $type = $request->query('type');
        $directReports = $request->query('directReports');
        $subtreeReports = $request->query('subtreeReports');

        return [
            'team' => $teamPublicId,
            'type' => in_array($type, ['head', 'regular'], true) ? $type : 'all',
            'directReports' => in_array($directReports, ['with', 'without'], true) ? $directReports : 'all',
            'subtreeReports' => in_array($subtreeReports, ['with', 'without'], true) ? $subtreeReports : 'all',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{team: string, type: string, directReports: string, subtreeReports: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredManagerRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['type'] !== 'all' && ($row['managerType'] ?? null) !== $filters['type']) {
                return false;
            }

            if ($filters['directReports'] === 'with' && self::intValue($row['directReportsCount'] ?? 0) <= 0) {
                return false;
            }

            if ($filters['directReports'] === 'without' && self::intValue($row['directReportsCount'] ?? 0) > 0) {
                return false;
            }

            if ($filters['subtreeReports'] === 'with' && self::intValue($row['subtreeReportsCount'] ?? 0) <= 0) {
                return false;
            }

            if ($filters['subtreeReports'] === 'without' && self::intValue($row['subtreeReportsCount'] ?? 0) > 0) {
                return false;
            }

            return true;
        }));
    }

    public function create(Request $request): Response
    {
        $teamPublicId = $this->selectedTeamPublicId($request);
        $previewManager = $request->query('preview_manager');
        $selectedManagerPublicId = is_string($previewManager) && $previewManager !== '' ? $previewManager : '';
        $previewReportPublicIds = $this->previewReportPublicIds($request);
        $manager = $teamPublicId === '' || $selectedManagerPublicId === ''
            ? null
            : $this->managerCandidate($teamPublicId, $selectedManagerPublicId);

        return Inertia::render('Admin/Managers/Create', [
            'selectedTeamPublicId' => $teamPublicId,
            'selectedManagerPublicId' => $selectedManagerPublicId,
            'teamOptions' => array_map(static fn ($team): array => [
                'value' => $team->publicId,
                'label' => $team->name,
            ], $this->memberships->activeTeamOptions()),
            'teamMembers' => $teamPublicId === '' ? [] : $this->teamMembers($teamPublicId),
            'manager' => $manager,
            'relationships' => $teamPublicId === '' || $selectedManagerPublicId === '' ? [] : array_values(array_filter(
                array_map($this->relationship(...), $this->hierarchy->activeRelationships($teamPublicId)),
                static fn (array $relationship): bool => ($relationship['managerUserPublicId'] ?? '') === $selectedManagerPublicId,
            )),
            'tree' => $teamPublicId === '' || $selectedManagerPublicId === '' ? [] : $this->managerTree($teamPublicId, $selectedManagerPublicId),
            'previewReportPublicIds' => $previewReportPublicIds,
            'assignmentPreviews' => $teamPublicId === '' || $selectedManagerPublicId === ''
                ? []
                : $this->assignmentPreviews($teamPublicId, $selectedManagerPublicId, $previewReportPublicIds),
        ]);
    }

    public function edit(Request $request, string $user): Response
    {
        $teamPublicId = $this->selectedTeamPublicId($request);
        $previewReportPublicIds = $this->previewReportPublicIds($request);

        $manager = $this->managerRow($teamPublicId, $user);

        if ($teamPublicId === '' || $manager === null) {
            abort(404);
        }

        return Inertia::render('Admin/Managers/Edit', [
            'selectedTeamPublicId' => $teamPublicId,
            'teamOptions' => array_map(static fn ($team): array => [
                'value' => $team->publicId,
                'label' => $team->name,
            ], $this->memberships->activeTeamOptions()),
            'manager' => $manager,
            'teamMembers' => $this->teamMembers($teamPublicId),
            'relationships' => array_values(array_filter(
                array_map($this->relationship(...), $this->hierarchy->activeRelationships($teamPublicId)),
                static fn (array $relationship): bool => ($relationship['managerUserPublicId'] ?? '') === $user,
            )),
            'tree' => $this->managerTree($teamPublicId, $user),
            'previewReportPublicIds' => $previewReportPublicIds,
            'assignmentPreviews' => $this->assignmentPreviews($teamPublicId, $user, $previewReportPublicIds),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'team_public_id' => ['required', 'string'],
            'manager_user_public_id' => ['required', 'string'],
            'report_user_public_id' => ['nullable', 'string', 'required_without:report_user_public_ids'],
            'report_user_public_ids' => ['nullable', 'array', 'required_without:report_user_public_id'],
            'report_user_public_ids.*' => ['string'],
            'valid_from' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $values = is_array($validated) ? $validated : [];
        $reportUserPublicIds = $this->reportUserPublicIds($values);

        if ($reportUserPublicIds === []) {
            throw ValidationException::withMessages(['report_user_public_ids' => __('validation.required', ['attribute' => __('pages.admin.managers.forms.reports')])]);
        }

        try {
            foreach ($reportUserPublicIds as $reportUserPublicId) {
                $this->hierarchy->assign(
                    actorUserPublicId: $this->actorPublicId($request),
                    teamPublicId: $this->string($values['team_public_id'] ?? ''),
                    managerUserPublicId: $this->string($values['manager_user_public_id'] ?? ''),
                    reportUserPublicId: $reportUserPublicId,
                    validFrom: $this->string($values['valid_from'] ?? ''),
                    reason: $this->string($values['reason'] ?? ''),
                );
            }
        } catch (ManagerHierarchyViolation $exception) {
            $errorKey = array_key_exists('report_user_public_ids', $values) ? 'report_user_public_ids' : 'manager_user_public_id';

            throw ValidationException::withMessages([$errorKey => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.managers.edit', [
                'user' => $this->string($values['manager_user_public_id'] ?? ''),
                'team' => $this->string($values['team_public_id'] ?? ''),
            ])
            ->with('flash.messages', [
                FlashMessage::success('flash.teams.manager_relationship_created'),
            ]);
    }

    public function end(Request $request, string $relationship): RedirectResponse
    {
        $validated = $request->validate([
            'valid_to' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'team_public_id' => ['required', 'string'],
        ]);
        $values = is_array($validated) ? $validated : [];
        $managerUserPublicId = $this->managerPublicIdForRelationship($this->string($values['team_public_id'] ?? ''), $relationship);

        try {
            $this->hierarchy->end(
                actorUserPublicId: $this->actorPublicId($request),
                relationshipPublicId: $relationship,
                validTo: $this->string($values['valid_to'] ?? ''),
                reason: $this->string($values['reason'] ?? ''),
            );
        } catch (ManagerHierarchyViolation $exception) {
            throw ValidationException::withMessages(['relationship' => $exception->getMessage()]);
        }

        $redirect = $managerUserPublicId === ''
            ? redirect()->route('admin.managers.index', ['team' => $this->string($values['team_public_id'] ?? '')])
            : redirect()->route('admin.managers.edit', [
                'user' => $managerUserPublicId,
                'team' => $this->string($values['team_public_id'] ?? ''),
            ]);

        return $redirect
            ->with('flash.messages', [
                FlashMessage::success('flash.teams.manager_relationship_ended'),
            ]);
    }

    public function head(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'team_public_id' => ['required', 'string'],
            'user_public_id' => ['required', 'string'],
            'head_manager' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $values = is_array($validated) ? $validated : [];

        try {
            $this->hierarchy->setHeadManager(
                actorUserPublicId: $this->actorPublicId($request),
                teamPublicId: $this->string($values['team_public_id'] ?? ''),
                userPublicId: $this->string($values['user_public_id'] ?? ''),
                headManager: (bool) ($values['head_manager'] ?? false),
                reason: $this->string($values['reason'] ?? ''),
            );
        } catch (ManagerHierarchyViolation $exception) {
            throw ValidationException::withMessages(['user_public_id' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.managers.edit', [
                'user' => $this->string($values['user_public_id'] ?? ''),
                'team' => $this->string($values['team_public_id'] ?? ''),
            ])
            ->with('flash.messages', [
                FlashMessage::success('flash.teams.head_manager_updated'),
            ]);
    }

    private function selectedTeamPublicId(Request $request): string
    {
        $team = $request->query('team');

        if (is_string($team) && $team !== '') {
            return $team;
        }

        $active = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        return is_string($active) ? $active : '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function managerRows(string $teamPublicId): array
    {
        $members = $this->teamMembers($teamPublicId);
        $relationships = array_map($this->relationship(...), $this->hierarchy->activeRelationships($teamPublicId));
        $tree = array_map($this->node(...), $this->hierarchy->tree($teamPublicId));
        $directCounts = [];

        foreach ($relationships as $relationship) {
            $managerUserPublicId = $this->string($relationship['managerUserPublicId'] ?? '');
            $directCounts[$managerUserPublicId] = ($directCounts[$managerUserPublicId] ?? 0) + 1;
        }

        $rows = [];

        foreach ($members as $member) {
            $userPublicId = $member['value'];
            $directReportsCount = $directCounts[$userPublicId] ?? 0;

            if ($directReportsCount === 0 && $member['headManager'] !== true) {
                continue;
            }

            $subtreeReportsCount = $this->subtreeReportsCount($tree, $userPublicId);
            $rows[] = [
                'userPublicId' => $userPublicId,
                'teamPublicId' => $teamPublicId,
                'teamName' => $this->teamName($teamPublicId),
                'name' => $member['name'],
                'email' => $member['email'],
                'managerType' => $member['headManager'] === true ? 'head' : 'regular',
                'directReportsCount' => $directReportsCount,
                'subtreeReportsCount' => $subtreeReportsCount,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function managerRow(string $teamPublicId, string $userPublicId): ?array
    {
        foreach ($this->managerRows($teamPublicId) as $row) {
            if (($row['userPublicId'] ?? '') === $userPublicId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function managerCandidate(string $teamPublicId, string $userPublicId): ?array
    {
        $row = $this->managerRow($teamPublicId, $userPublicId);

        if ($row !== null) {
            return $row;
        }

        foreach ($this->teamMembers($teamPublicId) as $member) {
            if ($member['value'] !== $userPublicId) {
                continue;
            }

            return [
                'userPublicId' => $userPublicId,
                'teamPublicId' => $teamPublicId,
                'teamName' => $this->teamName($teamPublicId),
                'name' => $member['name'],
                'email' => $member['email'],
                'managerType' => $member['headManager'] === true ? 'head' : 'regular',
                'directReportsCount' => 0,
                'subtreeReportsCount' => 0,
            ];
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function managerTree(string $teamPublicId, string $userPublicId): array
    {
        $tree = array_map($this->node(...), $this->hierarchy->tree($teamPublicId));
        $node = $this->findNode($tree, $userPublicId);

        return $node === null ? [] : [$node];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>|null
     */
    private function findNode(array $nodes, string $userPublicId): ?array
    {
        foreach ($nodes as $node) {
            if (($node['userPublicId'] ?? '') === $userPublicId) {
                return $node;
            }

            $reports = self::childNodes($node['reports'] ?? []);

            if ($reports !== []) {
                $found = $this->findNode($reports, $userPublicId);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function subtreeReportsCount(array $nodes, string $userPublicId): int
    {
        $node = $this->findNode($nodes, $userPublicId);

        if ($node === null) {
            return 0;
        }

        return $this->nodeReportCount(self::childNodes($node['reports'] ?? []));
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function nodeReportCount(array $nodes): int
    {
        $count = count($nodes);

        foreach ($nodes as $node) {
            $count += $this->nodeReportCount(self::childNodes($node['reports'] ?? []));
        }

        return $count;
    }

    private function managerPublicIdForRelationship(string $teamPublicId, string $relationshipPublicId): string
    {
        foreach (array_map($this->relationship(...), $this->hierarchy->activeRelationships($teamPublicId)) as $relationship) {
            if (($relationship['publicId'] ?? '') === $relationshipPublicId) {
                return $this->string($relationship['managerUserPublicId'] ?? '');
            }
        }

        return '';
    }

    private function teamName(string $teamPublicId): string
    {
        foreach ($this->memberships->activeTeamOptions() as $team) {
            if ($team->publicId === $teamPublicId) {
                return $team->name;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function previewReportPublicIds(Request $request): array
    {
        $reports = $request->query('preview_reports');

        if (is_array($reports)) {
            return array_values(array_unique(array_filter(
                array_map($this->string(...), $reports),
                static fn (string $value): bool => $value !== '',
            )));
        }

        $report = $request->query('preview_report');

        return is_string($report) && $report !== '' ? [$report] : [];
    }

    /**
     * @param  list<string>  $reportUserPublicIds
     * @return list<array<string, mixed>>
     */
    private function assignmentPreviews(string $teamPublicId, string $managerUserPublicId, array $reportUserPublicIds): array
    {
        $members = [];

        foreach ($this->teamMembers($teamPublicId) as $member) {
            $members[$member['value']] = $member;
        }

        $previews = [];

        foreach ($reportUserPublicIds as $reportUserPublicId) {
            $member = $members[$reportUserPublicId] ?? null;
            $preview = $this->hierarchy->previewAssign($teamPublicId, $managerUserPublicId, $reportUserPublicId);

            $previews[] = [
                'reportUserPublicId' => $reportUserPublicId,
                'reportName' => is_array($member) ? $this->string($member['name']) : $reportUserPublicId,
                'reportEmail' => is_array($member) ? $this->string($member['email']) : '',
                'allowed' => $preview->allowed,
                'affectedReportPublicIds' => $preview->affectedReportPublicIds,
                'warnings' => $preview->warnings,
            ];
        }

        return $previews;
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private function reportUserPublicIds(array $values): array
    {
        $many = $values['report_user_public_ids'] ?? null;

        if (is_array($many)) {
            return array_values(array_unique(array_filter(
                array_map($this->string(...), $many),
                static fn (string $value): bool => $value !== '',
            )));
        }

        $single = $this->string($values['report_user_public_id'] ?? '');

        return $single === '' ? [] : [$single];
    }

    /**
     * @return list<array{value: string, label: string, name: string, email: string, headManager: bool}>
     */
    private function teamMembers(string $teamPublicId): array
    {
        $members = [];

        foreach (DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS.' as assignments')
            ->join(DatabaseTable::TEAMS.' as teams', 'assignments.team_id', '=', 'teams.id')
            ->join(DatabaseTable::USERS.' as users', 'assignments.user_id', '=', 'users.id')
            ->where('teams.public_id', $teamPublicId)
            ->where(static function (Builder $query): void {
                $query->whereNull('assignments.valid_from')->orWhere('assignments.valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('assignments.valid_to')->orWhere('assignments.valid_to', '>', now());
            })
            ->orderBy('users.name')
            ->get(['users.public_id', 'users.name', 'users.email', 'assignments.is_head_manager']) as $row) {
            $values = get_object_vars($row);
            $name = $this->string($values['name'] ?? '');
            $email = $this->string($values['email'] ?? '');
            $members[] = [
                'value' => $this->string($values['public_id'] ?? ''),
                'label' => trim($name.' · '.$email),
                'name' => $name,
                'email' => $email,
                'headManager' => (bool) ($values['is_head_manager'] ?? false),
            ];
        }

        return $members;
    }

    /**
     * @return array<string, mixed>
     */
    private function relationship(ManagerRelationshipSummary $relationship): array
    {
        return [
            'publicId' => $relationship->publicId,
            'teamPublicId' => $relationship->teamPublicId,
            'teamName' => $relationship->teamName,
            'managerUserPublicId' => $relationship->managerUserPublicId,
            'managerName' => $relationship->managerName,
            'managerEmail' => $relationship->managerEmail,
            'reportUserPublicId' => $relationship->reportUserPublicId,
            'reportName' => $relationship->reportName,
            'reportEmail' => $relationship->reportEmail,
            'validFrom' => $relationship->validFrom,
            'validTo' => $relationship->validTo,
            'reason' => $relationship->reason,
            'endReason' => $relationship->endReason,
            'endPreview' => $this->preview($this->hierarchy->previewEnd($relationship->publicId)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function node(ManagerHierarchyNode $node): array
    {
        return [
            'userPublicId' => $node->userPublicId,
            'name' => $node->name,
            'email' => $node->email,
            'headManager' => $node->headManager,
            'reports' => array_map($this->node(...), $node->reports),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function preview(ManagerImpactPreview $preview): array
    {
        return [
            'allowed' => $preview->allowed,
            'action' => $preview->action,
            'affectedReportPublicIds' => $preview->affectedReportPublicIds,
            'warnings' => $preview->warnings,
        ];
    }

    private function actorPublicId(Request $request): string
    {
        $publicId = data_get($request->user(), 'public_id');

        return is_string($publicId) ? $publicId : '';
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function childNodes(mixed $reports): array
    {
        if (! is_array($reports)) {
            return [];
        }

        $nodes = [];

        foreach ($reports as $report) {
            if (! is_array($report)) {
                continue;
            }

            $node = [];

            foreach ($report as $key => $value) {
                if (is_string($key)) {
                    $node[$key] = $value;
                }
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
