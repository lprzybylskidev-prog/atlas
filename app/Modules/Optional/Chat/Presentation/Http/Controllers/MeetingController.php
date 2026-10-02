<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\ChatModuleAccess;
use App\Modules\Optional\Chat\Application\DTOs\MeetingInput;
use App\Modules\Optional\Chat\Application\MeetingManager;
use App\Modules\Optional\Chat\Application\MeetingRecordingManager;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Domain\Conversations\MeetingResponse;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingMode;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingMutationScope;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecurrence;
use App\Shared\Presentation\Support\FlashMessage;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class MeetingController
{
    private const TIMEZONE = 'Europe/Warsaw';

    public function __construct(private MeetingManager $meetings, private UserLookup $users, private ChatModuleAccess $access, private MeetingRecordingManager $recordings) {}

    public function index(Request $request): Response
    {
        [$user, $team] = $this->context($request);

        return Inertia::render('Meetings/Index', [
            'meetings' => $this->meetings->listFor($user, $team),
            'users' => array_map(static fn ($item): array => ['publicId' => $item->publicId, 'name' => $item->name, 'email' => $item->email], $this->users->allActiveDisplaySummaries()),
            'now' => (new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d\TH:i'),
        ]);
    }

    public function show(Request $request, string $meeting): Response
    {
        [$user, $team] = $this->context($request);

        $view = $this->meetings->show($user, $team, $meeting);
        $view['canManageRecording'] = ($view['role'] ?? null) === 'organizer'
            && ($view['mode'] ?? null) !== 'in_person'
            && $this->access->allows($user, $team, ChatPermissionCatalog::RECORDING_MANAGE);
        $startsAt = $view['startsAt'] ?? null;
        $recording = ($view['mode'] ?? null) !== 'in_person' && is_string($startsAt) && $this->access->allows($user, $team, ChatPermissionCatalog::RECORDING_STATE)
            ? $this->recordings->state($user, $team, $meeting, substr($startsAt, 0, 10))
            : null;

        return Inertia::render('Meetings/Show', ['meeting' => $view, 'recording' => $recording,
            'users' => array_map(static fn ($item): array => ['publicId' => $item->publicId, 'name' => $item->name, 'email' => $item->email], $this->users->allActiveDisplaySummaries())]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$user, $team] = $this->context($request);
        $result = $this->meetings->create($user, $team, $this->input($request));
        $messages = [FlashMessage::success('flash.meetings.created')];
        if ($result['conflicts'] !== []) {
            $messages[] = FlashMessage::warning('flash.meetings.conflicts');
        }

        return redirect('/meetings/'.$result['meeting']->publicId)->with('flash.messages', $messages);
    }

    public function update(Request $request, string $meeting): RedirectResponse
    {
        [$user, $team] = $this->context($request);
        $this->meetings->update($user, $team, $meeting, $this->input($request), $this->scope($request), $this->occurrenceDate($request));

        return back()->with('flash.messages', [FlashMessage::success('flash.meetings.updated')]);
    }

    public function cancel(Request $request, string $meeting): RedirectResponse
    {
        [$user, $team] = $this->context($request);
        $this->meetings->cancel($user, $team, $meeting, $this->scope($request), $this->occurrenceDate($request));

        return back()->with('flash.messages', [FlashMessage::success('flash.meetings.cancelled')]);
    }

    public function invite(Request $request, string $meeting): RedirectResponse
    {
        $request->validate(['user_public_id' => ['required', 'string', 'size:26']]);
        [$user, $team] = $this->context($request);
        $this->meetings->invite($user, $team, $meeting, $request->string('user_public_id')->toString());

        return back()->with('flash.messages', [FlashMessage::success('flash.meetings.invited')]);
    }

    public function respond(Request $request, string $meeting): RedirectResponse
    {
        $request->validate(['response' => ['required', 'in:accepted,declined']]);
        [$user, $team] = $this->context($request);
        $this->meetings->respond($user, $team, $meeting, MeetingResponse::from($request->string('response')->toString()));

        return back()->with('flash.messages', [FlashMessage::success('flash.meetings.response_updated')]);
    }

    public function remove(Request $request, string $meeting, string $participant): RedirectResponse
    {
        [$user, $team] = $this->context($request);
        $this->meetings->remove($user, $team, $meeting, $participant);

        return back()->with('flash.messages', [FlashMessage::success('flash.meetings.removed')]);
    }

    private function input(Request $request): MeetingInput
    {
        $data = $this->stringKeyedArray($request->validate([
            'title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'mode' => ['required', 'in:online,in_person,hybrid'],
            'location' => ['nullable', 'string', 'max:300', 'required_if:mode,in_person,hybrid', 'prohibited_if:mode,online'],
            'invitee_public_ids' => ['array'], 'invitee_public_ids.*' => ['string', 'size:26', 'distinct'],
            'recurrence_frequency' => ['nullable', 'in:daily,weekly,monthly'], 'recurrence_weekdays' => ['array'], 'recurrence_weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'recurrence_ends_on' => ['nullable', 'date_format:Y-m-d'], 'recurrence_count' => ['nullable', 'integer', 'between:1,500'],
            'reminder_minutes' => ['array'], 'reminder_minutes.*' => ['integer', 'between:0,525600', 'distinct'],
        ]));
        $frequency = $this->nullableString($data['recurrence_frequency'] ?? null);
        $recurrence = $frequency === null ? null : new MeetingRecurrence(
            $frequency,
            $this->intList($data['recurrence_weekdays'] ?? []),
            ($endsOn = $this->nullableString($data['recurrence_ends_on'] ?? null)) === null ? null : new DateTimeImmutable($endsOn, new DateTimeZone(self::TIMEZONE)),
            $this->nullableInt($data['recurrence_count'] ?? null),
        );

        return new MeetingInput(
            $this->requiredString($data, 'title'),
            $this->nullableString($data['description'] ?? null),
            new DateTimeImmutable($this->requiredString($data, 'starts_at'), new DateTimeZone(self::TIMEZONE)),
            new DateTimeImmutable($this->requiredString($data, 'ends_at'), new DateTimeZone(self::TIMEZONE)),
            MeetingMode::from($this->requiredString($data, 'mode')),
            $this->nullableString($data['location'] ?? null),
            $recurrence,
            $this->stringList($data['invitee_public_ids'] ?? []),
            $this->intList($data['reminder_minutes'] ?? [15]),
        );
    }

    private function scope(Request $request): MeetingMutationScope
    {
        $request->validate(['mutation_scope' => ['required', 'in:occurrence,future,series']]);

        return MeetingMutationScope::from($request->string('mutation_scope')->toString());
    }

    private function occurrenceDate(Request $request): string
    {
        $request->validate(['occurrence_date' => ['required', 'date_format:Y-m-d']]);

        return $request->string('occurrence_date')->toString();
    }

    /** @return array<string, mixed> */
    private function stringKeyedArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : abort(422);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /** @return list<int> */
    private function intList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $integers = [];
        foreach ($value as $item) {
            $integer = $this->nullableInt($item);
            if ($integer !== null) {
                $integers[] = $integer;
            }
        }

        return $integers;
    }

    private function nullableInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    /** @return array{string,string} */
    private function context(Request $request): array
    {
        $user = data_get($request->user(), 'public_id');
        $team = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        return is_string($user) && is_string($team) ? [$user, $team] : abort(403);
    }
}
