<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Console;

use App\Modules\Core\Health\Application\Readiness\Contracts\ReadinessChecker;
use App\Modules\Core\Health\Application\Readiness\HealthCheckStatus;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\Observability\SchedulerHeartbeatMonitor;
use App\Shared\Infrastructure\Operations\OperationalAlertDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class DispatchOperationalAlertsCommand extends Command
{
    protected $signature = 'system:operational-alerts';

    protected $description = 'Dispatch deduplicated operational alerts for readiness, queues, scheduler, backup, integrations, and Sentry signals.';

    public function handle(
        ReadinessChecker $readiness,
        SchedulerHeartbeatMonitor $scheduler,
        OperationalAlertDispatcher $alerts,
    ): int {
        $sent = 0;
        $report = $readiness->check();

        if ($report->status() === HealthCheckStatus::Unhealthy) {
            $sent += (int) $alerts->send(
                type: 'readiness.failure',
                title: 'Readiness failure',
                body: sprintf('%d blocking readiness check(s) failed.', $report->blockingFailureCount()),
                severity: 'error',
                context: ['blocking_failures' => $report->blockingFailureCount()],
            );
        }

        $schedulerStatus = $scheduler->status();
        $schedulerHealthy = ($schedulerStatus['status'] ?? null) === 'healthy' && ($schedulerStatus['isFresh'] ?? false) === true;

        if (! $schedulerHealthy) {
            $sent += (int) $alerts->send(
                type: 'scheduler.failure',
                title: 'Scheduler heartbeat failure',
                body: (string) ($schedulerStatus['description'] ?? 'Scheduler heartbeat is not healthy.'),
                severity: 'error',
                context: ['status' => is_scalar($schedulerStatus['status'] ?? null) ? (string) $schedulerStatus['status'] : null],
            );
        }

        $failedJobs = DB::table(DatabaseTable::FAILED_JOBS)->count();
        $threshold = max(1, config()->integer('atlas.operations.alerts.failed_jobs_threshold', 3));

        if ($failedJobs >= $threshold) {
            $sent += (int) $alerts->send(
                type: 'queue.failed_jobs.repeated',
                title: 'Repeated failed jobs',
                body: sprintf('%d failed job(s) are currently recorded.', $failedJobs),
                severity: 'error',
                context: ['failed_jobs' => $failedJobs, 'threshold' => $threshold],
            );
        }

        $sent += $this->flaggedAlert($alerts, 'backup.failure', 'Backup failure', 'A backup failure signal is active.', 'backup_failed');
        $sent += $this->flaggedAlert($alerts, 'integration.failure', 'Persistent integration failure', 'An integration failure signal is active.', 'integration_failed');
        $sent += $this->flaggedAlert($alerts, 'sentry.critical', 'Critical Sentry exception', 'A critical Sentry signal is active.', 'sentry_critical');

        $this->info(sprintf('Dispatched %d operational alert(s).', $sent));

        return self::SUCCESS;
    }

    private function flaggedAlert(OperationalAlertDispatcher $alerts, string $type, string $title, string $body, string $configKey): int
    {
        if (! config()->boolean('atlas.operations.alerts.'.$configKey, false)) {
            return 0;
        }

        return (int) $alerts->send($type, $title, $body, 'error');
    }
}
