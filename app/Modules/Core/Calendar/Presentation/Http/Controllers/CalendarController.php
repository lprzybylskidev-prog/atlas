<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Presentation\Http\Controllers;

use App\Modules\Core\Calendar\Application\DTOs\CalendarOccurrenceView;
use App\Modules\Core\Calendar\Application\DTOs\CalendarPreference;
use App\Modules\Core\Calendar\Application\Exceptions\CalendarEventNotFound;
use App\Modules\Core\Calendar\Application\Exceptions\StaleCalendarEvent;
use App\Modules\Core\Calendar\Application\Services\PersonalCalendar;
use App\Modules\Core\Calendar\Presentation\Http\Requests\CalendarEventRequest;
use App\Shared\Presentation\Support\FlashMessage;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CalendarController
{
    private const TIMEZONE = 'Europe/Warsaw';

    public function __construct(private PersonalCalendar $calendar) {}

    public function index(Request $request): Response
    {
        $view = $request->query('view');
        $view = is_string($view) && in_array($view, ['month', 'week', 'day', 'agenda'], true) ? $view : 'month';
        $date = $this->selectedDate($request->query('date'));
        [$rangeStart, $rangeEnd] = $this->range($view, $date);
        $preference = $this->calendar->preference($this->userId($request));

        return Inertia::render('Calendar/Index', [
            'view' => $view,
            'selectedDate' => $date->format('Y-m-d'),
            'range' => [
                'startsAt' => $rangeStart->format(DATE_ATOM),
                'endsAt' => $rangeEnd->format(DATE_ATOM),
            ],
            'events' => array_map(
                static fn (CalendarOccurrenceView $event): array => get_object_vars($event),
                $this->calendar->occurrences($this->userId($request), $rangeStart, $rangeEnd),
            ),
            'preference' => get_object_vars($preference),
        ]);
    }

    public function store(CalendarEventRequest $request): RedirectResponse
    {
        $this->calendar->create($this->userId($request), $request->eventInput());

        return back()->with('flash.messages', [FlashMessage::success('flash.calendar.event_created')]);
    }

    public function update(CalendarEventRequest $request, string $event): RedirectResponse
    {
        try {
            $this->calendar->update(
                $this->userId($request),
                $event,
                $request->string('occurrence_date')->toString(),
                $request->string('mutation_scope', 'series')->toString(),
                $request->integer('version'),
                $request->eventInput(),
            );
        } catch (CalendarEventNotFound) {
            abort(404);
        } catch (StaleCalendarEvent) {
            return back()->withErrors(['version' => __('validation.calendar_stale')]);
        }

        return back()->with('flash.messages', [FlashMessage::success('flash.calendar.event_updated')]);
    }

    public function destroy(Request $request, string $event): RedirectResponse
    {
        $validated = $request->validate([
            'occurrence_date' => ['required', 'date_format:Y-m-d'],
            'mutation_scope' => ['required', 'in:occurrence,future,series'],
            'version' => ['required', 'integer', 'min:1'],
        ]);
        if (! is_array($validated)) {
            abort(422);
        }

        try {
            $this->calendar->delete(
                $this->userId($request),
                $event,
                $this->validatedString($validated, 'occurrence_date'),
                $this->validatedString($validated, 'mutation_scope'),
                $this->validatedInt($validated, 'version'),
            );
        } catch (CalendarEventNotFound) {
            abort(404);
        } catch (StaleCalendarEvent) {
            return back()->withErrors(['version' => __('validation.calendar_stale')]);
        }

        return back()->with('flash.messages', [FlashMessage::success('flash.calendar.event_deleted')]);
    }

    public function updatePreference(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_reminder_minutes' => ['required', 'integer', 'min:0', 'max:525600'],
            'email_enabled' => ['required', 'boolean'],
        ]);
        if (! is_array($validated)) {
            abort(422);
        }

        $this->calendar->savePreference($this->userId($request), new CalendarPreference(
            $this->validatedInt($validated, 'default_reminder_minutes'),
            $request->boolean('email_enabled'),
        ));

        return back()->with('flash.messages', [FlashMessage::success('flash.calendar.preference_updated')]);
    }

    private function userId(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : abort(403);
    }

    private function selectedDate(mixed $value): DateTimeImmutable
    {
        $timezone = new DateTimeZone(self::TIMEZONE);

        if (is_string($value)) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
            if ($date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value) {
                return $date;
            }
        }

        return new DateTimeImmutable('today', $timezone);
    }

    /** @return array{DateTimeImmutable, DateTimeImmutable} */
    private function range(string $view, DateTimeImmutable $date): array
    {
        return match ($view) {
            'day' => [$date->setTime(0, 0), $date->modify('+1 day')->setTime(0, 0)],
            'week' => [$date->modify('monday this week')->setTime(0, 0), $date->modify('monday this week +7 days')->setTime(0, 0)],
            'agenda' => [$date->setTime(0, 0), $date->modify('+90 days')->setTime(0, 0)],
            default => [
                $date->modify('first day of this month')->modify('monday this week')->setTime(0, 0),
                $date->modify('first day of this month')->modify('monday this week +42 days')->setTime(0, 0),
            ],
        };
    }

    /** @param array<mixed, mixed> $validated */
    private function validatedString(array $validated, string $key): string
    {
        $value = $validated[$key] ?? null;

        return is_string($value) ? $value : abort(422);
    }

    /** @param array<mixed, mixed> $validated */
    private function validatedInt(array $validated, string $key): int
    {
        $value = $validated[$key] ?? null;

        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : abort(422));
    }
}
