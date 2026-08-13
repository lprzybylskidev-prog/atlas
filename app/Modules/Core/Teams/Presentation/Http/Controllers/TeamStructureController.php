<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Presentation\Http\Controllers;

use App\Modules\Core\Teams\Application\Exceptions\ManagerHierarchyViolation;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerImpactPreview;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerRelationshipSummary;
use App\Modules\Core\Teams\Application\Public\DTOs\StructuralRoleChangePreview;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipManager;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $rolePreviewUser = $this->string($request->query('role_preview_user'));
        $rolePreviewTarget = $this->string($request->query('role_preview_target'));

        return Inertia::render('Admin/Teams/Structure', [
            'structureVersion' => $this->hierarchy->version($teamPublicId),
            'selectedTeamPublicId' => $teamPublicId,
            'teamOptions' => array_map(static fn ($team): array => [
                'value' => $team->publicId,
                'label' => $team->name,
            ], $this->memberships->activeTeamOptions()),
            'teamMembers' => $teamPublicId === '' ? [] : $this->teamMembers($teamPublicId),
            'activeRelationships' => $teamPublicId === '' ? [] : array_map(
                $this->relationship(...),
                $this->hierarchy->activeRelationships($teamPublicId),
            ),
            'structuralRolePreview' => $teamPublicId === '' || $rolePreviewUser === '' || $rolePreviewTarget === ''
                ? null
                : $this->structuralRolePreview($this->hierarchy->previewStructuralRoleChange(
                    $teamPublicId,
                    $rolePreviewUser,
                    $rolePreviewTarget,
                )),
            'membershipHistory' => $teamPublicId === '' ? [] : array_map(static fn ($membership): array => [
                'userPublicId' => $membership->userPublicId,
                'userName' => $membership->userName,
                'userEmail' => $membership->userEmail,
                'validFrom' => $membership->validFrom,
                'validTo' => $membership->validTo,
                'headManager' => $membership->structuralRole === 'head_manager',
                'structuralRole' => $membership->structuralRole,
                'active' => $membership->active,
            ], $this->memberships->membershipHistoryForTeam($teamPublicId)),
            'assignableUsers' => $teamPublicId === '' ? [] : $this->memberships->assignableUsersForTeam($teamPublicId),
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

            DB::transaction(function () use ($request, $team, $values, $reportUserPublicIds, $expectedVersion): void {
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
            });
        } catch (ManagerHierarchyViolation $exception) {
            $errorKey = array_key_exists('report_user_public_ids', $values) ? 'report_user_public_ids' : 'manager_user_public_id';

            throw ValidationException::withMessages([$errorKey => $this->hierarchyError($exception)]);
        }

        return redirect()
            ->route('admin.teams.structure.show', [
                'team' => $team,
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
        try {
            $this->hierarchy->end(
                actorUserPublicId: $this->actorPublicId($request),
                relationshipPublicId: $relationship,
                validTo: $this->string($values['valid_to'] ?? ''),
                reason: $this->string($values['reason'] ?? ''),
                expectedVersion: isset($values['structure_version']) ? $this->string($values['structure_version']) : null,
            );
        } catch (ManagerHierarchyViolation $exception) {
            throw ValidationException::withMessages(['operation' => $this->hierarchyError($exception)]);
        }

        $redirect = redirect()->route('admin.teams.structure.show', [
            'team' => $team,
        ]);

        return $redirect
            ->with('flash.messages', [
                FlashMessage::success('flash.teams.manager_relationship_ended'),
            ]);
    }

    public function changeStructuralRole(Request $request, string $team): RedirectResponse
    {
        $validated = $request->validate([
            'team_public_id' => ['required', 'string'],
            'user_public_id' => ['required', 'string'],
            'structural_role' => ['required', 'string', 'in:employee,manager,head_manager'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'structure_version' => ['sometimes', 'string', 'size:64'],
        ]);
        $values = is_array($validated) ? $validated : [];
        $values['team_public_id'] = $team;
        $targetRole = $this->string($values['structural_role'] ?? '');

        try {
            $this->hierarchy->changeStructuralRole(
                actorUserPublicId: $this->actorPublicId($request),
                teamPublicId: $team,
                userPublicId: $this->string($values['user_public_id'] ?? ''),
                targetRole: $targetRole,
                reason: $this->string($values['reason'] ?? ''),
                expectedVersion: isset($values['structure_version']) ? $this->string($values['structure_version']) : null,
            );
        } catch (ManagerHierarchyViolation $exception) {
            throw ValidationException::withMessages(['user_public_id' => $this->hierarchyError($exception)]);
        }

        return redirect()
            ->route('admin.teams.structure.show', [
                'team' => $team,
            ])
            ->with('flash.messages', [
                FlashMessage::success('flash.teams.structural_role_updated'),
            ]);
    }

    public function addMember(Request $request, string $team): RedirectResponse
    {
        $validated = $request->validate(['user_public_id' => ['required', 'string']]);
        $userPublicId = $this->string(is_array($validated) ? ($validated['user_public_id'] ?? '') : '');
        $this->memberships->addAccess($this->actorPublicId($request), $userPublicId, $team);

        return redirect()->route('admin.teams.structure.show', ['team' => $team])->with('flash.messages', [
            FlashMessage::success('flash.teams.access_added'),
        ]);
    }

    public function removeMember(Request $request, string $team, string $user): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $reason = $this->string(is_array($validated) ? ($validated['reason'] ?? '') : '');

        try {
            $this->memberships->removeAccess($this->actorPublicId($request), $user, $team, $reason);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $operationError = $this->firstValidationError($errors['operation'] ?? null)
                ?? $this->firstValidationError($errors['reason'] ?? null)
                ?? $exception->getMessage();

            throw ValidationException::withMessages(['operation' => $operationError]);
        }

        return redirect()->route('admin.teams.structure.show', ['team' => $team])->with('flash.messages', [
            FlashMessage::success('flash.teams.access_removed'),
        ]);
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

    private function hierarchyError(ManagerHierarchyViolation $exception): string
    {
        return __('validation.custom.manager_hierarchy.'.$exception->errorKey);
    }

    /**
     * @return list<array{value: string, label: string, name: string, email: string, headManager: bool, structuralRole: string, manager: bool}>
     */
    private function teamMembers(string $teamPublicId): array
    {
        $members = [];
        foreach ($this->memberships->activeMembershipsForTeam($teamPublicId) as $member) {
            $name = $member->userName;
            $email = $member->userEmail;
            $members[] = [
                'value' => $member->userPublicId,
                'label' => trim($name.' · '.$email),
                'name' => $name,
                'email' => $email,
                'headManager' => $member->structuralRole === 'head_manager',
                'structuralRole' => $member->structuralRole,
                'manager' => $member->structuralRole === 'manager',
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
    private function preview(ManagerImpactPreview $preview): array
    {
        return [
            'allowed' => $preview->allowed,
            'action' => $preview->action,
            'affectedReportPublicIds' => $preview->affectedReportPublicIds,
            'warnings' => $preview->warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function structuralRolePreview(StructuralRoleChangePreview $preview): array
    {
        return [
            'allowed' => $preview->allowed,
            'currentRole' => $preview->currentRole,
            'targetRole' => $preview->targetRole,
            'endingRelationshipPublicIds' => $preview->endingRelationshipPublicIds,
            'affectedUserPublicIds' => $preview->affectedUserPublicIds,
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

    private function firstValidationError(mixed $value): ?string
    {
        if (! is_array($value)) {
            return null;
        }

        $message = reset($value);

        return is_string($message) && $message !== '' ? $message : null;
    }
}
