<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Presentation\Console;

use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunner;
use App\Modules\Optional\Search\Application\SearchRebuildProcess;
use Illuminate\Console\Command;

final class RebuildSearchIndexesCommand extends Command
{
    protected $signature = 'search:rebuild
        {--module= : Optional module key to rebuild}
        {--index= : Optional index key to rebuild}
        {--actor= : Actor public ID used for authorization and audit}
        {--team= : Active team public ID used for authorization and module activation}';

    protected $description = 'Start a managed-process run that rebuilds Search indexes.';

    public function handle(ManagedProcessRunner $runner): int
    {
        $actor = $this->stringOption('actor');
        $team = $this->stringOption('team');

        if ($actor === null || $team === null) {
            $this->error('Both --actor and --team are required so Search rebuilds remain authorized and audited.');

            return self::FAILURE;
        }

        $input = array_filter([
            'module_key' => $this->stringOption('module'),
            'index_key' => $this->stringOption('index'),
        ], static fn (?string $value): bool => $value !== null);

        $runPublicId = $runner->start(
            processKey: SearchRebuildProcess::KEY,
            sourceType: 'cli',
            input: $input === [] ? null : $input,
            actorPublicId: $actor,
            teamPublicId: $team,
        );

        $this->info(sprintf('Search rebuild managed process queued: %s', $runPublicId));

        return self::SUCCESS;
    }

    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
