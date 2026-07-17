<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Observability;

use App\Shared\Application\Modules\Activation\ModuleActivationScheduleStatus;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;

final class ModuleActivationScheduleDiagnostics
{
    /**
     * @return array<string, int|string|bool|null>
     */
    public function status(): array
    {
        $failedCount = DB::table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)
            ->where('status', ModuleActivationScheduleStatus::Failed->value)
            ->count();

        $latestFailure = DB::table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)
            ->where('status', ModuleActivationScheduleStatus::Failed->value)
            ->orderByDesc('updated_at')
            ->first();

        $values = is_object($latestFailure) ? get_object_vars($latestFailure) : [];
        $failed = (int) $failedCount;

        return [
            'status' => $failed > 0 ? 'failed' : 'healthy',
            'label' => $failed > 0 ? 'Failed' : 'Healthy',
            'description' => $failed > 0
                ? 'One or more scheduled module activation changes failed and require operator review.'
                : 'No failed module activation schedules are recorded.',
            'failedCount' => $failed,
            'latestFailedPublicId' => $this->nullableString($values['public_id'] ?? null),
            'latestFailedModule' => $this->nullableString($values['module_key'] ?? null),
            'latestFailedAt' => $this->nullableString($values['updated_at'] ?? null),
            'latestFailureReason' => $this->nullableString($values['failure_reason'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
