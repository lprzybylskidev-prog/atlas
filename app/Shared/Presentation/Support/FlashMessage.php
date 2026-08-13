<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Support;

use Illuminate\Support\Str;

final readonly class FlashMessage
{
    /**
     * @return array{id: string, type: 'success', key: string, descriptionKey?: string, timeoutMs?: int|null, critical?: bool}
     */
    public static function success(string $key, ?string $descriptionKey = null, ?int $timeoutMs = null, bool $critical = false): array
    {
        return [
            'type' => 'success',
            ...self::payload($key, $descriptionKey, $timeoutMs, $critical),
        ];
    }

    /**
     * @return array{id: string, type: 'info', key: string, descriptionKey?: string, timeoutMs?: int|null, critical?: bool}
     */
    public static function info(string $key, ?string $descriptionKey = null, ?int $timeoutMs = null, bool $critical = false): array
    {
        return [
            'type' => 'info',
            ...self::payload($key, $descriptionKey, $timeoutMs, $critical),
        ];
    }

    /**
     * @return array{id: string, type: 'warning', key: string, descriptionKey?: string, timeoutMs?: int|null, critical?: bool}
     */
    public static function warning(string $key, ?string $descriptionKey = null, ?int $timeoutMs = null, bool $critical = false): array
    {
        return [
            'type' => 'warning',
            ...self::payload($key, $descriptionKey, $timeoutMs, $critical),
        ];
    }

    /**
     * @return array{id: string, type: 'error', key: string, descriptionKey?: string, timeoutMs?: int|null, critical?: bool}
     */
    public static function error(string $key, ?string $descriptionKey = null, ?int $timeoutMs = null, bool $critical = false): array
    {
        return [
            'type' => 'error',
            ...self::payload($key, $descriptionKey, $timeoutMs, $critical),
        ];
    }

    /**
     * @return array{id: string, key: string, descriptionKey?: string, timeoutMs?: int|null, critical?: bool}
     */
    private static function payload(string $key, ?string $descriptionKey, ?int $timeoutMs, bool $critical): array
    {
        $message = [
            'id' => (string) Str::ulid(),
            'key' => $key,
        ];

        if ($descriptionKey !== null) {
            $message['descriptionKey'] = $descriptionKey;
        }

        if ($timeoutMs !== null) {
            $message['timeoutMs'] = $timeoutMs;
        }

        if ($critical) {
            $message['critical'] = true;
        }

        return $message;
    }
}
