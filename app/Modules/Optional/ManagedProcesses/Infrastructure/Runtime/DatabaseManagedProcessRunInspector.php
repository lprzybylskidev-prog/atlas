<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Infrastructure\Runtime;

use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunInspector;
use App\Modules\Optional\ManagedProcesses\Application\Public\Persistence\ManagedProcessesDatabaseTable;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

final readonly class DatabaseManagedProcessRunInspector implements ManagedProcessRunInspector
{
    public function __construct(private ConnectionInterface $database) {}

    public function inputSnapshot(string $runPublicId): array
    {
        $snapshot = $this->database
            ->table(ManagedProcessesDatabaseTable::RUNS)
            ->where('public_id', $runPublicId)
            ->value('input_snapshot');

        if (! is_string($snapshot) || $snapshot === '') {
            return [];
        }

        $decoded = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Managed process input snapshot must decode to a JSON object.');
        }

        $result = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
