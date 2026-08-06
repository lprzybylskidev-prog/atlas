<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Optional\TimeTracking\Application\Contracts\UserTeamTrackingSettings;
use App\Modules\Optional\TimeTracking\Application\CorrectionRequestCoordinator;
use App\Modules\Optional\TimeTracking\Application\DTOs\ExactTimeChange;
use App\Modules\Optional\TimeTracking\Application\Enums\CorrectionSourceType;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\TimeTrackingModuleAccess;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Presentation\Support\FlashMessage;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class UserCorrectionRequestController
{
    private const TIMEZONE = 'Europe/Warsaw';

    public function __construct(
        private TimeTrackingModuleAccess $access,
        private UserTeamTrackingSettings $trackingSettings,
        private TableRequestContext $context,
        private CorrectionRequestCoordinator $corrections,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        [$userId, $teamId] = $this->context->userTeam($request);
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        $this->access->ensureAllowed(
            activeTeamId: $teamId,
            activeTeamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            userPublicId: is_string($userPublicId) ? $userPublicId : null,
            requiredPermission: TimeTrackingPermissionCatalog::USER_CORRECTION_REQUEST_STORE,
        );

        if ($userId <= 0 || $teamId <= 0 || ! $this->trackingSettings->isEnabledForUserTeam($userId, $teamId)) {
            abort(403);
        }

        $values = Validator::make($request->all(), [
            'source_type' => ['required', 'string', 'in:work_session,break,other_work'],
            'source_public_id' => ['required', 'string'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'proposed_started_at' => ['nullable', 'date', 'required_with:proposed_ended_at'],
            'proposed_ended_at' => ['nullable', 'date', 'required_with:proposed_started_at'],
        ], [], [
            'source_type' => __('validation.attributes.time_tracking_correction_source_type'),
            'source_public_id' => __('validation.attributes.time_tracking_correction_source'),
            'description' => __('validation.attributes.time_tracking_correction_description'),
            'proposed_started_at' => __('validation.attributes.time_tracking_proposed_started_at'),
            'proposed_ended_at' => __('validation.attributes.time_tracking_proposed_ended_at'),
        ])->validate();

        $sourceType = CorrectionSourceType::from($this->stringValue($values['source_type'] ?? null));
        $source = $this->sourceRecord($sourceType, $this->stringValue($values['source_public_id'] ?? null), $userId, $teamId);
        $description = $this->stringValue($values['description'] ?? null);
        $proposedStarted = $this->nullableString($values['proposed_started_at'] ?? null);
        $proposedEnded = $this->nullableString($values['proposed_ended_at'] ?? null);
        $now = new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));

        if ($proposedStarted !== null && $proposedEnded !== null) {
            $this->corrections->requestSourceExactChange(
                userId: $userId,
                teamId: $teamId,
                sourceType: $sourceType,
                sourceId: $this->intValue($source->id ?? null),
                description: $description,
                original: $this->changeFromSource($source),
                proposed: $this->changeFromInput($proposedStarted, $proposedEnded),
                requestedAt: $now,
            );
        } else {
            $this->corrections->requestSourceDescriptive(
                userId: $userId,
                teamId: $teamId,
                sourceType: $sourceType,
                sourceId: $this->intValue($source->id ?? null),
                description: $description,
                requestedAt: $now,
            );
        }

        return back()->with('flash.messages', [FlashMessage::success('flash.time_tracking.user_correction_requested')]);
    }

    private function sourceRecord(CorrectionSourceType $sourceType, string $publicId, int $userId, int $teamId): object
    {
        $table = match ($sourceType) {
            CorrectionSourceType::WorkSession => TimeTrackingDatabaseTable::WORK_SESSIONS,
            CorrectionSourceType::Break => TimeTrackingDatabaseTable::BREAKS,
            CorrectionSourceType::OtherWork => TimeTrackingDatabaseTable::OTHER_WORK,
        };
        $record = DB::table($table)
            ->where('public_id', $publicId)
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->first(['id', 'started_at', 'ended_at', 'exact_seconds']);

        if (! is_object($record)) {
            throw ValidationException::withMessages([
                'source_public_id' => __('validation.time_tracking.correction_source_not_found'),
            ]);
        }

        return $record;
    }

    private function changeFromSource(object $source): ExactTimeChange
    {
        return new ExactTimeChange(
            startedAt: new DateTimeImmutable($this->stringValue($source->started_at ?? null), new DateTimeZone(self::TIMEZONE)),
            endedAt: $this->nullableDateTime($source->ended_at ?? null),
            exactSeconds: $this->intValue($source->exact_seconds ?? null),
        );
    }

    private function changeFromInput(string $startedAtValue, string $endedAtValue): ExactTimeChange
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $startedAt = new DateTimeImmutable($startedAtValue, $timezone);
        $endedAt = new DateTimeImmutable($endedAtValue, $timezone);

        if ($endedAt <= $startedAt) {
            throw ValidationException::withMessages([
                'proposed_ended_at' => __('validation.time_tracking.end_must_be_after_start'),
            ]);
        }

        return new ExactTimeChange($startedAt, $endedAt, $endedAt->getTimestamp() - $startedAt->getTimestamp());
    }

    private function nullableDateTime(mixed $value): ?DateTimeImmutable
    {
        $string = $this->nullableString($value);

        return $string === null ? null : new DateTimeImmutable($string, new DateTimeZone(self::TIMEZONE));
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim($this->stringValue($value));

        return $string === '' ? null : $string;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
