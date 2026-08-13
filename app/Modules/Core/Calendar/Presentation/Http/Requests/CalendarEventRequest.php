<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Presentation\Http\Requests;

use App\Modules\Core\Calendar\Application\DTOs\CalendarEventInput;
use App\Modules\Core\Calendar\Domain\Events\CalendarAvailability;
use App\Modules\Core\Calendar\Domain\Events\RecurrenceFrequency;
use App\Modules\Core\Calendar\Domain\Events\RecurrenceRule;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CalendarEventRequest extends FormRequest
{
    private const TIMEZONE = 'Europe/Warsaw';

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'string', Rule::when($this->boolean('all_day'), 'date_format:Y-m-d', 'date_format:Y-m-d\TH:i:s')],
            'ends_at' => ['required', 'string', Rule::when($this->boolean('all_day'), 'date_format:Y-m-d', 'date_format:Y-m-d\TH:i:s')],
            'all_day' => ['required', 'boolean'],
            'location' => ['nullable', 'string', 'max:300'],
            'availability' => ['required', Rule::enum(CalendarAvailability::class)],
            'recurrence_frequency' => ['nullable', Rule::enum(RecurrenceFrequency::class)],
            'recurrence_interval' => ['required', 'integer', 'min:1', 'max:365'],
            'recurrence_weekdays' => ['array', 'max:7'],
            'recurrence_weekdays.*' => ['integer', 'distinct', 'between:1,7'],
            'recurrence_ends_on' => ['nullable', 'date_format:Y-m-d'],
            'recurrence_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'reminder_minutes' => ['array', 'max:10'],
            'reminder_minutes.*' => ['integer', 'distinct', 'min:0', 'max:525600'],
            'mutation_scope' => [Rule::requiredIf($this->isMethod('PATCH')), Rule::in(['occurrence', 'future', 'series'])],
            'occurrence_date' => [Rule::requiredIf($this->isMethod('PATCH')), 'date_format:Y-m-d'],
            'version' => [Rule::requiredIf($this->isMethod('PATCH')), 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = $this->localDateTime($this->requiredStringValue('starts_at'));
            $end = $this->localDateTime($this->requiredStringValue('ends_at'));

            if ($start === null || $end === null) {
                $validator->errors()->add('starts_at', __('validation.calendar_local_time'));

                return;
            }

            if (! $this->boolean('all_day') && $end <= $start) {
                $validator->errors()->add('ends_at', __('validation.calendar_end_after_start'));
            }

            $recurrenceEndsOn = $this->input('recurrence_ends_on');
            if (is_string($recurrenceEndsOn) && $recurrenceEndsOn !== '' && $recurrenceEndsOn < $start->format('Y-m-d')) {
                $validator->errors()->add('recurrence_ends_on', __('validation.calendar_recurrence_end_after_start'));
            }
        });
    }

    public function eventInput(): CalendarEventInput
    {
        $start = $this->requiredLocalDateTime('starts_at');
        $end = $this->requiredLocalDateTime('ends_at');
        $allDay = $this->boolean('all_day');

        if ($allDay) {
            $start = $start->setTime(0, 0);
            $end = $end->setTime(0, 0);
            if ($end <= $start) {
                $end = $start->modify('+1 day');
            }
        }

        $frequencyValue = $this->input('recurrence_frequency');
        $frequency = is_string($frequencyValue) ? RecurrenceFrequency::tryFrom($frequencyValue) : null;
        $endsOn = $this->input('recurrence_ends_on');
        $count = $this->input('recurrence_count');

        return new CalendarEventInput(
            title: trim($this->requiredStringValue('title')),
            description: $this->nullableString('description'),
            startsAt: $start,
            endsAt: $end,
            allDay: $allDay,
            location: $this->nullableString('location'),
            availability: CalendarAvailability::from($this->requiredStringValue('availability')),
            recurrence: $frequency === null ? null : new RecurrenceRule(
                frequency: $frequency,
                interval: $this->requiredIntValue('recurrence_interval'),
                weekdays: $this->integerList('recurrence_weekdays'),
                endsOn: is_string($endsOn) && $endsOn !== ''
                    ? new DateTimeImmutable($endsOn, new DateTimeZone(self::TIMEZONE))
                    : null,
                occurrenceCount: is_numeric($count) ? (int) $count : null,
            ),
            reminderMinutes: $this->integerList('reminder_minutes'),
        );
    }

    private function requiredLocalDateTime(string $key): DateTimeImmutable
    {
        return $this->localDateTime($this->requiredStringValue($key))
            ?? throw new \LogicException('Validated Calendar local date/time is unavailable.');
    }

    private function localDateTime(string $value): ?DateTimeImmutable
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $format = $this->boolean('all_day') ? '!Y-m-d' : '!Y-m-d\TH:i:s';
        $outputFormat = $this->boolean('all_day') ? 'Y-m-d' : 'Y-m-d\TH:i:s';
        $date = DateTimeImmutable::createFromFormat($format, $value, $timezone);

        if (! $date instanceof DateTimeImmutable || $date->format($outputFormat) !== $value) {
            return null;
        }

        return $date;
    }

    /** @return list<int> */
    private function integerList(string $key): array
    {
        $value = $this->input($key, []);

        if (! is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            if (is_int($item) || (is_string($item) && ctype_digit($item))) {
                $result[] = (int) $item;
            }
        }

        return $result;
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function requiredStringValue(string $key): string
    {
        $value = $this->input($key);

        return is_string($value) ? $value : '';
    }

    private function requiredIntValue(string $key): int
    {
        $value = $this->input($key, 1);

        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : 1);
    }
}
