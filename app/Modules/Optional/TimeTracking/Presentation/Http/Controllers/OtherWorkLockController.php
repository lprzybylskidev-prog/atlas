<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\UserStepUpAuthentication;
use App\Modules\Optional\TimeTracking\Application\OtherWorkSessionCoordinator;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\FlashMessage;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OtherWorkLockController
{
    public function __construct(
        private OtherWorkSessionCoordinator $otherWork,
        private UserStepUpAuthentication $stepUp,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $userId = $this->userId($request);
        $otherWork = $userId === null ? null : $this->activeOtherWork($userId);

        if ($otherWork === null) {
            return redirect()->route('dashboard');
        }

        $startedAt = new DateTimeImmutable($this->stringValue($otherWork->started_at ?? null));
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw'));
        $teamId = $this->intValue($otherWork->team_id ?? null);
        $elapsedSeconds = max(0, $now->getTimestamp() - $startedAt->getTimestamp());

        return Inertia::render('TimeTracking/OtherWorkLock', [
            'otherWorkSession' => [
                'publicId' => $this->stringValue($otherWork->public_id ?? null),
                'startedAt' => $startedAt->format(DateTimeImmutable::ATOM),
                'elapsedSeconds' => $elapsedSeconds,
                'categoryLabel' => $this->categoryLabel($teamId, $this->nullableString($otherWork->category_key ?? null)),
                'description' => $this->stringValue($otherWork->description ?? null),
                'approvalStatus' => $this->stringValue($otherWork->approval_status ?? null),
            ],
            'mfaRequired' => $this->stepUp->currentUserRequiresMfa($request),
        ]);
    }

    public function end(Request $request): RedirectResponse
    {
        $request->validate([
            'end_note' => ['nullable', 'string', 'max:2000'],
            'password' => ['required', 'string'],
            'mfa_code' => ['nullable', 'string'],
        ], [], [
            'end_note' => __('validation.attributes.time_tracking_other_work_end_note'),
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
            $this->otherWork->end(
                $userId,
                new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw')),
                $this->nullableString($request->string('end_note')->toString()),
            );
        }

        return redirect()->route('dashboard')->with('flash.messages', [FlashMessage::success('flash.time_tracking.other_work_ended')]);
    }

    private function activeOtherWork(int $userId): ?object
    {
        return DB::table(DatabaseTable::TIME_TRACKING_OTHER_WORK)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->first(['public_id', 'team_id', 'category_key', 'description', 'approval_status', 'started_at']);
    }

    private function categoryLabel(int $teamId, ?string $categoryKey): string
    {
        if ($categoryKey === null) {
            return __('pages.time_tracking.other_work_lock.category.none');
        }

        $labelColumn = app()->getLocale() === 'pl' ? 'label_pl' : 'label_en';
        $row = DB::table(DatabaseTable::TIME_TRACKING_OTHER_WORK_CATEGORIES)
            ->where('category_key', $categoryKey)
            ->where('scope_type', 'team')
            ->where('scope_id', $teamId)
            ->value($labelColumn);

        return is_string($row) && $row !== '' ? $row : Str::headline(str_replace('_', ' ', $categoryKey));
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

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
