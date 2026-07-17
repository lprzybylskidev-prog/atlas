<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Presentation\Console;

use App\Modules\Core\Files\Application\Public\Contracts\FileMaintenance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

final class PruneTemporaryFilesCommand extends Command
{
    protected $signature = 'files:prune-temporary {--ttl-minutes= : Override the configured temporary file TTL in minutes}';

    protected $description = 'Prune expired temporary Files module scan files.';

    public function handle(FileMaintenance $maintenance): int
    {
        $configuredTtl = Config::integer('atlas.files.temporary_ttl_minutes', 60);
        $option = $this->option('ttl-minutes');
        $ttl = is_numeric($option) ? (int) $option : $configuredTtl;
        $result = $maintenance->pruneTemporaryFiles(max(1, $ttl));

        $this->info(sprintf(
            'Pruned %d temporary file(s); %d delete(s) failed.',
            $result->deletedTemporaryFiles,
            $result->failedTemporaryDeletes,
        ));

        return $result->failedTemporaryDeletes === 0 ? self::SUCCESS : self::FAILURE;
    }
}
