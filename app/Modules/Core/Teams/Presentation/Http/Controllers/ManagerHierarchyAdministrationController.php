<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Teams\Application\Exceptions\ManagerHierarchyViolation;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamMembershipManager;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerHierarchyNode;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerImpactPreview;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerRelationshipSummary;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
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
        private readonly UserCredentialAccountDirectory $accounts,
    ) {}

    public function index(Request $request): Response
    {
        $teamPublicId = $this->selectedTeamPublicId($request);
        $preview = null;
        $previewManager = $request->query('preview_manager');
        $previewReport = $request->query('preview_report');

        if ($teamPublicId !== '' && is_string($previewManager) && is_string($previewReport) && $previewManager !== '' && $previewReport !== '') {
            $preview = $this->preview($this->hierarchy->previewAssign($teamPublicId, $previewManager, $previewReport));
        }

        return Inertia::render('Admin/Managers/Index', [
            'selectedTeamPublicId' => $teamPublicId,
            'teamOptions' => array_map(static fn ($team): array => [
                'value' => $team->publicId,
                'label' => $team->name,
            ], $this->memberships->activeTeamOptions()),
            'userOptions' => $this->userOptions(),
            'teamMembers' => $teamPublicId === '' ? [] : $this->teamMembers($teamPublicId),
            'relationships' => array_map($this->relationship(...), $teamPublicId === '' ? [] : $this->hierarchy->activeRelationships($teamPublicId)),
            'history' => array_map($this->relationship(...), $teamPublicId === '' ? [] : $this->hierarchy->relationshipHistory($teamPublicId)),
            'tree' => array_map($this->node(...), $teamPublicId === '' ? [] : $this->hierarchy->tree($teamPublicId)),
            'preview' => $preview,
            'exports' => AdminDataTableExportMeta::defaults(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'team_public_id' => ['required', 'string'],
            'manager_user_public_id' => ['required', 'string'],
            'report_user_public_id' => ['required', 'string'],
            'valid_from' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $values = is_array($validated) ? $validated : [];

        try {
            $this->hierarchy->assign(
                actorUserPublicId: $this->actorPublicId($request),
                teamPublicId: $this->string($values['team_public_id'] ?? ''),
                managerUserPublicId: $this->string($values['manager_user_public_id'] ?? ''),
                reportUserPublicId: $this->string($values['report_user_public_id'] ?? ''),
                validFrom: $this->string($values['valid_from'] ?? ''),
                reason: $this->string($values['reason'] ?? ''),
            );
        } catch (ManagerHierarchyViolation $exception) {
            throw ValidationException::withMessages(['manager_user_public_id' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.managers.index', ['team' => $this->string($values['team_public_id'] ?? '')])
            ->with('success', 'Manager relationship was created.');
    }

    public function end(Request $request, string $relationship): RedirectResponse
    {
        $validated = $request->validate([
            'valid_to' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'team_public_id' => ['required', 'string'],
        ]);
        $values = is_array($validated) ? $validated : [];

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

        return redirect()
            ->route('admin.managers.index', ['team' => $this->string($values['team_public_id'] ?? '')])
            ->with('success', 'Manager relationship was ended.');
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
            ->route('admin.managers.index', ['team' => $this->string($values['team_public_id'] ?? '')])
            ->with('success', 'Head manager status was updated.');
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
     * @return list<array{value: string, label: string}>
     */
    private function userOptions(): array
    {
        $users = [];

        foreach ($this->accounts->allOptions() as $user) {
            $users[] = [
                'value' => $user->publicId,
                'label' => trim($user->name.' · '.$user->email),
            ];
        }

        return $users;
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
}
