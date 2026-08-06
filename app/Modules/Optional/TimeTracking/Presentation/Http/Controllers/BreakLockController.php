<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\UserStepUpAuthentication;
use App\Modules\Optional\TimeTracking\Application\BreakSessionCoordinator;
use App\Modules\Optional\TimeTracking\Application\Contracts\BreakPolicyStore;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use App\Shared\Presentation\Support\FlashMessage;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final readonly class BreakLockController
{
    public function __construct(
        private BreakSessionCoordinator $breaks,
        private BreakPolicyStore $policies,
        private UserStepUpAuthentication $stepUp,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $userId = $this->userId($request);
        $break = $userId === null ? null : $this->activeBreak($userId);

        if ($break === null) {
            return redirect()->route('dashboard');
        }

        $startedAt = new DateTimeImmutable($this->stringValue($break->started_at ?? null));
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw'));
        $teamId = $this->intValue($break->team_id ?? null);
        $policy = $this->policies->policyForUserTeam($userId, $teamId);
        $elapsedSeconds = max(0, $now->getTimestamp() - $startedAt->getTimestamp());

        return Inertia::render('TimeTracking/BreakLock', [
            'breakSession' => [
                'publicId' => $this->stringValue($break->public_id ?? null),
                'startedAt' => $startedAt->format(DateTimeImmutable::ATOM),
                'elapsedSeconds' => $elapsedSeconds,
                'maximumSeconds' => $policy->maximumSingleBreakSeconds,
                'warningBeforeMaximumSeconds' => $policy->warningBeforeMaximumSeconds,
                'remainingSeconds' => max(0, $policy->maximumSingleBreakSeconds - $elapsedSeconds),
                'exceededSeconds' => max(0, $elapsedSeconds - $policy->maximumSingleBreakSeconds),
            ],
            'mfaRequired' => $this->stepUp->currentUserRequiresMfa($request),
        ]);
    }

    public function end(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
            'mfa_code' => ['nullable', 'string'],
        ], [], [
            'password' => __('validation.attributes.current_password'),
            'mfa_code' => __('validation.attributes.mfa_code'),
        ]);

        $result = $this->stepUp->verifyCurrentUser(
            $request,
            $request->string('password')->toString(),
            $request->string('mfa_code')->toString(),
        );

        if (! $result->passwordValid) {
            throw ValidationException::withMessages([
                'password' => __('auth.password_current_mismatch'),
            ]);
        }

        if (! $result->mfaValid) {
            throw ValidationException::withMessages([
                'mfa_code' => __('auth.mfa_code_invalid'),
            ]);
        }

        $userId = $this->userId($request);

        if ($userId !== null && $result->verified) {
            $this->breaks->end($userId, new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw')));
        }

        return redirect()->route('dashboard')->with('flash.messages', [FlashMessage::success('flash.time_tracking.break_ended')]);
    }

    private function activeBreak(int $userId): ?object
    {
        return DB::table(TimeTrackingDatabaseTable::BREAKS)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->first(['public_id', 'team_id', 'started_at']);
    }

    private function userId(Request $request): ?int
    {
        $id = data_get($request->user(), 'id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
