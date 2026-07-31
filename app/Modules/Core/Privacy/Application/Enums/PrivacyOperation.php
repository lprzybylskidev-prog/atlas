<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Application\Enums;

use App\Shared\Application\DataLifecycle\DataLifecycleOperation;

enum PrivacyOperation: string
{
    case HardDelete = 'hard_delete';
    case Anonymization = 'anonymization';

    public function lifecycleOperation(): DataLifecycleOperation
    {
        return match ($this) {
            self::HardDelete => DataLifecycleOperation::Delete,
            self::Anonymization => DataLifecycleOperation::Anonymize,
        };
    }

    public function confirmationPhrase(string $subjectIdentifier): string
    {
        return match ($this) {
            self::HardDelete => 'HARD DELETE '.$subjectIdentifier,
            self::Anonymization => 'ANONYMIZE '.$subjectIdentifier,
        };
    }
}
