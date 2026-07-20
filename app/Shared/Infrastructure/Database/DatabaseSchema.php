<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database;

use Illuminate\Support\Facades\DB;

final class DatabaseSchema
{
    public const CORE_IDENTITY = 'core_identity';

    public const CORE_TEAMS = 'core_teams';

    public const CORE_AUTHORIZATION = 'core_authorization';

    public const CORE_AUDIT = 'core_audit';

    public const CORE_SETTINGS = 'core_settings';

    public const CORE_NOTIFICATIONS = 'core_notifications';

    public const CORE_FILES = 'core_files';

    public const OPTIONAL_INTEGRATIONS = 'optional_integrations';

    public const SHARED = 'shared';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CORE_IDENTITY,
            self::CORE_TEAMS,
            self::CORE_AUTHORIZATION,
            self::CORE_AUDIT,
            self::CORE_SETTINGS,
            self::CORE_NOTIFICATIONS,
            self::CORE_FILES,
            self::OPTIONAL_INTEGRATIONS,
            self::SHARED,
        ];
    }

    public static function ensure(string $schema): void
    {
        DB::statement(sprintf('create schema if not exists %s', self::quoteIdentifier($schema)));
    }

    public static function ensureAll(): void
    {
        foreach (self::all() as $schema) {
            self::ensure($schema);
        }
    }

    public static function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
