<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Controllers;

use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Observability\ApplicationLogReader;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminApplicationLogController
{
    public function __construct(
        private ApplicationLogReader $reader,
    ) {}

    public function __invoke(Request $request): Response
    {
        $logFiles = $this->reader->logFiles();
        $requestedFile = $this->oneOf($request->query('file'), array_column($logFiles, 'name'), $logFiles[0]['name'] ?? '');
        $log = $this->reader->latest(fileName: $requestedFile);
        $selectedFile = (string) ($log['summary']['pathLabel'] ?? $requestedFile);
        $filters = $this->filters($request, $log['entries'], $selectedFile);
        $entries = $this->filteredEntries($log['entries'], $filters);

        return Inertia::render('Admin/Logs/Index', [
            'logs' => $entries,
            'summary' => [
                ...$log['summary'],
                'visible' => count($entries),
                'errors' => count(array_filter($log['entries'], static fn (array $entry): bool => in_array($entry['level'] ?? '', ['critical', 'error', 'emergency', 'alert'], true))),
                'warnings' => count(array_filter($log['entries'], static fn (array $entry): bool => ($entry['level'] ?? '') === 'warning')),
                'withDetails' => count(array_filter($log['entries'], static fn (array $entry): bool => trim((string) ($entry['details'] ?? '')) !== '')),
                'files' => count($logFiles),
            ],
            'filters' => $filters,
            'filterOptions' => [
                ...$this->filterOptions($log['entries']),
                'files' => $logFiles,
            ],
            'tableKey' => AdminTableDefinitions::APPLICATION_LOGS,
            'exports' => AdminDataTableExportMeta::defaults(),
        ]);
    }

    /**
     * @param  list<array<string, string>>  $entries
     * @return array{file: string, level: string, module: string, source: string, from: string, to: string, search: string}
     */
    private function filters(Request $request, array $entries, string $selectedFile): array
    {
        return [
            'file' => $selectedFile,
            'level' => $this->oneOf($request->query('level'), $this->allOr($this->uniqueValues($entries, 'level'))),
            'module' => $this->oneOf($request->query('module'), $this->allOr($this->uniqueValues($entries, 'module'))),
            'source' => $this->oneOf($request->query('source'), $this->allOr($this->uniqueValues($entries, 'source'))),
            'from' => $this->dateValue($request->query('from')),
            'to' => $this->dateValue($request->query('to')),
            'search' => $this->stringValue($request->query('search')),
        ];
    }

    /**
     * @param  list<array<string, string>>  $entries
     * @param  array{file: string, level: string, module: string, source: string, from: string, to: string, search: string}  $filters
     * @return list<array<string, string>>
     */
    private function filteredEntries(array $entries, array $filters): array
    {
        $search = mb_strtolower(trim($filters['search']));

        return array_values(array_filter($entries, static function (array $entry) use ($filters, $search): bool {
            if ($filters['level'] !== 'all' && ($entry['level'] ?? '') !== $filters['level']) {
                return false;
            }

            if ($filters['module'] !== 'all' && ($entry['module'] ?? '') !== $filters['module']) {
                return false;
            }

            if ($filters['source'] !== 'all' && ($entry['source'] ?? '') !== $filters['source']) {
                return false;
            }

            if (! self::dateRangeMatches((string) ($entry['occurredAt'] ?? ''), $filters['from'], $filters['to'])) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            foreach (['message', 'details', 'correlationId', 'requestId', 'eventName', 'channel', 'environment'] as $key) {
                if (str_contains(mb_strtolower((string) ($entry[$key] ?? '')), $search)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param  list<array<string, string>>  $entries
     * @return array{levels: list<string>, modules: list<string>, sources: list<string>}
     */
    private function filterOptions(array $entries): array
    {
        return [
            'levels' => $this->uniqueValues($entries, 'level'),
            'modules' => $this->uniqueValues($entries, 'module'),
            'sources' => $this->uniqueValues($entries, 'source'),
        ];
    }

    /**
     * @param  list<array<string, string>>  $entries
     * @return list<string>
     */
    private function uniqueValues(array $entries, string $key): array
    {
        $values = [];

        foreach ($entries as $entry) {
            $value = $entry[$key] ?? '';

            if ($value !== '') {
                $values[] = $value;
            }
        }

        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function allOr(array $values): array
    {
        return array_values(array_unique(['all', ...$values]));
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed, string $fallback = 'all'): string
    {
        if (is_string($value) && in_array($value, $allowed, true)) {
            return $value;
        }

        return $fallback;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? mb_substr((string) $value, 0, 200) : '';
    }

    private function dateValue(mixed $value): string
    {
        $value = $this->stringValue($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }

    private static function dateRangeMatches(string $value, string $from, string $to): bool
    {
        if ($value === '') {
            return $from === '' && $to === '';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return true;
        }

        if ($from !== '') {
            $fromTimestamp = strtotime($from.' 00:00:00');

            if ($fromTimestamp !== false && $timestamp < $fromTimestamp) {
                return false;
            }
        }

        if ($to !== '') {
            $toTimestamp = strtotime($to.' 23:59:59');

            if ($toTimestamp !== false && $timestamp > $toTimestamp) {
                return false;
            }
        }

        return true;
    }
}
