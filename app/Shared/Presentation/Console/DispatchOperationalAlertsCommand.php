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
                titleKey: 'mail.operational_alert.readiness.subject',
                bodyKey: 'mail.operational_alert.readiness.body',
                severity: 'error',
                parameters: ['count' => $report->blockingFailureCount()],
                context: ['blocking_failures' => $report->blockingFailureCount()],
            );
        }

        $schedulerStatus = $scheduler->status();
        $schedulerHealthy = ($schedulerStatus['status'] ?? null) === 'healthy' && ($schedulerStatus['isFresh'] ?? false) === true;

        if (! $schedulerHealthy) {
            $sent += (int) $alerts->send(
                type: 'scheduler.failure',
                titleKey: 'mail.operational_alert.scheduler.subject',
                bodyKey: 'mail.operational_alert.scheduler.body',
                severity: 'error',
                context: ['status' => is_scalar($schedulerStatus['status'] ?? null) ? (string) $schedulerStatus['status'] : null],
            );
        }

        $failedJobs = DB::table(DatabaseTable::FAILED_JOBS.' as failed_jobs')
            ->leftJoin(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS.' as acknowledgements', 'acknowledgements.failed_job_uuid', '=', 'failed_jobs.uuid')
            ->whereNull('acknowledgements.failed_job_uuid')
            ->count();
        $threshold = max(1, config()->integer('atlas.operations.alerts.failed_jobs_threshold', 3));

        if ($failedJobs >= $threshold) {
            $sent += (int) $alerts->send(
                type: 'queue.failed_jobs.repeated',
                titleKey: 'mail.operational_alert.queue.subject',
                bodyKey: 'mail.operational_alert.queue.body',
                severity: 'error',
                parameters: ['count' => $failedJobs],
                context: ['failed_jobs' => $failedJobs, 'threshold' => $threshold],
            );
        }

        $sent += $this->flaggedAlert($alerts, 'backup.failure', 'mail.operational_alert.backup.subject', 'mail.operational_alert.backup.body', 'backup_failed');
        $sent += $this->flaggedAlert($alerts, 'integration.failure', 'mail.operational_alert.integration.subject', 'mail.operational_alert.integration.body', 'integration_failed');
        $sent += $this->flaggedAlert($alerts, 'sentry.critical', 'mail.operational_alert.sentry.subject', 'mail.operational_alert.sentry.body', 'sentry_critical');

        $this->info(sprintf('Dispatched %d operational alert(s).', $sent));

        return self::SUCCESS;
    }

    private function flaggedAlert(OperationalAlertDispatcher $alerts, string $type, string $titleKey, string $bodyKey, string $configKey): int
    {
        if (! config()->boolean('atlas.operations.alerts.'.$configKey, false)) {
            return 0;
        }

        return (int) $alerts->send($type, $titleKey, $bodyKey, 'error');
    }
}
