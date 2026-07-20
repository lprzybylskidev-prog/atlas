<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Public\DTOs;

readonly class ProcessPermissions
{
    public function __construct(
        public string $view,
        public string $run,
        public string $retry,
        public string $cancel,
        public string $schedule,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'view' => $this->view,
            'run' => $this->run,
            'retry' => $this->retry,
            'cancel' => $this->cancel,
            'schedule' => $this->schedule,
        ];
    }
}
