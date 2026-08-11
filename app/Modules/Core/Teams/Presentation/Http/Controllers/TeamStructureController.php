<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Presentation\Http\Controllers;

use App\Modules\Core\Teams\Application\Exceptions\ManagerHierarchyViolation;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerHierarchyNode;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerImpactPreview;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerRelationshipSummary;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipManager;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class TeamStructureController
{
    public function __construct(
        private readonly ManagerHierarchy $hierarchy,
        private readonly UserTeamMembershipManager $memberships,
    ) {}

    public function show(Request $request, string $team): Response
    {
        $teamPublicId = $team;
        $previewManager = $request->query('preview_manager');
        $selectedManagerPublicId = is_string($previewManager) && $previewManager !== '' ? $previewManager : '';
        $previewReportPublicIds = $this->previewReportPublicIds($request);
        $manager = $teamPublicId === '' || $selectedManagerPublicId === ''
            ? null
            : $this->managerCandidate($teamPublicId, $selectedManagerPublicId);

        return Inertia::render('Admin/Teams/Structure', [
            'structureVersion' => $this->hierarchy->version($teamPublicId),
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

    public function store(Request $request, string $team): RedirectResponse
    {
        $validated = $request->validate([
            'team_public_id' => ['required', 'string'],
            'manager_user_public_id' => ['required', 'string'],
            'report_user_public_id' => ['nullable', 'string', 'required_without:report_user_public_ids'],
            'report_user_public_ids' => ['nullable', 'array', 'required_without:report_user_public_id'],
            'report_user_public_ids.*' => ['string'],
            'valid_from' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'structure_version' => ['sometimes', 'string', 'size:64'],
        ]);
        $values = is_array($validated) ? $validated : [];
        $values['team_public_id'] = $team;
        $reportUserPublicIds = $this->reportUserPublicIds($values);

        if ($reportUserPublicIds === []) {
            throw ValidationException::withMessages(['report_user_public_ids' => __('validation.required', ['attribute' => __('pages.admin.teams.structure.forms.reports')])]);
        }

        try {
            /** @var string|null $expectedVersion */
            $expectedVersion = isset($values['structure_version']) ? $this->string($values['structure_version']) : null;

            foreach ($reportUserPublicIds as $reportUserPublicId) {
                $this->hierarchy->assign(
                    actorUserPublicId: $this->actorPublicId($request),
                    teamPublicId: $team,
                    managerUserPublicId: $this->string($values['manager_user_public_id'] ?? ''),
                    reportUserPublicId: $reportUserPublicId,
                    validFrom: $this->string($values['valid_from'] ?? ''),
                    reason: $this->string($values['reason'] ?? ''),
                    expectedVersion: $expectedVersion,
                );
                $expectedVersion = null;
            }
        } catch (ManagerHierarchyViolation $exception) {
            $errorKey = array_key_exists('report_user_public_ids', $values) ? 'report_user_public_ids' : 'manager_user_public_id';

            throw ValidationException::withMessages([$errorKey => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.teams.structure.show', [
                'team' => $team,
                'preview_manager' => $this->string($values['manager_user_public_id'] ?? ''),
            ])
            ->with('flash.messages', [
                FlashMessage::success('flash.teams.manager_relationship_created'),
            ]);
    }

    public function end(Request $request, string $team, string $relationship): RedirectResponse
    {
        $validated = $request->validate([
            'valid_to' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'team_public_id' => ['required', 'string'],
            'structure_version' => ['sometimes', 'string', 'size:64'],
        ]);
        $values = is_array($validated) ? $validated : [];
        $values['team_public_id'] = $team;
        $managerUserPublicId = $this->managerPublicIdForRelationship($team, $relationship);

        try {
            $this->hierarchy->end(
                actorUserPublicId: $this->actorPublicId($request),
                relationshipPublicId: $relationship,
                validTo: $this->string($values['valid_to'] ?? ''),
                reason: $this->string($values['reason'] ?? ''),
                expectedVersion: isset($values['structure_version']) ? $this->string($values['structure_version']) : null,
            );
        } catch (ManagerHierarchyViolation $exception) {
            throw ValidationException::withMessages(['relationship' => $exception->getMessage()]);
        }

        $redirect = redirect()->route('admin.teams.structure.show', [
            'team' => $team,
            'preview_manager' => $managerUserPublicId,
        ]);

        return $redirect
            ->with('flash.messages', [
                FlashMessage::success('flash.teams.manager_relationship_ended'),
            ]);
    }

    public function head(Request $request, string $team): RedirectResponse
    {
        $validated = $request->validate([
            'team_public_id' => ['required', 'string'],
            'user_public_id' => ['required', 'string'],
            'head_manager' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'structure_version' => ['sometimes', 'string', 'size:64'],
        ]);
        $values = is_array($validated) ? $validated : [];
        $values['team_public_id'] = $team;

        try {
            $this->hierarchy->setHeadManager(
                actorUserPublicId: $this->actorPublicId($request),
                teamPublicId: $team,
                userPublicId: $this->string($values['user_public_id'] ?? ''),
                headManager: (bool) ($values['head_manager'] ?? false),
                reason: $this->string($values['reason'] ?? ''),
                expectedVersion: isset($values['structure_version']) ? $this->string($values['structure_version']) : null,
            );
        } catch (ManagerHierarchyViolation $exception) {
            throw ValidationException::withMessages(['user_public_id' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.teams.structure.show', [
                'team' => $team,
                'preview_manager' => $this->string($values['user_public_id'] ?? ''),
            ])
            ->with('flash.messages', [
                FlashMessage::success('flash.teams.head_manager_updated'),
            ]);
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
     * @return list<array{value: string, label: string, name: string, email: string, headManager: bool, manager: bool}>
     */
    private function teamMembers(string $teamPublicId): array
    {
        $members = [];
        $managerUserPublicIds = [];

        foreach ($this->hierarchy->activeRelationships($teamPublicId) as $relationship) {
            $managerUserPublicIds[$relationship->managerUserPublicId] = true;
        }

        foreach ($this->memberships->activeMembershipsForTeam($teamPublicId) as $member) {
            $name = $member->userName;
            $email = $member->userEmail;
            $members[] = [
                'value' => $member->userPublicId,
                'label' => trim($name.' · '.$email),
                'name' => $name,
                'email' => $email,
                'headManager' => $member->headManager,
                'manager' => $member->headManager || isset($managerUserPublicIds[$member->userPublicId]),
            ];
        }

        usort($members, static fn (array $first, array $second): int => strcmp($first['name'], $second['name']));

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
}
