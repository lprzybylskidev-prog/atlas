<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Infrastructure\Runtime;

use App\Modules\Optional\ManagedProcesses\Application\Contracts\ManagedProcessHandler;
use App\Modules\Optional\ManagedProcesses\Application\Contracts\ProcessDefinitionRegistry;
use App\Modules\Optional\ManagedProcesses\Application\DTOs\ProcessLogEntry;
use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessLogSeverity;
use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessRunStatus;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunner;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class ExecuteManagedProcessJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $runPublicId) {}

    public function handle(ProcessDefinitionRegistry $definitions): void
    {
        $runner = app(ManagedProcessRunner::class);
        $run = DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)->where('public_id', $this->runPublicId)->first();

        if (! is_object($run)) {
            throw new RuntimeException('Managed process run was not found.');
        }

        $processKey = $run->process_key ?? null;
        $definition = is_scalar($processKey) ? $definitions->get((string) $processKey) : null;

        if ($definition === null) {
            throw new RuntimeException('Managed process definition was not found.');
        }

        $handler = $this->handler($definition->key);

        try {
            $runner->updateProgress($this->runPublicId, ProcessRunStatus::Running, 'started', 0, null, 'Running');
            $runner->log($this->runPublicId, new ProcessLogEntry(ProcessLogSeverity::Info, 'stage', 'Process execution started.', 'started'));
            $handler->handle($this->runPublicId);
        } catch (Throwable $exception) {
            $runner->log($this->runPublicId, new ProcessLogEntry(
                severity: ProcessLogSeverity::Error,
                eventType: 'exception',
                message: 'Process failed with a safe error summary.',
                stage: 'failed',
                safeContext: ['message' => mb_substr($exception->getMessage(), 0, 500)],
                exceptionClass: $exception::class,
                retryable: true,
            ));
            $runner->updateProgress($this->runPublicId, ProcessRunStatus::Failed, 'failed', null, null, 'Failed', null, null, $exception->getMessage());

            throw $exception;
        }
    }

    private function handler(string $processKey): ManagedProcessHandler
    {
        foreach (app()->tagged('atlas.managed_process_handlers') as $handler) {
            if ($handler instanceof ManagedProcessHandler && $handler->processKey() === $processKey) {
                return $handler;
            }
        }

        throw new RuntimeException('Managed process handler is not registered.');
    }
}
