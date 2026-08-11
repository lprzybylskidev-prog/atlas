<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Console;

use App\Shared\Infrastructure\Database\DatabaseSchema;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Console\WipeCommand;

final class AtlasDatabaseWipeCommand extends WipeCommand
{
    /**
     * Drop framework/package tables and every explicitly owned Atlas schema.
     *
     * @param  string|null  $database
     */
    protected function dropAllTables($database): void
    {
        $connectionName = is_string($database)
            ? $database
            : $this->defaultConnectionName();

        parent::dropAllTables($connectionName);

        $connections = $this->laravel->make(ConnectionResolverInterface::class);
        $connection = $this->resolveDirectConnectionIfPossible($connections, $connectionName);

        foreach (array_reverse(DatabaseSchema::all()) as $schema) {
            $connection->statement(sprintf(
                'drop schema if exists %s cascade',
                DatabaseSchema::quoteIdentifier($schema),
            ));
        }
    }

    private function defaultConnectionName(): string
    {
        $name = $this->laravel->make(Repository::class)->get('database.default');

        return is_string($name) ? $name : 'pgsql';
    }
}
