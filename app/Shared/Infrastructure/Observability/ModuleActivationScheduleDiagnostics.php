<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Observability;

use App\Shared\Application\Modules\Activation\ModuleActivationScheduleStatus;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;

final class ModuleActivationScheduleDiagnostics
{
    /**
     * @return array<string, int|string|bool|array<int, array<string, string|bool|null>>|null>
     */
    public function status(): array
    {
        $failedCount = DB::table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)
            ->where('status', ModuleActivationScheduleStatus::Failed->value)
            ->count();

        $scheduledCount = DB::table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)
            ->where('status', ModuleActivationScheduleStatus::Scheduled->value)
            ->count();

        $latestFailure = DB::table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)
            ->where('status', ModuleActivationScheduleStatus::Failed->value)
            ->orderByDesc('updated_at')
            ->first();
        $scheduleRows = DB::table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)
            ->whereIn('status', [ModuleActivationScheduleStatus::Failed->value, ModuleActivationScheduleStatus::Scheduled->value])
            ->orderByRaw('case when status = ? then 0 else 1 end', [ModuleActivationScheduleStatus::Failed->value])
            ->orderBy('effective_at')
            ->limit(5)
            ->get(['public_id', 'module_key', 'scope', 'target_enabled', 'effective_at', 'status', 'failure_reason']);

        $values = is_object($latestFailure) ? get_object_vars($latestFailure) : [];
        $failed = (int) $failedCount;
        $scheduled = (int) $scheduledCount;

        return [
            'status' => $failed > 0 ? 'failed' : 'healthy',
            'label' => $failed > 0 ? 'Failed' : 'Healthy',
            'description' => $failed > 0
                ? 'One or more scheduled module activation changes failed and require operator review.'
                : ($scheduled > 0
                    ? 'Scheduled module activation changes are waiting to be applied.'
                    : 'No failed or pending module activation schedules are recorded.'),
            'failedCount' => $failed,
            'scheduledCount' => $scheduled,
            'latestFailedPublicId' => $this->nullableString($values['public_id'] ?? null),
            'latestFailedModule' => $this->nullableString($values['module_key'] ?? null),
            'latestFailedAt' => $this->nullableString($values['updated_at'] ?? null),
            'latestFailureReason' => $this->nullableString($values['failure_reason'] ?? null),
            'items' => $scheduleRows->map(function (object $row): array {
                $values = get_object_vars($row);

                return [
                    'publicId' => $this->nullableString($values['public_id'] ?? null),
                    'module' => $this->nullableString($values['module_key'] ?? null),
                    'scope' => $this->nullableString($values['scope'] ?? null),
                    'targetEnabled' => (bool) ($values['target_enabled'] ?? false),
                    'effectiveAt' => $this->nullableString($values['effective_at'] ?? null),
                    'status' => $this->nullableString($values['status'] ?? null),
                    'failureReason' => $this->nullableString($values['failure_reason'] ?? null),
                ];
            })->all(),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
