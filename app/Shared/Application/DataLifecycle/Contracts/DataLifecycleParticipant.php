<?php

declare(strict_types=1);

namespace App\Shared\Application\DataLifecycle\Contracts;

use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecyclePreview;
use App\Shared\Application\DataLifecycle\DataLifecycleResult;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;

interface DataLifecycleParticipant
{
    public function preview(DataLifecycleSubject $subject, DataLifecycleOperation $operation): DataLifecyclePreview;

    public function execute(DataLifecycleSubject $subject, DataLifecycleOperation $operation, string $correlationId): DataLifecycleResult;
}
