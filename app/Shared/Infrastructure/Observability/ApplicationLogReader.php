<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Observability;

use SplFileObject;
use Throwable;

final readonly class ApplicationLogReader
{
    private const MAX_LINE_LENGTH = 4_000;

    private const DEFAULT_LOG_FILE = 'laravel.log';

    public function __construct(
        private SensitiveDataRedactor $redactor,
        private ?string $path = null,
    ) {}

    /**
     * @return array{entries: list<array<string, string>>, summary: array<string, int|string|null>}
     */
    public function latest(int $limit = 1_000, ?string $fileName = null): array
    {
        $limit = min(max($limit, 1), 2_000);
        $path = $this->path ?? $this->pathForFileName($fileName);

        if (! is_file($path) || ! is_readable($path)) {
            return [
                'entries' => [],
                'summary' => $this->summary($path, 0, null),
            ];
        }

        $lines = $this->tail($path, $limit);
        $entries = [];

        foreach ($this->groupLines($lines) as $entry) {
            $text = trim($entry['text']);

            if ($text === '') {
                continue;
            }

            $entries[] = $this->parseEntry($text, $entry['line']);
        }

        return [
            'entries' => array_reverse($entries),
            'summary' => $this->summary($path, count($entries), filemtime($path) ?: null),
        ];
    }

    /**
     * @return list<array{name: string, size: int, latestModifiedAt: string|null}>
     */
    public function logFiles(): array
    {
        if ($this->path !== null) {
            return [$this->fileSummary($this->path)];
        }

        $paths = glob($this->logDirectory().DIRECTORY_SEPARATOR.'*.log') ?: [];
        $files = [];

        foreach ($paths as $path) {
            if (is_file($path) && is_readable($path)) {
                $files[] = $this->fileSummary($path);
            }
        }

        usort($files, static function (array $left, array $right): int {
            if ($left['name'] === self::DEFAULT_LOG_FILE) {
                return -1;
            }

            if ($right['name'] === self::DEFAULT_LOG_FILE) {
                return 1;
            }

            return $left['name'] <=> $right['name'];
        });

        return $files;
    }

    /**
     * @return array<int, string>
     */
    private function tail(string $path, int $limit): array
    {
        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $start = max(0, $lastLine - $limit + 1);
        $lines = [];

        $file->seek($start);

        while (! $file->eof()) {
            $lineNumber = $file->key() + 1;
            $line = $file->fgets();

            $lines[$lineNumber] = mb_substr($line, 0, self::MAX_LINE_LENGTH);
        }

        return $lines;
    }

    private function pathForFileName(?string $fileName): string
    {
        $files = $this->logFiles();
        $requested = is_string($fileName) && $fileName === basename($fileName) ? $fileName : '';

        foreach ($files as $file) {
            if ($file['name'] === $requested) {
                return $this->logDirectory().DIRECTORY_SEPARATOR.$file['name'];
            }
        }

        foreach ($files as $file) {
            if ($file['name'] === self::DEFAULT_LOG_FILE) {
                return $this->logDirectory().DIRECTORY_SEPARATOR.$file['name'];
            }
        }

        if ($files !== []) {
            return $this->logDirectory().DIRECTORY_SEPARATOR.$files[0]['name'];
        }

        return $this->logDirectory().DIRECTORY_SEPARATOR.self::DEFAULT_LOG_FILE;
    }

    /**
     * @return array{name: string, size: int, latestModifiedAt: string|null}
     */
    private function fileSummary(string $path): array
    {
        return [
            'name' => basename($path),
            'size' => is_file($path) ? (int) filesize($path) : 0,
            'latestModifiedAt' => is_file($path) && filemtime($path) !== false ? date(DATE_ATOM, (int) filemtime($path)) : null,
        ];
    }

    private function logDirectory(): string
    {
        return storage_path('logs');
    }

    /**
     * @return array<string, string>
     */
    /**
     * @param  array<int, string>  $lines
     * @return list<array{line: int, text: string}>
     */
    private function groupLines(array $lines): array
    {
        $groups = [];
        $currentLine = null;
        $currentText = '';

        foreach ($lines as $lineNumber => $line) {
            $line = rtrim($line, "\r\n");

            if ($line === '') {
                continue;
            }

            if ($this->startsEntry($line)) {
                if ($currentLine !== null) {
                    $groups[] = ['line' => $currentLine, 'text' => $currentText];
                }

                $currentLine = $lineNumber;
                $currentText = $line;

                continue;
            }

            if ($currentLine === null) {
                $currentLine = $lineNumber;
                $currentText = $line;

                continue;
            }

            $currentText .= PHP_EOL.$line;
        }

        if ($currentLine !== null) {
            $groups[] = ['line' => $currentLine, 'text' => $currentText];
        }

        return $groups;
    }

    private function startsEntry(string $line): bool
    {
        return $this->decodeJsonLine($line) !== null
            || preg_match('/^\[[^\]]+] [A-Za-z0-9_-]+\.[A-Za-z]+: /', $line) === 1;
    }

    /**
     * @return array<string, string>
     */
    private function parseEntry(string $line, int $lineNumber): array
    {
        $decoded = $this->decodeJsonLine($line);

        if ($decoded !== null) {
            return $this->jsonRow($decoded, $line, $lineNumber);
        }

        return $this->textRow($line, $lineNumber);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonLine(string $line): ?array
    {
        try {
            $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $values = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, string>
     */
    private function jsonRow(array $decoded, string $line, int $lineNumber): array
    {
        $context = $this->redactor->redactStringKeyedArray(is_array($decoded['context'] ?? null) ? $decoded['context'] : []);
        $extra = $this->redactor->redactStringKeyedArray(is_array($decoded['extra'] ?? null) ? $decoded['extra'] : []);
        $atlas = $this->stringKeyedArray($context['atlas'] ?? []);
        $merged = array_merge($context, $extra, $atlas);

        return [
            'publicId' => $this->publicId($lineNumber, $line),
            'line' => (string) $lineNumber,
            'occurredAt' => $this->stringValue($decoded['datetime'] ?? null),
            'level' => strtolower($this->stringValue($decoded['level_name'] ?? $decoded['level'] ?? 'unknown')),
            'channel' => $this->stringValue($decoded['channel'] ?? ''),
            'environment' => $this->stringValue($merged['environment'] ?? ''),
            'module' => $this->stringValue($merged['module'] ?? ''),
            'source' => $this->stringValue($merged['source'] ?? ''),
            'eventName' => $this->stringValue($merged['event_name'] ?? ''),
            'correlationId' => $this->stringValue($merged['correlation_id'] ?? ''),
            'requestId' => $this->stringValue($merged['request_id'] ?? ''),
            'message' => $this->redactor->redactText($this->stringValue($decoded['message'] ?? '')),
            'details' => $this->details($decoded),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function textRow(string $line, int $lineNumber): array
    {
        $row = [
            'occurredAt' => '',
            'environment' => '',
            'level' => 'unknown',
            'message' => $line,
        ];

        $details = '';

        if (preg_match('/^\[(?<datetime>[^\]]+)] (?<environment>[A-Za-z0-9_-]+)\.(?<level>[A-Za-z]+): (?<message>.*)$/s', $line, $matches) === 1) {
            [$message, $details] = $this->splitMessageAndDetails($matches['message']);
            $row = [
                'occurredAt' => $matches['datetime'],
                'environment' => strtolower($matches['environment']),
                'level' => strtolower($matches['level']),
                'message' => $message,
            ];
        } else {
            [$row['message'], $details] = $this->splitMessageAndDetails($line);
        }

        return [
            'publicId' => $this->publicId($lineNumber, $line),
            'line' => (string) $lineNumber,
            'occurredAt' => $row['occurredAt'],
            'level' => $row['level'],
            'channel' => '',
            'environment' => $row['environment'],
            'module' => '',
            'source' => '',
            'eventName' => '',
            'correlationId' => '',
            'requestId' => '',
            'message' => $this->redactor->redactText(mb_substr($row['message'], 0, self::MAX_LINE_LENGTH)),
            'details' => $this->redactor->redactText(mb_substr($details, 0, self::MAX_LINE_LENGTH * 3)),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitMessageAndDetails(string $value): array
    {
        $parts = preg_split("/\R/", $value, 2);

        if (! is_array($parts)) {
            return [$value, ''];
        }

        $message = $parts[0] ?? '';
        $details = $parts[1] ?? '';

        if ($details === '') {
            $inlineContextPosition = strpos($message, ' {"');

            if ($inlineContextPosition !== false) {
                $details = mb_substr($message, $inlineContextPosition + 1);
                $message = mb_substr($message, 0, $inlineContextPosition);
            }
        }

        return [$message, $details];
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function details(array $decoded): string
    {
        $context = $this->redactor->redactArray($decoded);
        unset($context['message'], $context['datetime'], $context['level'], $context['level_name'], $context['channel']);

        if ($context === []) {
            return '';
        }

        $json = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return mb_substr($this->redactor->redactText($json), 0, self::MAX_LINE_LENGTH * 3);
    }

    /**
     * @return array<string, mixed>
     */
    private function stringKeyedArray(mixed $value): array
    {
        return is_array($value) ? $this->redactor->redactStringKeyedArray($value) : [];
    }

    private function publicId(int $lineNumber, string $line): string
    {
        return hash('xxh128', $lineNumber.'|'.$line);
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? mb_substr((string) $value, 0, self::MAX_LINE_LENGTH) : '';
    }

    /**
     * @return array<string, int|string|null>
     */
    private function summary(string $path, int $rows, ?int $modifiedAt): array
    {
        return [
            'source' => 'application',
            'pathLabel' => basename($path),
            'rows' => $rows,
            'latestModifiedAt' => $modifiedAt !== null ? date(DATE_ATOM, $modifiedAt) : null,
        ];
    }
}
